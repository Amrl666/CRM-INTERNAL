<?php

namespace App\Domain\Campaigns\Actions;

use App\Domain\Audit\Actions\RecordAuditAction;
use App\Domain\Campaigns\Enums\CampaignStatus;
use App\Domain\Campaigns\Enums\DeliveryStatus;
use App\Domain\Campaigns\Enums\EligibilityStatus;
use App\Domain\Campaigns\Enums\PdfStatus;
use App\Domain\Campaigns\Enums\ProposalChannel;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Models\CampaignRecipient;
use App\Domain\Campaigns\Models\ProposalDeliveryAttempt;
use App\Domain\Campaigns\Services\BusinessSchedule;
use App\Domain\Communications\Contracts\ProposalEmailSender;
use App\Domain\Communications\Contracts\ProposalWhatsAppSender;
use App\Domain\Communications\DTOs\CommunicationResult;
use App\Domain\Communications\DTOs\ProposalEmailData;
use App\Domain\Communications\DTOs\ProposalWhatsAppData;
use App\Domain\Communications\Models\CommunicationMessage;
use App\Domain\Consent\Services\CommunicationEligibility;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final class SendProposalDeliveryAction
{
    public function __construct(
        private readonly CommunicationEligibility $eligibility,
        private readonly BusinessSchedule $schedule,
        private readonly RenderProposalMessageAction $renderMessage,
        private readonly ProposalEmailSender $emailSender,
        private readonly ProposalWhatsAppSender $whatsAppSender,
        private readonly RecordAuditAction $audit,
    ) {}

    public function handle(CampaignRecipient $recipient): CommunicationResult
    {
        $recipient->loadMissing(['campaign.proposalTemplate', 'contact.company', 'lead.source']);
        $campaign = $recipient->campaign;

        if (! $campaign->isApprovedForCurrentRevision()) {
            return CommunicationResult::fail('campaign_not_approved');
        }

        if ($campaign->status === CampaignStatus::Paused) {
            return CommunicationResult::fail('campaign_paused');
        }

        if ($campaign->status === CampaignStatus::Cancelled) {
            return CommunicationResult::fail('campaign_cancelled');
        }

        $terminal = [
            DeliveryStatus::Sent, DeliveryStatus::Accepted, DeliveryStatus::Delivered,
            DeliveryStatus::Failed, DeliveryStatus::Skipped, DeliveryStatus::Cancelled,
        ];

        if (in_array($recipient->delivery_status, $terminal, true)) {
            return CommunicationResult::fail('already_processed');
        }

        $reasons = $this->eligibility->check(
            $recipient->contact,
            $campaign->channel->consentChannel(),
            $recipient->lead,
        );

        if ($reasons !== []) {
            $message = implode(' ', $reasons);
            $recipient->update([
                'eligibility_status' => EligibilityStatus::Ineligible,
                'eligibility_reason' => $message,
                'delivery_status' => DeliveryStatus::Skipped,
                'skip_reason' => $message,
                'processed_at' => now(),
            ]);
            $this->audit->handle(action: 'proposal.recipient_skipped', subject: $recipient, after: ['reason' => $message]);

            return CommunicationResult::fail('ineligible');
        }

        if ($campaign->channel === ProposalChannel::WhatsApp && ! $this->whatsAppSender->isEnabled()) {
            $reason = 'Campaign proposal WhatsApp dinonaktifkan administrator.';
            $recipient->update([
                'delivery_status' => DeliveryStatus::Skipped,
                'skip_reason' => $reason,
                'processed_at' => now(),
            ]);
            $this->audit->handle(action: 'proposal.recipient_skipped', subject: $recipient, after: ['reason' => $reason]);

            return CommunicationResult::fail('whatsapp_disabled');
        }

        if ($recipient->pdf_status !== PdfStatus::Ready || $recipient->attachment_path === null) {
            $recipient->update([
                'delivery_status' => DeliveryStatus::Failed,
                'last_error' => 'PDF belum siap.',
                'processed_at' => now(),
            ]);

            return CommunicationResult::fail('pdf_not_ready');
        }

        $disk = (string) config('proposals.storage_disk', 'local');
        if (! Storage::disk($disk)->exists($recipient->attachment_path)) {
            $recipient->update([
                'delivery_status' => DeliveryStatus::Failed,
                'last_error' => 'Berkas PDF tidak ditemukan.',
                'processed_at' => now(),
            ]);

            return CommunicationResult::fail('pdf_missing');
        }

        if ($recipient->rendered_subject === null || $recipient->rendered_body_text === null) {
            try {
                $recipient = $this->renderMessage->handle($recipient);
            } catch (\Throwable $e) {
                $recipient->update([
                    'delivery_status' => DeliveryStatus::Failed,
                    'last_error' => $e->getMessage(),
                    'processed_at' => now(),
                ]);

                return CommunicationResult::fail('message_render_failed');
            }
        }

        $claim = DB::transaction(function () use ($recipient, $campaign): array {
            $lockedCampaign = Campaign::query()
                ->lockForUpdate()
                ->findOrFail($campaign->id);

            $locked = CampaignRecipient::query()
                ->lockForUpdate()
                ->findOrFail($recipient->id);

            if (! in_array($lockedCampaign->status, [
                CampaignStatus::Scheduled,
                CampaignStatus::Running,
            ], true)) {
                return ['attempt' => null, 'reason' => 'delivery_not_claimed'];
            }

            if (! $lockedCampaign->isApprovedForCurrentRevision()) {
                return ['attempt' => null, 'reason' => 'delivery_not_claimed'];
            }

            if ($locked->delivery_status !== DeliveryStatus::Scheduled) {
                return ['attempt' => null, 'reason' => 'delivery_not_claimed'];
            }

            if (
                $locked->scheduled_at === null
                || $locked->scheduled_at->isFuture()
            ) {
                return ['attempt' => null, 'reason' => 'delivery_not_claimed'];
            }

            $now = now();

            // Validasi ulang slot berdasarkan pengiriman aktual dan reservasi
            // saat worker benar-benar akan mengambil pekerjaan.
            $runtimeSlot = $this->schedule->plan(
                $lockedCampaign,
                [$locked->id],
                $now,
            )[$locked->id] ?? null;

            if ($runtimeSlot === null) {
                return ['attempt' => null, 'reason' => 'no_available_slot'];
            }

            // Jika ada konflik spacing, rate limit, atau jam operasional,
            // pindahkan jadwal recipient dan jangan kirim sekarang.
            if ($runtimeSlot->greaterThan($now)) {
                $locked->update([
                    'scheduled_at' => $runtimeSlot,
                ]);

                return ['attempt' => null, 'reason' => 'rescheduled'];
            }

            if (! $this->schedule->isWithinBusinessWindow($lockedCampaign, $now)) {
                return ['attempt' => null, 'reason' => 'outside_business_window'];
            }

            $locked->update([
                'delivery_status' => DeliveryStatus::Processing,
                'attempts' => $locked->attempts + 1,
                'last_error' => null,
            ]);

            if ($lockedCampaign->status === CampaignStatus::Scheduled) {
                $lockedCampaign->update([
                    'status' => CampaignStatus::Running,
                    'started_at' => now(),
                ]);
            }

            return [
                'attempt' => (int) $locked->attempts,
                'reason' => null,
            ];
        });

        if ($claim['attempt'] === null) {
            return CommunicationResult::fail(
                $claim['reason'] ?? 'delivery_not_claimed'
            );
        }

        $attemptNumber = $claim['attempt'];

        $recipient->refresh();
        $absolutePath = Storage::disk($disk)->path((string) $recipient->attachment_path);

        try {
            $result = $campaign->channel === ProposalChannel::Email
                ? $this->emailSender->sendProposal(new ProposalEmailData(
                    to: (string) $recipient->contact->normalized_email,
                    subject: (string) $recipient->rendered_subject,
                    htmlBody: (string) $recipient->rendered_body_html,
                    textBody: (string) $recipient->rendered_body_text,
                    attachmentPath: $absolutePath,
                    attachmentFilename: (string) ($recipient->attachment_filename ?: basename($absolutePath)),
                ))
                : $this->whatsAppSender->sendProposalDocument(new ProposalWhatsAppData(
                    to: (string) $recipient->contact->phone_e164,
                    body: (string) ($recipient->rendered_whatsapp ?? $recipient->rendered_body_text),
                    attachmentPath: $absolutePath,
                    attachmentFilename: (string) ($recipient->attachment_filename ?: basename($absolutePath)),
                ));
        } catch (\Throwable $e) {
            $result = CommunicationResult::unknown('provider_exception');
        }

        $provider = $campaign->channel === ProposalChannel::Email
            ? $this->emailSender->getProviderName()
            : $this->whatsAppSender->getProviderName();

        $senderEmail = $campaign->channel === ProposalChannel::Email
            ? $this->emailSender->getSenderEmail()
            : null;

        $senderName = $campaign->channel === ProposalChannel::Email
            ? $this->emailSender->getSenderName()
            : null;

        ProposalDeliveryAttempt::create([
            'campaign_recipient_id' => $recipient->id,
            'channel' => (string) $campaign->channel?->value,
            'provider' => $provider,
            'status' => $result->status ?? 'unknown',
            'attempt_number' => $attemptNumber,
            'provider_message_id' => $result->providerMessageId,
            'response_meta' => ['success' => $result->success, 'reason' => $result->reason],
            'error' => $result->success ? null : $result->reason,
            'attempted_at' => now(),
        ]);

        if ($result->success) {
            $communication = CommunicationMessage::create([
                'contact_id' => $recipient->contact_id,
                'campaign_id' => $campaign->id,
                'channel' => strtolower((string) $campaign->channel?->value),
                'direction' => 'outbound',
                'subject' => $recipient->rendered_subject,
                'body' => $recipient->rendered_body_text,
                'provider' => $provider,
                'provider_message_id' => $result->providerMessageId,
                'status' => $result->status === 'sent' ? 'sent' : 'accepted',
                'sender_email' => $senderEmail,
                'sender_name' => $senderName,
                'sent_at' => now(),
            ]);

            $recipient->update([
                'delivery_status' => $result->status === 'sent' ? DeliveryStatus::Sent : DeliveryStatus::Accepted,
                'provider_message_id' => $result->providerMessageId,
                'message_id' => $communication->id,
                'processed_at' => now(),
                'last_error' => null,
            ]);

            $this->audit->handle(action: 'proposal.delivery_attempted', subject: $recipient, after: [
                'attempt' => $attemptNumber,
                'provider' => $provider,
                'provider_message_id' => $result->providerMessageId,
                'status' => $result->status,
            ]);

            $this->completeCampaignIfDone($recipient);

            return $result;
        }

        if (($result->status ?? 'failed') === 'unknown') {
            $recipient->update([
                'delivery_status' => DeliveryStatus::Unknown,
                'processed_at' => now(),
                'last_error' => $result->reason,
            ]);

            $this->audit->handle(action: 'proposal.delivery_reconciled', subject: $recipient, after: [
                'outcome' => 'unknown',
                'reason' => $result->reason,
            ]);

            return $result;
        }

        $retryable = in_array($result->reason, ['gmail_rate_limited', 'brevo_rate_limited', 'evolution_rate_limited'], true);

        if ($retryable && $attemptNumber < 3) {
            $recipient->update([
                'delivery_status' => DeliveryStatus::Scheduled,
                'last_error' => $result->reason,
            ]);

            return $result;
        }

        $recipient->update([
            'delivery_status' => DeliveryStatus::Failed,
            'processed_at' => now(),
            'last_error' => $result->reason,
        ]);

        $this->audit->handle(action: 'proposal.delivery_attempted', subject: $recipient, after: [
            'attempt' => $attemptNumber,
            'provider' => $provider,
            'status' => 'failed',
            'reason' => $result->reason,
        ]);

        $this->completeCampaignIfDone($recipient);

        return $result;
    }

    private function completeCampaignIfDone(CampaignRecipient $recipient): void
    {
        DB::transaction(function () use ($recipient): void {
            $campaign = Campaign::query()
                ->lockForUpdate()
                ->find($recipient->campaign_id);

            if ($campaign === null) {
                return;
            }

            if (in_array($campaign->status, [
                CampaignStatus::Paused,
                CampaignStatus::Cancelled,
                CampaignStatus::Completed,
            ], true)) {
                return;
            }

            $remaining = $campaign->recipients()
                ->whereNotIn(
                    'delivery_status',
                    DeliveryStatus::terminal(),
                )
                ->count();

            if ($remaining === 0) {
                $campaign->update([
                    'status' => CampaignStatus::Completed,
                    'completed_at' => now(),
                ]);
            }
        });
    }
}
