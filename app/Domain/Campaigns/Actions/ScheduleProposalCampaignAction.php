<?php

namespace App\Domain\Campaigns\Actions;

use App\Domain\Campaigns\Enums\CampaignStatus;
use App\Domain\Campaigns\Models\Campaign;
use InvalidArgumentException;

/**
 * Compatibility entry point. New HTTP flow uses PrepareProposalCampaignAction
 * followed by queued resolution/finalization. This action performs the whole
 * start sequence synchronously and is kept for legacy callers/tests.
 */
final class ScheduleProposalCampaignAction
{
    public function __construct(
        private readonly PrepareProposalCampaignAction $prepare,
        private readonly ResolveProposalAudienceAction $resolve,
        private readonly FinalizeProposalCampaignPreparationAction $finalize,
    ) {}

    public function handle(Campaign $campaign, ?int $userId = null): Campaign
    {
        if (! in_array($campaign->status, [CampaignStatus::Approved, CampaignStatus::Preparing], true)) {
            throw new InvalidArgumentException('Campaign harus disetujui sebelum dijadwalkan.');
        }

        if ($campaign->status === CampaignStatus::Approved) {
            $campaign = $this->prepare->handle($campaign, $userId);
        }

        if (! $campaign->audienceIsResolved()) {
            $this->resolve->handle($campaign);
        }

        return $this->finalize->handle($campaign->refresh(), $userId);
    }
}
