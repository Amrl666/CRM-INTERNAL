<?php

namespace App\Domain\Campaigns\Actions;

use App\Domain\Audit\Actions\RecordAuditAction;
use App\Domain\Campaigns\Enums\CampaignStatus;
use App\Domain\Campaigns\Enums\DeliveryStatus;
use App\Domain\Campaigns\Enums\EligibilityStatus;
use App\Domain\Campaigns\Enums\PdfStatus;
use App\Domain\Campaigns\Enums\ProposalChannel;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Models\CampaignRecipientChannel;
use App\Domain\Campaigns\Models\ProposalDeliveryAttempt;
use App\Domain\Campaigns\Services\BusinessSchedule;
use App\Domain\Campaigns\Services\PriorOutreachGuard;
use App\Domain\Campaigns\Services\RecipientStatusRollup;
use App\Domain\Communications\Contracts\ProposalEmailSender;
use App\Domain\Communications\Contracts\ProposalWhatsAppSender;
use App\Domain\Communications\DTOs\CommunicationResult;
use App\Domain\Communications\DTOs\ProposalEmailData;
use App\Domain\Communications\DTOs\ProposalWhatsAppData;
use App\Domain\Communications\Models\CommunicationMessage;
use App\Domain\Consent\Enums\Channel as ConsentChannel;
use App\Domain\Consent\Services\CommunicationEligibility;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final class SendProposalChannelDeliveryAction
{
    public function __construct(
        private readonly CommunicationEligibility $eligibility,
        private readonly BusinessSchedule $schedule,
        private readonly RenderProposalMessageAction $renderMessage,
        private readonly ProposalEmailSender $emailSender,
        private readonly ProposalWhatsAppSender $whatsAppSender,
        private readonly PriorOutreachGuard $prior,
        private readonly RecipientStatusRollup $rollup,
        private readonly RecordAuditAction $audit,
    ) {}

    public function handle(CampaignRecipientChannel $channelRow): CommunicationResult
    {
        $channelRow->loadMissing(['recipient.campaign', 'recipient.contact.company', 'recipient.lead.source', 'campaign']);
        $recipient = $channelRow->recipient;
        $campaign = $recipient->campaign;
        $channel = $channelRow->channelEnum();
        $consentChannel = $channel->consentChannel();

        // A terminal channel row must stay idempotent even after the campaign
        // itself has completed; report the row state before campaign state.
        if ($channelRow->delivery_status?->isTerminal()) {
            return CommunicationResult::fail('already_processed');
        }

        if (! $campaign->isApprovedForCurrentRevision()) {
            return CommunicationResult::fail('campaign_not_approved');
        }

        if (in_array($campaign->status, [CampaignStatus::Paused, CampaignStatus::Cancelled], true)) {
            return CommunicationResult::fail('campaign_'.strtolower($campaign->status->value));
        }

        $reasons = $this->eligibility->check($recipient->contact, $consentChannel, $recipient->lead);

        if ($reasons !== []) {
            return $this->skip($channelRow, implode(' ', $reasons), 'ineligible');
        }

        $duplicate = $this->prior->blockedContacts(
            [$recipient->contact_id],
            $consentChannel,
            $campaign->id,
        );

        if ($duplicate !== []) {
            return $this->skip($channelRow, reset($duplicate), 'duplicate_outreach');
        }

        if ($channel === ProposalChannel::WhatsApp && ! $this->whatsAppSender->isEnabled()) {
            return $this->skip($channelRow, 'Campaign proposal WhatsApp dinonaktifkan administrator.', 'whatsapp_disabled');
        }

        if ($recipient->pdf_status !== PdfStatus::Ready || $recipient->attachment_path === null) {
            $channelRow->update([
                'delivery_status' => DeliveryStatus::Failed,
                'last_error' => 'PDF belum siap.',
                'processed_at' => now(),
            ]);
            $this->rollup->refresh($recipient);

            return CommunicationResult::fail('pdf_not_ready');
        }

        $disk = (string) config('proposals.storage_disk', 'local');
        if (! Storage::disk($disk)->exists($recipient->attachment_path)) {
            $channelRow->update([
                'delivery_status' => DeliveryStatus::Failed,
                'last_error' => 'Berkas PDF tidak ditemukan.',
                'processed_at' => now(),
            ]);
            $this->rollup->refresh($recipient);

            return CommunicationResult::fail('pdf_missing');
        }

        if (! $this->hasRenderedContent($recipient, $consentChannel)) {
            try {
                $this->renderMessage->handle($recipient);
            } catch (\Throwable $e) {
                $channelRow->update([
                    'delivery_status' => DeliveryStatus::Failed,
                    'last_error' => $e->getMessage(),
                    'processed_at' => now(),
                ]);
                $this->rollup->refresh($recipient);

                return CommunicationResult::fail('message_render_failed');
            }
        }

        try {
            $claim = $this->claim($campaign, $channelRow);
        } catch (UniqueConstraintViolationException) {
            return $this->skip($channelRow, 'Pengiriman yang sama sedang diproses campaign lain.', 'duplicate_processing');
        }

        if ($claim['attempt'] === null) {
            return CommunicationResult::fail($claim['reason'] ?? 'delivery_not_claimed');
        }

        $attemptNumber = (int) $claim['attempt'];
        $channelRow->refresh();
        $absolutePath = Storage::disk($disk)->path((string) $recipient->attachment_path);

        // Banner identity follows the creator/owner of the outreach, never the
        // generic proposal sender settings.
        $bannerSender = $consentChannel === ConsentChannel::Email
            ? User::query()->find($campaign->created_by)
            : null;

        try {
            $result = $consentChannel === ConsentChannel::Email
                ? $this->emailSender->sendProposal(new ProposalEmailData(
                    to: (string) $recipient->contact->normalized_email,
                    subject: (string) $recipient->rendered_subject,
                    htmlBody: (string) $recipient->rendered_body_html,
                    textBody: (string) $recipient->rendered_body_text,
                    attachmentPath: $absolutePath,
                    attachmentFilename: (string) ($recipient->attachment_filename ?: basename($absolutePath)),
                    senderName: $bannerSender?->name,
                    senderPosition: $bannerSender?->position,
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

        $provider = $consentChannel === ConsentChannel::Email
            ? $this->emailSender->getProviderName()
            : $this->whatsAppSender->getProviderName();

        ProposalDeliveryAttempt::create([
            'campaign_recipient_id' => $recipient->id,
            'campaign_recipient_channel_id' => $channelRow->id,
            'channel' => $channelRow->channel,
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
                'channel' => strtolower($channelRow->channel),
                'direction' => 'outbound',
                'subject' => $recipient->rendered_subject,
                'body' => $recipient->rendered_whatsapp ?: $recipient->rendered_body_text,
                'provider' => $provider,
                'provider_message_id' => $result->providerMessageId,
                'status' => $result->status === 'sent' ? 'sent' : 'accepted',
                'sender_email' => $consentChannel === ConsentChannel::Email ? $this->emailSender->getSenderEmail() : null,
                'sender_name' => $consentChannel === ConsentChannel::Email ? $this->emailSender->getSenderName() : null,
                'sent_at' => now(),
            ]);

            $channelRow->update([
                'delivery_status' => $result->status === 'sent' ? DeliveryStatus::Sent : DeliveryStatus::Accepted,
                'provider' => $provider,
                'provider_message_id' => $result->providerMessageId,
                'message_id' => $communication->id,
                'processed_at' => now(),
                'last_error' => null,
            ]);

            $recipient->update([
                'provider_message_id' => $recipient->provider_message_id ?? $result->providerMessageId,
                'message_id' => $recipient->message_id ?? $communication->id,
                'last_error' => null,
            ]);

            $this->audit->handle(action: 'proposal.channel_delivery_attempted', subject: $channelRow, after: [
                'channel' => $channelRow->channel,
                'attempt' => $attemptNumber,
                'provider' => $provider,
                'status' => $result->status,
            ]);

            $this->rollup->refresh($recipient);
            $this->completeCampaignIfDone($campaign);

            return $result;
        }

        if (($result->status ?? 'failed') === 'unknown') {
            $channelRow->update([
                'delivery_status' => DeliveryStatus::Unknown,
                'provider' => $provider,
                'processed_at' => now(),
                'last_error' => $result->reason,
            ]);
            $this->rollup->refresh($recipient);
            $this->completeCampaignIfDone($campaign);

            $this->audit->handle(action: 'proposal.delivery_reconciled', subject: $channelRow, after: [
                'outcome' => 'unknown', 'reason' => $result->reason,
            ]);

            return $result;
        }

        $retryable = in_array($result->reason, [
            'gmail_rate_limited', 'brevo_rate_limited', 'evolution_rate_limited',
        ], true);

        if ($retryable && $attemptNumber < 3) {
            $channelRow->update([
                'delivery_status' => DeliveryStatus::Scheduled,
                'provider' => $provider,
                'last_error' => $result->reason,
            ]);
            $this->rollup->refresh($recipient);

            return $result;
        }

        $channelRow->update([
            'delivery_status' => DeliveryStatus::Failed,
            'provider' => $provider,
            'processed_at' => now(),
            'last_error' => $result->reason,
        ]);
        $this->rollup->refresh($recipient);

        $this->audit->handle(action: 'proposal.channel_delivery_attempted', subject: $channelRow, after: [
            'channel' => $channelRow->channel,
            'attempt' => $attemptNumber,
            'provider' => $provider,
            'status' => 'failed',
            'reason' => $result->reason,
        ]);

        $this->completeCampaignIfDone($campaign);

        return $result;
    }

    /** @return array{attempt: int|null, reason: string|null} */
    private function claim(Campaign $campaign, CampaignRecipientChannel $channelRow): array
    {
        return DB::transaction(function () use ($campaign, $channelRow): array {
            $lockedCampaign = Campaign::query()->lockForUpdate()->findOrFail($campaign->id);
            $locked = CampaignRecipientChannel::query()->lockForUpdate()->findOrFail($channelRow->id);

            if (! in_array($lockedCampaign->status, [CampaignStatus::Scheduled, CampaignStatus::Running], true)) {
                return ['attempt' => null, 'reason' => 'delivery_not_claimed'];
            }

            if (! $lockedCampaign->isApprovedForCurrentRevision()) {
                return ['attempt' => null, 'reason' => 'delivery_not_claimed'];
            }

            if ($locked->delivery_status !== DeliveryStatus::Scheduled) {
                return ['attempt' => null, 'reason' => 'delivery_not_claimed'];
            }

            if ($locked->scheduled_at === null || $locked->scheduled_at->isFuture()) {
                return ['attempt' => null, 'reason' => 'delivery_not_claimed'];
            }

            $now = now();
            $runtimeSlot = $this->schedule->planChannelRows($lockedCampaign, [$locked->id], $now)[$locked->id] ?? null;

            if ($runtimeSlot === null) {
                return ['attempt' => null, 'reason' => 'no_available_slot'];
            }

            if ($runtimeSlot->greaterThan($now)) {
                $locked->update(['scheduled_at' => $runtimeSlot]);

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

            return ['attempt' => (int) $locked->attempts, 'reason' => null];
        });
    }

    private function hasRenderedContent($recipient, ConsentChannel $channel): bool
    {
        if ($channel === ConsentChannel::Email) {
            return $recipient->rendered_subject !== null && $recipient->rendered_body_text !== null;
        }

        return $recipient->rendered_whatsapp !== null && $recipient->rendered_whatsapp !== '';
    }

    private function skip(CampaignRecipientChannel $channelRow, string $reason, string $auditReason): CommunicationResult
    {
        $channelRow->update([
            'eligibility_status' => EligibilityStatus::Ineligible,
            'eligibility_reason' => $reason,
            'delivery_status' => DeliveryStatus::Skipped,
            'skip_reason' => $reason,
            'processed_at' => now(),
        ]);
        $this->rollup->refresh($channelRow->recipient);

        $this->audit->handle(action: 'proposal.recipient_skipped', subject: $channelRow, after: [
            'reason' => $reason, 'type' => $auditReason,
        ]);

        return CommunicationResult::fail($auditReason);
    }

    private function completeCampaignIfDone(Campaign $campaign): void
    {
        DB::transaction(function () use ($campaign): void {
            $locked = Campaign::query()->lockForUpdate()->find($campaign->id);

            if ($locked === null || in_array($locked->status, [
                CampaignStatus::Paused, CampaignStatus::Cancelled, CampaignStatus::Completed,
            ], true)) {
                return;
            }

            $remaining = $locked->recipientChannels()
                ->whereNotIn('delivery_status', DeliveryStatus::terminal())
                ->count();

            if ($remaining === 0) {
                $locked->update([
                    'status' => CampaignStatus::Completed,
                    'completed_at' => now(),
                ]);
            }
        });
    }
}
