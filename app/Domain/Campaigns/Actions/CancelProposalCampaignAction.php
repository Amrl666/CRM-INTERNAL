<?php

namespace App\Domain\Campaigns\Actions;

use App\Domain\Audit\Actions\RecordAuditAction;
use App\Domain\Campaigns\Enums\CampaignStatus;
use App\Domain\Campaigns\Enums\DeliveryStatus;
use App\Domain\Campaigns\Models\Campaign;

final class CancelProposalCampaignAction
{
    public function __construct(private readonly RecordAuditAction $audit) {}

    public function handle(Campaign $campaign, int $userId, ?string $reason = null): Campaign
    {
        if (in_array($campaign->status, [CampaignStatus::Completed, CampaignStatus::Cancelled], true)) {
            throw new \InvalidArgumentException('Campaign sudah selesai atau dibatalkan.');
        }

        $campaign->recipients()
            ->whereNotIn('delivery_status', DeliveryStatus::terminal())
            ->update([
                'delivery_status' => DeliveryStatus::Cancelled,
                'cancelled_at' => now(),
                'skip_reason' => $reason ?: 'Campaign dibatalkan.',
                'updated_at' => now(),
            ]);

        $campaign->update([
            'status' => CampaignStatus::Cancelled,
            'cancelled_at' => now(),
            'last_error' => $reason,
        ]);

        $this->audit->handle(user: $userId, action: 'proposal_campaign.cancelled', subject: $campaign, after: [
            'reason' => $reason,
        ]);

        return $campaign;
    }
}
