<?php

namespace App\Domain\Campaigns\Actions;

use App\Domain\Audit\Actions\RecordAuditAction;
use App\Domain\Campaigns\Enums\CampaignStatus;
use App\Domain\Campaigns\Models\Campaign;
use Illuminate\Support\Facades\DB;

final class PauseProposalCampaignAction
{
    public function __construct(
        private readonly RecordAuditAction $audit,
    ) {}

    public function handle(Campaign $campaign, int $userId): Campaign
    {
        return DB::transaction(function () use ($campaign, $userId): Campaign {
            $lockedCampaign = Campaign::query()
                ->lockForUpdate()
                ->findOrFail($campaign->id);

            if (! in_array($lockedCampaign->status, [
                CampaignStatus::Scheduled,
                CampaignStatus::Running,
            ], true)) {
                throw new \InvalidArgumentException(
                    'Hanya campaign terjadwal atau berjalan yang dapat dijeda.'
                );
            }

            $lockedCampaign->update([
                'status' => CampaignStatus::Paused,
                'paused_at' => now(),
            ]);

            $this->audit->handle(
                user: $userId,
                action: 'proposal_campaign.paused',
                subject: $lockedCampaign,
            );

            return $lockedCampaign->refresh();
        });
    }
}
