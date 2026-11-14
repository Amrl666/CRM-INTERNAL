<?php

namespace App\Domain\Campaigns\Actions;

use App\Domain\Audit\Actions\RecordAuditAction;
use App\Domain\Campaigns\Enums\DeliveryStatus;
use App\Domain\Campaigns\Enums\EligibilityStatus;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Models\CampaignRecipient;
use App\Domain\Consent\Services\CommunicationEligibility;

final class RefreshProposalEligibilityAction
{
    public function __construct(
        private readonly CommunicationEligibility $eligibility,
        private readonly RecordAuditAction $audit,
    ) {}

    /** @return array{eligible: int, ineligible: int, reasons: array<string, int>} */
    public function handle(Campaign $campaign, ?int $userId = null): array
    {
        $eligible = 0;
        $ineligible = 0;
        $reasons = [];

        if ($campaign->channel === null) {
            throw new \InvalidArgumentException('Campaign proposal belum memiliki channel.');
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

                if (! in_array($recipient->delivery_status, [DeliveryStatus::Sent, DeliveryStatus::Accepted, DeliveryStatus::Delivered], true)) {
                    $recipient->update(['delivery_status' => DeliveryStatus::Skipped]);
                }
            });

        $this->audit->handle(user: $userId, action: 'proposal_campaign.eligibility_checked', subject: $campaign, after: [
            'eligible' => $eligible,
            'ineligible' => $ineligible,
            'reasons' => $reasons,
        ]);

        return ['eligible' => $eligible, 'ineligible' => $ineligible, 'reasons' => $reasons];
    }
}
