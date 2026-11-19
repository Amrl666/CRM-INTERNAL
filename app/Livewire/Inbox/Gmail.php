<?php

namespace App\Livewire\Inbox;

use App\Domain\Communications\Actions\DispatchGmailSyncAction;
use App\Domain\Communications\DTOs\GmailSyncDispatchResult;
use App\Domain\Communications\Models\CommunicationAccount;
use App\Domain\Communications\Models\CommunicationMessage;
use App\Domain\Communications\Services\GmailSyncStatus;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Livewire\WithPagination;

class Gmail extends Component
{
    use WithPagination;

    public string $search = '';

    public string $filter = 'all';

    public ?string $selected = null;

    public ?string $syncFeedback = null;

    public string $syncFeedbackType = 'info';

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('inbox.view'), 403);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilter(): void
    {
        abort_unless(in_array($this->filter, ['all', 'unread', 'starred'], true), 422);

        $this->selected = null;
        $this->resetPage();
    }

    public function syncNow(DispatchGmailSyncAction $action): void
    {
        abort_unless(auth()->user()?->can('inbox.view'), 403);

        $result = $action->handle();

        $this->syncFeedback = $result->message;
        $this->syncFeedbackType = match ($result->status) {
            GmailSyncDispatchResult::STATUS_DISPATCHED => 'success',
            GmailSyncDispatchResult::STATUS_ALREADY_RUNNING,
            GmailSyncDispatchResult::STATUS_THROTTLED => 'info',
            default => 'error',
        };
    }

    private function primaryAccountRecord(): ?CommunicationAccount
    {
        $email = mb_strtolower(trim((string) config(
            'communications.email.gmail.primary_email'
        )));

        if ($email === '') {
            return null;
        }

        return CommunicationAccount::query()
            ->where('provider', 'gmail')
            ->whereRaw('LOWER(account_email) = ?', [$email])
            ->first();
    }

    private function primaryAccount(): ?CommunicationAccount
    {
        $account = $this->primaryAccountRecord();

        return $account?->isConnected() ? $account : null;
    }

    private function messageQuery(CommunicationAccount $account): Builder
    {
        return CommunicationMessage::query()
            ->where('account_id', $account->id)
            ->where('provider', 'gmail')
            ->where('channel', 'email')
            ->where('status', '!=', 'deleted')
            ->where(function (Builder $query): void {
                // Legacy rows may have no label metadata yet; keep those
                // visible, but hide messages removed from INBOX.
                $query->whereNull('gmail_label_ids')
                    ->orWhereJsonContains('gmail_label_ids', 'INBOX');
            });
    }

    public function selectMessage(string $messageId): void
    {
        $account = $this->primaryAccount();

        abort_if($account === null, 404);

        $message = $this->messageQuery($account)
            ->whereKey($messageId)
            ->firstOrFail();

        $this->selected = $message->id;
    }

    public function selectedMessage(): ?CommunicationMessage
    {
        $account = $this->primaryAccount();

        if ($account === null || $this->selected === null) {
            return null;
        }

        return $this->messageQuery($account)
            ->whereKey($this->selected)
            ->first();
    }

    public function selectedThreadMessages()
    {
        $selected = $this->selectedMessage();

        if ($selected === null) {
            return collect();
        }

        $query = $this->messageQuery($selected->account);

        if (filled($selected->thread_id)) {
            $query->where('thread_id', $selected->thread_id);
        } else {
            $query->whereKey($selected->id);
        }

        return $query
            ->orderBy('received_at')
            ->orderBy('created_at')
            ->get();
    }

    public function render()
    {
        $account = $this->primaryAccount();
        $statusAccount = $this->primaryAccountRecord();
        $syncStatus = app(GmailSyncStatus::class);

        $messages = $account
            ? $this->messageQuery($account)
                ->when(trim($this->search) !== '', function (Builder $query) {
                    $term = '%'.mb_strtolower(trim($this->search)).'%';

                    $query->where(function (Builder $search) use ($term) {
                        $search->whereRaw('LOWER(COALESCE(subject, \'\')) LIKE ?', [$term])
                            ->orWhereRaw('LOWER(COALESCE(sender_email, \'\')) LIKE ?', [$term])
                            ->orWhereRaw('LOWER(COALESCE(sender_name, \'\')) LIKE ?', [$term])
                            ->orWhereRaw('LOWER(COALESCE(body, \'\')) LIKE ?', [$term]);
                    });
                })
                ->when($this->filter === 'unread', function (Builder $query): void {
                    $query->where(function (Builder $q): void {
                        $q->where('is_read', false)->orWhereNull('is_read');
                    });
                })
                ->when($this->filter === 'starred', function (Builder $query): void {
                    $query->where('is_starred', true);
                })
                ->orderByRaw('received_at DESC NULLS LAST')
                ->orderByDesc('created_at')
                ->paginate(20)
            : CommunicationMessage::query()->whereRaw('1 = 0')->paginate(20);

        return view('livewire.inbox.gmail', [
            'account' => $account,
            'statusAccount' => $statusAccount,
            'messages' => $messages,
            'selectedMessage' => $this->selectedMessage(),
            'threadMessages' => $this->selectedThreadMessages(),
            'syncRunning' => $statusAccount !== null
                && $syncStatus->isRunning($statusAccount->id),
            'reconnectRequired' => $statusAccount !== null
                && ! $statusAccount->isConnected(),
            'resync' => $statusAccount !== null
                ? $syncStatus->historyExpired($statusAccount->id)
                : null,
            'lastFailure' => $statusAccount !== null
                ? $syncStatus->lastFailure($statusAccount->id)
                : null,
        ]);
    }
}
