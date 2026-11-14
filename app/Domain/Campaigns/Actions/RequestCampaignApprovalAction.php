<?php

namespace App\Domain\Campaigns\Actions;

use App\Domain\Audit\Actions\RecordAuditAction;
use App\Domain\Campaigns\Enums\CampaignStatus;
use App\Domain\Campaigns\Models\Campaign;

final class RequestCampaignApprovalAction
{
    public function __construct(private readonly RecordAuditAction $audit) {}

    public function handle(Campaign $campaign, ?int $userId = null): Campaign
    {
        if ($campaign->status !== CampaignStatus::Draft) {
            throw new \InvalidArgumentException('Hanya campaign draft yang dapat diajukan approval.');
        }

        if ($campaign->recipients()->count() === 0) {
            throw new \InvalidArgumentException('Campaign tanpa penerima tidak dapat diajukan.');
        }

        $campaign->update(['status' => CampaignStatus::PendingApproval]);

        $this->audit->handle(user: $userId, action: 'campaign.approval_requested', subject: $campaign);

        return $campaign;
    }
}
