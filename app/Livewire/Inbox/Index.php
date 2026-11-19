<?php

namespace App\Livewire\Inbox;

use App\Domain\Communications\Actions\SendWhatsAppTextAction;
use App\Domain\Communications\Models\CommunicationMessage;
use App\Domain\Communications\Models\Conversation;
use App\Jobs\SendWhatsAppTextJob;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.sidebar', ['heading' => 'Inbox'])]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $filter = 'all';

    #[Url]
    public ?string $selected = null;

    public string $messageText = '';

    public string $channel = 'whatsapp';

    public function switchChannel(string $channel): void
    {
        abort_unless(in_array($channel, ['whatsapp', 'gmail'], true), 422);

        $this->channel = $channel;
        $this->selected = null;
        $this->resetPage();
    }

    public function mount(): void
    {
        $this->authorize('viewAny', Conversation::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilter(): void
    {
        $this->resetPage();
    }

    public function selectConversation(string $conversationId): void
    {
        $conversation = Conversation::findOrFail($conversationId);
        $this->authorize('view', $conversation);

        $this->selected = $conversationId;
        $this->markAsRead($conversationId);
    }

    public function markAsRead(string $conversationId): void
    {
        $conversation = Conversation::findOrFail($conversationId);
        $this->authorize('view', $conversation);

        $conversation->update(['last_read_at' => now()]);
    }

    public function closeConversation(string $conversationId): void
    {
        $conversation = Conversation::findOrFail($conversationId);
        $this->authorize('update', $conversation);

        $conversation->update(['status' => 'closed']);
        $this->dispatch('notify', message: 'Percakapan ditandai selesai.', type: 'success');
    }

    public function reopenConversation(string $conversationId): void
    {
        $conversation = Conversation::findOrFail($conversationId);
        $this->authorize('update', $conversation);

        $conversation->update(['status' => 'open']);
        $this->dispatch('notify', message: 'Percakapan dibuka kembali.', type: 'success');
    }

    public function sendMessage(SendWhatsAppTextAction $action): void
    {
        if ($this->selected === null) {
            return;
        }

        $conversation = Conversation::findOrFail($this->selected);
        $this->authorize('update', $conversation);

        $this->validate([
            'messageText' => ['required', 'string', 'max:4096'],
        ]);

        $action->handle(auth()->user(), $conversation, $this->messageText);

        $this->messageText = '';
        $this->dispatch('notify', message: 'Pesan masuk antrean pengiriman.', type: 'success');
    }

    public function retryMessage(string $messageId): void
    {
        $message = CommunicationMessage::findOrFail($messageId);
        $conversation = Conversation::findOrFail((string) $message->conversation_id);
        $this->authorize('update', $conversation);

        if ($message->status !== 'failed') {
            return;
        }

        $transitioned = CommunicationMessage::query()
            ->where('id', $message->id)
            ->where('status', 'failed')
            ->update(['status' => 'pending', 'provider_payload' => null]);

        if ($transitioned === 0) {
            return;
        }

        SendWhatsAppTextJob::dispatch($message->id);

        $this->dispatch('notify', message: 'Pesan diantrekan ulang.', type: 'success');
    }

    public function selectedConversation(): ?Conversation
    {
        if ($this->selected === null) {
            return null;
        }

        return Conversation::query()
            ->with(['contact', 'lead'])
            ->find($this->selected);
    }

    public function messages(): Collection
    {
        if ($this->selected === null) {
            return collect();
        }

        return CommunicationMessage::query()
            ->where('conversation_id', $this->selected)
            ->orderBy('created_at')
            ->get();
    }

    public function mediaKind(CommunicationMessage $message): ?string
    {
        /** @var mixed $rawPayload */
        $rawPayload = $message->provider_payload;
        $payload = is_array($rawPayload) ? $rawPayload : [];
        $messageNode = data_get($payload, 'data.message', []);

        if (! is_array($messageNode)) {
            return null;
        }

        foreach (['ephemeralMessage', 'viewOnceMessage', 'viewOnceMessageV2'] as $wrapper) {
            if (isset($messageNode[$wrapper]['message']) && is_array($messageNode[$wrapper]['message'])) {
                $messageNode = $messageNode[$wrapper]['message'];
                break;
            }
        }

        foreach (['imageMessage' => 'image', 'videoMessage' => 'video', 'documentMessage' => 'document', 'audioMessage' => 'audio'] as $key => $kind) {
            if (isset($messageNode[$key]) && is_array($messageNode[$key])) {
                return $kind;
            }
        }

        return null;
    }

    public function mediaFilename(CommunicationMessage $message): ?string
    {
        /** @var mixed $rawPayload */
        $rawPayload = $message->provider_payload;
        $payload = is_array($rawPayload) ? $rawPayload : [];
        $messageNode = data_get($payload, 'data.message', []);

        if (! is_array($messageNode)) {
            return null;
        }

        foreach (['ephemeralMessage', 'viewOnceMessage', 'viewOnceMessageV2'] as $wrapper) {
            if (isset($messageNode[$wrapper]['message']) && is_array($messageNode[$wrapper]['message'])) {
                $messageNode = $messageNode[$wrapper]['message'];
                break;
            }
        }

        foreach (['imageMessage', 'videoMessage', 'documentMessage', 'audioMessage'] as $key) {
            if (isset($messageNode[$key]['fileName'])) {
                return (string) $messageNode[$key]['fileName'];
            }
        }

        return null;
    }

    public function render()
    {
        $conversations = Conversation::query()
            ->with(['contact', 'lead'])
            ->when($this->search !== '', function ($query) {
                $term = '%'.mb_strtolower($this->search).'%';

                $query->where(function ($q) use ($term) {
                    $q->whereRaw('LOWER(remote_jid) LIKE ?', [$term])
                        ->orWhereHas('contact', fn ($c) => $c->whereRaw('LOWER(full_name) LIKE ?', [$term]));
                });
            })
            ->when($this->filter === 'unread', function ($query) {
                $query->whereExists(function ($sub) {
                    $sub->from('communication_messages')
                        ->whereColumn('communication_messages.conversation_id', 'conversations.id')
                        ->where('direction', 'inbound')
                        ->whereRaw('communication_messages.created_at > COALESCE(conversations.last_read_at, to_timestamp(0))');
                });
            })
            ->when($this->filter === 'open', fn ($q) => $q->where('status', 'open'))
            ->when($this->filter === 'closed', fn ($q) => $q->where('status', 'closed'))
            ->orderByDesc('last_message_at')
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('livewire.inbox.index', [
            'conversations' => $conversations,
            'selectedConversation' => $this->selectedConversation(),
            'messages' => $this->messages(),
            'channel' => $this->channel,
        ]);
    }
}
