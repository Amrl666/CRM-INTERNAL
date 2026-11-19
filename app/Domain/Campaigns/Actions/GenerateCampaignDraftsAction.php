<?php

namespace App\Domain\Campaigns\Actions;

use App\Domain\Audit\Actions\RecordAuditAction;
use App\Domain\Campaigns\Enums\CampaignStatus;
use App\Domain\Campaigns\Exceptions\CampaignNotReadyForDraftGeneration;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Models\CampaignRecipient;
use App\Domain\Campaigns\Models\ProposalSenderSetting;
use App\Domain\Campaigns\Services\MessagePersonalization;
use App\Domain\Communications\Actions\CreateEmailDraftAction;
use App\Domain\Consent\Enums\Channel;
use App\Domain\Consent\Services\CommunicationEligibility;
use App\Domain\Contacts\Models\Contact;
use Illuminate\Support\Facades\DB;

final class GenerateCampaignDraftsAction
{
    public function __construct(
        private readonly CreateEmailDraftAction $emailDraft,
        private readonly CommunicationEligibility $eligibility,
        private readonly RecordAuditAction $audit,
        private readonly MessagePersonalization $personalization,
    ) {}

    public function handle(Campaign $campaign, ?int $userId = null): Campaign
    {
        $claimed = DB::transaction(function () use ($campaign): bool {
            $locked = Campaign::query()
                ->lockForUpdate()
                ->findOrFail($campaign->id);

            if ($locked->status === CampaignStatus::Approved) {
                $locked->update([
                    'status' => CampaignStatus::Sending,
                    'started_at' => now(),
                ]);

                return true;
            }

            /*
             * Narrow recovery for the observed legacy state:
             * Sending, started_at NULL, all recipients still pending,
             * and no provider or local message IDs.
             */
            if (
                $locked->status === CampaignStatus::Sending
                && $locked->started_at === null
            ) {
                $hasProgress = $locked->recipients()
                    ->where(function ($query): void {
                        $query->where('status', '!=', 'pending')
                            ->orWhereNotNull('provider_draft_id')
                            ->orWhereNotNull('message_id');
                    })
                    ->exists();

                if (! $hasProgress) {
                    $locked->update(['started_at' => now()]);

                    return true;
                }
            }

            return false;
        });

        if (! $claimed) {
            throw new CampaignNotReadyForDraftGeneration(
                'Campaign tidak berada pada status yang dapat diproses.'
            );
        }

        $campaign = Campaign::with('template')->findOrFail($campaign->id);
        $template = $campaign->template;

        if ($template === null) {
            $campaign->update([
                'status' => CampaignStatus::Failed,
                'completed_at' => now(),
            ]);

            throw new \RuntimeException('Template email campaign tidak ditemukan.');
        }

        $createdBy = $userId ?? (int) $campaign->approved_by;

        if ($createdBy <= 0) {
            $campaign->update([
                'status' => CampaignStatus::Failed,
                'completed_at' => now(),
            ]);

            throw new \RuntimeException('User pembuat draft tidak valid.');
        }

        $recipientIds = $campaign->recipients()
            ->where('status', 'pending')
            ->pluck('id');

        foreach ($recipientIds as $recipientId) {
            $recipientClaimed = DB::transaction(function () use ($recipientId): bool {
                $recipient = CampaignRecipient::query()
                    ->lockForUpdate()
                    ->find($recipientId);

                if ($recipient === null || $recipient->status !== 'pending') {
                    return false;
                }

                if (
                    $recipient->provider_draft_id !== null
                    || $recipient->message_id !== null
                ) {
                    $recipient->update([
                        'status' => 'unknown',
                        'error' => 'Draft atau message ID sudah tercatat. Perlu rekonsiliasi manual.',
                    ]);

                    return false;
                }

                $recipient->update([
                    'status' => 'processing',
                    'error' => null,
                ]);

                return true;
            });

            if (! $recipientClaimed) {
                continue;
            }

            $recipient = CampaignRecipient::with([
                'contact.company',
                'lead.source',
            ])->find($recipientId);

            if ($recipient === null || $recipient->contact === null) {
                CampaignRecipient::whereKey($recipientId)->update([
                    'status' => 'failed',
                    'error' => 'Recipient atau contact tidak ditemukan.',
                ]);

                continue;
            }

            $contact = $recipient->contact;

            try {
                $reasons = $this->eligibility->check($contact, Channel::Email, $recipient->lead);

                if ($reasons !== []) {
                    $recipient->update([
                        'status' => 'skipped',
                        'error' => implode('. ', $reasons),
                    ]);

                    continue;
                }

                $variables = $this->variables($campaign, $contact, $recipient);
                $subject = $this->personalization->subject($template->subject, $variables);
                $body = $this->personalization->html($template->body, $variables);
            } catch (\Throwable $e) {
                report($e);

                $recipient->update([
                    'status' => 'failed',
                    'error' => 'Validasi atau personalisasi gagal: '.$e->getMessage(),
                ]);

                continue;
            }

            /*
             * Gmail is called before the local CommunicationMessage is saved.
             * An exception here has an uncertain provider outcome.
             */
            try {
                $message = $this->emailDraft->handle(
                    contact: $contact,
                    subject: $subject,
                    body: $body,
                    createdBy: $createdBy,
                    campaignId: $campaign->id,
                );

                $recipient->update([
                    'status' => $message->status === 'draft' ? 'draft_created' : 'failed',
                    'provider_draft_id' => $message->provider_message_id,
                    'message_id' => $message->id,
                    'error' => $message->status === 'draft' ? null : $message->status,
                ]);
            } catch (\Throwable $e) {
                report($e);

                CampaignRecipient::whereKey($recipientId)
                    ->where('status', 'processing')
                    ->update([
                        'status' => 'unknown',
                        'error' => 'Hasil Gmail tidak dapat dipastikan. Periksa Gmail dan riwayat lokal sebelum mencoba ulang.',
                    ]);
            }
        }

        $counts = [
            'drafts' => $campaign->recipients()->where('status', 'draft_created')->count(),
            'skipped' => $campaign->recipients()->where('status', 'skipped')->count(),
            'failed' => $campaign->recipients()->where('status', 'failed')->count(),
            'unknown' => $campaign->recipients()->where('status', 'unknown')->count(),
            'processing' => $campaign->recipients()->where('status', 'processing')->count(),
            'pending' => $campaign->recipients()->where('status', 'pending')->count(),
        ];

        $hasUnresolved = $counts['unknown'] > 0
            || $counts['processing'] > 0
            || $counts['pending'] > 0;

        $campaign->update([
            'status' => $hasUnresolved
                ? CampaignStatus::Failed
                : CampaignStatus::Completed,
            'completed_at' => now(),
        ]);

        $this->audit->handle(
            user: $createdBy,
            action: 'campaign.drafts_generated',
            subject: $campaign,
            after: $counts,
        );

        return $campaign->refresh();
    }

    /** @return array<string, string|null> */
    private function variables(
        Campaign $campaign,
        Contact $contact,
        ?CampaignRecipient $recipient
    ): array {
        $sender = ProposalSenderSetting::query()
            ->where('is_active', true)
            ->latest('updated_at')
            ->first();

        $variables = [
            'contact_name' => $contact->full_name,
            'company_name' => (string) ($contact->company?->name ?? ''),
            'email' => (string) ($contact->normalized_email ?? ''),
            'phone' => (string) ($contact->phone_e164 ?? ''),
            'industry' => (string) ($contact->company?->industry ?? ''),
            'source' => (string) ($recipient?->lead?->source?->getAttribute('name') ?? ''),
        ];

        foreach ($sender?->toTemplateVariables() ?? [] as $key => $value) {
            if ($value !== null) {
                $variables[$key] = (string) $value;
            }
        }

        return $variables;
    }
}
