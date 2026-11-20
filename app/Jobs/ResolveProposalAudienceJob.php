<?php

namespace App\Jobs;

use App\Domain\Campaigns\Actions\ResolveProposalAudienceAction;
use App\Domain\Campaigns\Enums\CampaignStatus;
use App\Domain\Campaigns\Models\Campaign;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ResolveProposalAudienceJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;

    public int $tries = 3;

    public function __construct(public readonly string $campaignId)
    {
        $this->onQueue('campaigns');
    }

    public function handle(ResolveProposalAudienceAction $action): void
    {
        $campaign = Campaign::find($this->campaignId);

        if ($campaign === null || $campaign->status !== CampaignStatus::Preparing) {
            return;
        }

        $hasMore = $action->handleChunk($campaign, max(1, (int) config('proposals.audience.chunk_size', 500)));

        if ($hasMore) {
            self::dispatch($this->campaignId)
                ->onQueue('campaigns')
                ->afterCommit();
        }
    }
}
