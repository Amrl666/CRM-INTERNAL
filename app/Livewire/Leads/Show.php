<?php

namespace App\Livewire\Leads;

use App\Domain\Communications\Actions\CreateEmailDraftAction;
use App\Domain\Communications\Actions\RecordManualWhatsAppInteractionAction;
use App\Domain\Communications\Actions\SendApprovedEmailAction;
use App\Domain\Communications\Models\CommunicationMessage;
use App\Domain\Consent\Actions\RecordConsentAction;
use App\Domain\Consent\Enums\Channel;
use App\Domain\Consent\Services\CommunicationEligibility;
use App\Domain\Consent\Services\ConsentService;
use App\Domain\Leads\Actions\ArchiveLeadAction;
use App\Domain\Leads\Actions\AssignLeadAction;
use App\Domain\Leads\Actions\ChangeLeadStatusAction;
use App\Domain\Leads\Enums\LeadStatus;
use App\Domain\Leads\Models\Lead;
use App\Domain\Leads\Models\Note;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('layouts.sidebar', ['heading' => 'Detail Lead'])]
class Show extends Component
{
    #[Locked]
    public Lead $lead;

    public string $note = '';

    public string $ownerId = '';

    public string $emailSubject = '';

    public string $emailBody = '';

    public string $waSummary = '';

    public string $emailConsent = 'UNKNOWN';

    public string $waConsent = 'UNKNOWN';

    public function mount(Lead $lead): void
    {
        $this->authorize('view', $lead);
        $this->lead = $lead->load(['company', 'contact', 'source', 'owner']);
        $this->ownerId = (string) ($lead->owner_id ?? '');

        if ($lead->contact !== null) {
            $status = app(ConsentService::class)->currentStatus($lead->contact, Channel::Email);
            $this->emailConsent = $status->value;

            $waStatus = app(ConsentService::class)->currentStatus($lead->contact, Channel::WhatsApp);
            $this->waConsent = $waStatus->value;
        }
    }

    public function changeStatus(string $target, ChangeLeadStatusAction $action): void
    {
        $this->authorize('update', $this->lead);

        $status = LeadStatus::tryFrom($target);

        if ($status === null) {
            $this->addError('status', 'Status tujuan tidak dikenal.');

            return;
        }

        try {
            $action->handle($this->lead, $status, auth()->id());
            $this->lead->refresh();
            $this->dispatch('notify', message: 'Status lead diperbarui menjadi '.$status->label().'.', type: 'success');
        } catch (\InvalidArgumentException $e) {
            $this->addError('status', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);
            $this->addError('status', 'Status lead tidak dapat diubah. Silakan coba lagi.');
        }
    }

    public function assign(string $userId, AssignLeadAction $action): void
    {
        if ($userId === '') {
            return;
        }

        $this->authorize('assign', Lead::class);

        $owner = User::query()->whereKey($userId)->where('is_active', true)->first();

        if ($owner === null) {
            $this->addError('ownerId', 'Pengguna yang dipilih tidak ditemukan atau tidak aktif.');

            return;
        }

        try {
            $action->handle($this->lead, $owner->id, auth()->id());
            $this->lead->refresh();
            $this->ownerId = (string) $owner->id;
            $this->dispatch('notify', message: 'Lead ditugaskan kepada '.$owner->name.'.', type: 'success');
        } catch (\Throwable $e) {
            report($e);
            $this->addError('ownerId', 'Lead tidak dapat ditugaskan. Silakan coba lagi.');
        }
    }

    public function addNote(): void
    {
        $this->authorize('update', $this->lead);
        $this->validate(['note' => ['required', 'string', 'max:2000']]);

        try {
            Note::create([
                'lead_id' => $this->lead->id,
                'user_id' => auth()->id(),
                'body' => trim($this->note),
            ]);

            $this->note = '';
            $this->dispatch('notify', message: 'Catatan berhasil ditambahkan.', type: 'success');
        } catch (\Throwable $e) {
            report($e);
            $this->addError('note', 'Catatan tidak dapat disimpan. Silakan coba lagi.');
        }
    }

    public function archive(ArchiveLeadAction $action): void
    {
        $this->authorize('update', $this->lead);

        try {
            $action->handle($this->lead, auth()->id());
            session()->flash('status', 'Lead berhasil diarsipkan.');

            $this->redirectRoute('leads.index', navigate: true);
        } catch (\Throwable $e) {
            report($e);
            $this->addError('status', 'Lead tidak dapat diarsipkan. Silakan coba lagi.');
        }
    }

    public function recordConsent(Channel $channel, RecordConsentAction $action): void
    {
        $this->authorize('update', $this->lead);

        if ($this->lead->contact === null) {
            $this->addError('consent', 'Lead belum memiliki kontak.');

            return;
        }

        try {
            $action->grant($this->lead->contact, $channel, auth()->id());
            $this->refreshConsentStatuses();
            $this->dispatch('notify', message: 'Izin komunikasi berhasil dicatat.', type: 'success');
        } catch (\Throwable $e) {
            report($e);
            $this->addError('consent', 'Izin komunikasi tidak dapat dicatat. Silakan coba lagi.');
        }
    }

    public function revokeConsent(Channel $channel, RecordConsentAction $action): void
    {
        $this->authorize('update', $this->lead);

        if ($this->lead->contact === null) {
            $this->addError('consent', 'Lead belum memiliki kontak.');

            return;
        }

        try {
            $action->revoke($this->lead->contact, $channel, auth()->id());
            $this->refreshConsentStatuses();
            $this->dispatch('notify', message: 'Izin komunikasi berhasil dicabut.', type: 'success');
        } catch (\Throwable $e) {
            report($e);
            $this->addError('consent', 'Izin komunikasi tidak dapat dicabut. Silakan coba lagi.');
        }
    }

    private function refreshConsentStatuses(): void
    {
        if ($this->lead->contact !== null) {
            $this->emailConsent = app(ConsentService::class)
                ->currentStatus($this->lead->contact, Channel::Email)->value;

            $this->waConsent = app(ConsentService::class)
                ->currentStatus($this->lead->contact, Channel::WhatsApp)->value;
        }
    }

    public function createDraft(CreateEmailDraftAction $action, CommunicationEligibility $eligibility): void
    {
        $this->authorize('update', $this->lead);

        if ($this->lead->contact === null) {
            $this->addError('emailDraft', 'Belum ada kontak.');

            return;
        }

        $reasons = $eligibility->check($this->lead->contact, Channel::Email, $this->lead);

        if ($reasons !== []) {
            $this->addError('emailReasons', implode('. ', $reasons));

            return;
        }

        $this->validate([
            'emailSubject' => ['required', 'string', 'max:500'],
            'emailBody' => ['required', 'string'],
        ]);

        try {
            $message = $action->handle(
                $this->lead->contact,
                trim($this->emailSubject),
                trim($this->emailBody),
                auth()->id(),
            );

            if ($message->status === 'failed') {
                $this->addError('emailDraft', 'Draft email gagal dibuat di provider. Periksa koneksi akun Gmail.');

                return;
            }

            $this->emailSubject = '';
            $this->emailBody = '';
            $this->dispatch('notify', message: 'Draft email berhasil dibuat.', type: 'success');
        } catch (\Throwable $e) {
            report($e);
            $this->addError('emailDraft', 'Draft email tidak dapat dibuat. Silakan coba lagi.');
        }
    }

    public function sendDraft(CommunicationMessage $message, SendApprovedEmailAction $action): void
    {
        $this->authorize('update', $this->lead);

        if ($this->lead->contact === null || $message->contact_id !== $this->lead->contact->id) {
            $this->addError('emailReasons', 'Draft email tidak terkait dengan lead ini.');

            return;
        }

        try {
            $action->handle($message, (int) auth()->id());

            if ($message->status === 'sent') {
                $this->dispatch('notify', message: 'Email telah dikirim ke Gmail (atau provider fake).', type: 'success');
            } elseif ($message->status === 'cancelled') {
                $this->dispatch('notify', message: 'Email dibatalkan karena kontak tidak lagi memenuhi syarat komunikasi.', type: 'warning');
            } else {
                $this->addError('emailReasons', 'Email gagal dikirim. Periksa status akun Gmail dan coba lagi.');
            }
        } catch (\InvalidArgumentException $e) {
            $this->addError('emailReasons', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);
            $this->addError('emailReasons', 'Email tidak dapat dikirim. Silakan coba lagi.');
        }
    }

    public function recordWaInteraction(RecordManualWhatsAppInteractionAction $action): void
    {
        $this->authorize('update', $this->lead);
        $this->validate(['waSummary' => ['required', 'string', 'max:2000']]);

        if ($this->lead->contact === null) {
            $this->addError('waSummary', 'Lead belum memiliki kontak.');

            return;
        }

        try {
            $action->handle($this->lead->contact, trim($this->waSummary), (int) auth()->id());
            $this->waSummary = '';
            $this->dispatch('notify', message: 'Interaksi WhatsApp berhasil dicatat.', type: 'success');
        } catch (\Throwable $e) {
            report($e);
            $this->addError('waSummary', 'Interaksi WhatsApp tidak dapat dicatat. Silakan coba lagi.');
        }
    }

    public function render()
    {
        $messages = collect();

        if ($this->lead->contact !== null) {
            $messages = CommunicationMessage::query()
                ->where('contact_id', $this->lead->contact->id)
                ->orderByDesc('created_at')
                ->limit(10)
                ->get(['id', 'channel', 'direction', 'subject', 'status', 'sent_at', 'provider_message_id']);
        }

        $status = $this->lead->status instanceof LeadStatus ? $this->lead->status : LeadStatus::from((string) $this->lead->status);

        return view('livewire.leads.show', [
            'transitions' => $status->allowedTransitions(),
            'owners' => auth()->user()->can('leads.assign') ? User::where('is_active', true)->orderBy('name')->get() : collect(),
            'activities' => $this->lead->activities()->with('lead')->limit(50)->get(),
            'notes' => $this->lead->notes()->with('lead')->limit(50)->get(),
            'messages' => $messages,
        ]);
    }
}
