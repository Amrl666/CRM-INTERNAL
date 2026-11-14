<?php

namespace App\Domain\Campaigns\Actions;

use App\Domain\Audit\Actions\RecordAuditAction;
use App\Domain\Campaigns\Enums\CampaignStatus;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Communications\Actions\CreateEmailDraftAction;
use App\Domain\Consent\Enums\Channel;
use App\Domain\Consent\Services\CommunicationEligibility;

final class GenerateCampaignDraftsAction
{
    public function __construct(
        private readonly CreateEmailDraftAction $emailDraft,
        private readonly CommunicationEligibility $eligibility,
        private readonly RecordAuditAction $audit,
    ) {}

    public function handle(Campaign $campaign, ?int $userId = null): Campaign
    {
        if ($campaign->status !== CampaignStatus::Approved) {
            throw new \InvalidArgumentException('Campaign harus disetujui sebelum membuat draft.');
        }

        $campaign->update(['status' => CampaignStatus::Sending]);
        $template = $campaign->template;

        $recipients = $campaign->recipients()
            ->with(['contact', 'campaign'])
            ->where('status', 'pending')
            ->get();

        foreach ($recipients as $recipient) {
            $contact = $recipient->contact;

            $reasons = $this->eligibility->check($contact, Channel::Email);

            if ($reasons !== []) {
                $recipient->update(['status' => 'skipped', 'error' => implode('. ', $reasons)]);

                continue;
            }

            $message = $this->emailDraft->handle(
                contact: $contact,
                subject: $template->subject,
                body: $this->render($template->body, $contact->full_name, $contact->company?->name),
                createdBy: $userId ?? 0,
                campaignId: $campaign->id,
            );

            $recipient->update([
                'status' => $message->status === 'draft' ? 'draft_created' : 'failed',
                'provider_draft_id' => $message->provider_message_id,
                'message_id' => $message->id,
                'error' => $message->status === 'draft' ? null : $message->status,
            ]);
        }

        $campaign->update(['status' => CampaignStatus::Completed]);

        $this->audit->handle(user: $userId, action: 'campaign.drafts_generated', subject: $campaign, after: [
            'drafts' => $campaign->recipients()->where('status', 'draft_created')->count(),
            'skipped' => $campaign->recipients()->where('status', 'skipped')->count(),
            'failed' => $campaign->recipients()->where('status', 'failed')->count(),
        ]);

        return $campaign;
    }

    private function render(string $body, string $contactName, ?string $companyName): string
    {
        return str_replace(
            ['{{contact_name}}', '{{company_name}}'],
            [$contactName, $companyName ?? '-'],
            $body
        );
    }
}
