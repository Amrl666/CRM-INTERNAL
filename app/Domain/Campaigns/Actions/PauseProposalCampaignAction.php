<?php

namespace App\Domain\Campaigns\Actions;

use App\Domain\Audit\Actions\RecordAuditAction;
use App\Domain\Campaigns\Enums\CampaignStatus;
use App\Domain\Campaigns\Models\Campaign;

final class PauseProposalCampaignAction
{
    public function __construct(private readonly RecordAuditAction $audit) {}

    public function handle(Campaign $campaign, int $userId): Campaign
    {
        if (! in_array($campaign->status, [CampaignStatus::Scheduled, CampaignStatus::Running], true)) {
            throw new \InvalidArgumentException('Hanya campaign terjadwal atau berjalan yang dapat dijeda.');
        }

        $campaign->update([
            'status' => CampaignStatus::Paused,
            'paused_at' => now(),
        ]);

        $this->audit->handle(user: $userId, action: 'proposal_campaign.paused', subject: $campaign);

        return $campaign;
    }
}
