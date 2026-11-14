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
use Livewire\Component;

#[Layout('layouts.sidebar', ['heading' => 'Detail Lead'])]
class Show extends Component
{
    public Lead $lead;

    public string $note = '';

    public string $ownerId = '';

    public string $emailSubject = '';

    public string $emailBody = '';

    public string $waSummary = '';

    public string $emailConsent = 'UNKNOWN';

    public function mount(Lead $lead): void
    {
        $this->authorize('view', $lead);
        $this->lead = $lead->load(['company', 'contact', 'source', 'owner']);
        $this->ownerId = (string) ($lead->owner_id ?? '');

        if ($lead->contact !== null) {
            $status = app(ConsentService::class)->currentStatus($lead->contact, Channel::Email);
            $this->emailConsent = $status->value;
        }
    }

    public function changeStatus(string $target, ChangeLeadStatusAction $action): void
    {
        $this->authorize('update', $this->lead);
        $action->handle($this->lead, LeadStatus::from($target), auth()->id());
        $this->lead->refresh();
    }

    public function assign(string $userId, AssignLeadAction $action): void
    {
        if ($userId === '') {
            return;
        }

        $this->authorize('assign', Lead::class);
        $action->handle($this->lead, (int) $userId, auth()->id());
        $this->lead->refresh();
    }

    public function addNote(): void
    {
        $this->authorize('update', $this->lead);
        $this->validate(['note' => ['required', 'string', 'max:2000']]);

        Note::create([
            'lead_id' => $this->lead->id,
            'user_id' => auth()->id(),
            'body' => $this->note,
        ]);

        $this->note = '';
    }

    public function archive(ArchiveLeadAction $action): void
    {
        $this->authorize('update', $this->lead);
        $action->handle($this->lead, auth()->id());
        $this->lead->refresh();
    }

    public function recordConsent(Channel $channel, RecordConsentAction $action): void
    {
        $this->authorize('update', $this->lead);
        $action->grant($this->lead->contact, $channel, auth()->id());
        $this->refreshEmailConsent();
    }

    public function revokeConsent(Channel $channel, RecordConsentAction $action): void
    {
        $this->authorize('update', $this->lead);
        $action->revoke($this->lead->contact, $channel, auth()->id());
        $this->refreshEmailConsent();
    }

    private function refreshEmailConsent(): void
    {
        if ($this->lead->contact !== null) {
            $status = app(ConsentService::class)->currentStatus($this->lead->contact, Channel::Email);
            $this->emailConsent = $status->value;
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

        $message = $action->handle(
            $this->lead->contact,
            trim($this->emailSubject),
            trim($this->emailBody),
            auth()->id(),
        );

        session()->flash('status', 'Draft berhasil dibuat.');
        $this->emailSubject = '';
        $this->emailBody = '';
    }

    public function sendDraft(CommunicationMessage $message, SendApprovedEmailAction $action): void
    {
        $this->authorize('update', $this->lead);

        try {
            $action->handle($message, auth()->id());
            session()->flash('status', 'Email telah dikirim ke Gmail (atau provider fake).');
        } catch (\InvalidArgumentException $e) {
            $this->addError('emailReasons', $e->getMessage());
        }
    }

    public function recordWaInteraction(RecordManualWhatsAppInteractionAction $action): void
    {
        $this->authorize('update', $this->lead);
        $this->validate(['waSummary' => ['required', 'string', 'max:2000']]);

        if ($this->lead->contact === null) {
            return;
        }

        $action->handle($this->lead->contact, trim($this->waSummary), auth()->id());
        $this->waSummary = '';
    }

    public function render()
    {
        $messages = [];
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
            'owners' => auth()->user()->can('leads.assign') ? User::orderBy('name')->get() : collect(),
            'activities' => $this->lead->activities()->with('lead')->limit(50)->get(),
            'notes' => $this->lead->notes()->with('lead')->limit(50)->get(),
            'messages' => $messages,
        ]);
    }
}
