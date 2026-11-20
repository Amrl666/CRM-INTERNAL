<?php

namespace App\Domain\Campaigns\Actions;

use App\Domain\Audit\Actions\RecordAuditAction;
use App\Domain\Campaigns\Enums\CampaignStatus;
use App\Domain\Campaigns\Enums\DeliveryStatus;
use App\Domain\Campaigns\Enums\EligibilityStatus;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Models\CampaignRecipient;
use App\Domain\Campaigns\Models\CampaignRecipientChannel;
use App\Domain\Campaigns\Services\PriorOutreachGuard;
use App\Domain\Consent\Services\CommunicationEligibility;
use InvalidArgumentException;

final class RefreshProposalEligibilityAction
{
    public function __construct(
        private readonly CommunicationEligibility $eligibility,
        private readonly PriorOutreachGuard $prior,
        private readonly RecordAuditAction $audit,
    ) {}

    /** @return array{eligible: int, ineligible: int, reasons: array<string, int>} */
    public function handle(Campaign $campaign, ?int $userId = null): array
    {
        if (! in_array($campaign->status, [
            CampaignStatus::Draft, CampaignStatus::Review, CampaignStatus::Preparing,
        ], true)) {
            throw new InvalidArgumentException('Eligibility hanya dapat diperiksa ulang sebelum pengiriman dimulai.');
        }

        $eligible = 0;
        $ineligible = 0;
        $reasons = [];

        $hasChannels = $campaign->recipientChannels()->exists();

        if ($hasChannels) {
            $campaign->recipientChannels()
                ->with(['recipient.contact.company', 'recipient.lead'])
                ->each(function (CampaignRecipientChannel $row) use ($campaign, &$eligible, &$ineligible, &$reasons): void {
                    $channel = $row->channelEnum();
                    $recipient = $row->recipient;
                    $reasonsForRow = $this->eligibility->check(
                        $recipient->contact,
                        $channel->consentChannel(),
                        $recipient->lead,
                    );

                    $duplicates = $this->prior->blockedContacts(
                        [$recipient->contact_id],
                        $channel->consentChannel(),
                        $campaign->id,
                    );

                    if (isset($duplicates[$recipient->contact_id])) {
                        $reasonsForRow[] = $duplicates[$recipient->contact_id];
                    }

                    if ($reasonsForRow === []) {
                        $eligible++;
                        $row->update([
                            'eligibility_status' => EligibilityStatus::Eligible,
                            'eligibility_reason' => null,
                        ]);

                        return;
                    }

                    $ineligible++;
                    $message = implode(' ', $reasonsForRow);
                    $reasons[$row->channel.': '.$message] = ($reasons[$row->channel.': '.$message] ?? 0) + 1;
                    $updates = [
                        'eligibility_status' => EligibilityStatus::Ineligible,
                        'eligibility_reason' => $message,
                        'skip_reason' => $message,
                    ];

                    if (! $row->delivery_status?->isTerminal()) {
                        $updates['delivery_status'] = DeliveryStatus::Skipped;
                    }

                    $row->update($updates);
                });
        } else {
            if ($campaign->channel === null) {
                throw new InvalidArgumentException('Campaign proposal belum memiliki channel.');
            }

            $campaign->recipients()
                ->with(['contact.company', 'lead'])
                ->each(function (CampaignRecipient $recipient) use ($campaign, &$eligible, &$ineligible, &$reasons): void {
                    $reason = $this->eligibility->check(
                        $recipient->contact,
                        $campaign->channel->consentChannel(),
                        $recipient->lead,
                    );

                    if ($reason === []) {
                        $eligible++;
                        $recipient->update([
                            'eligibility_status' => EligibilityStatus::Eligible,
                            'eligibility_reason' => null,
                        ]);

                        return;
                    }

                    $ineligible++;
                    $message = implode(' ', $reason);
                    $reasons[$message] = ($reasons[$message] ?? 0) + 1;
                    $recipient->update([
                        'eligibility_status' => EligibilityStatus::Ineligible,
                        'eligibility_reason' => $message,
                        'skip_reason' => $message,
                    ]);

                    if (in_array($recipient->delivery_status, [
                        DeliveryStatus::Pending, DeliveryStatus::Ready, DeliveryStatus::Skipped,
                    ], true)) {
                        $recipient->update(['delivery_status' => DeliveryStatus::Skipped]);
                    }
                });
        }

        $this->audit->handle(user: $userId, action: 'proposal_campaign.eligibility_checked', subject: $campaign, after: [
            'eligible' => $eligible, 'ineligible' => $ineligible, 'reasons' => $reasons,
        ]);

        return ['eligible' => $eligible, 'ineligible' => $ineligible, 'reasons' => $reasons];
    }
}
