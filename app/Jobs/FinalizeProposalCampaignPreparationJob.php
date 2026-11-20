<?php

namespace App\Jobs;

use App\Domain\Campaigns\Actions\FinalizeProposalCampaignPreparationAction;
use App\Domain\Campaigns\Models\Campaign;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class FinalizeProposalCampaignPreparationJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public int $tries = 3;

    public function __construct(public readonly string $campaignId)
    {
        $this->onQueue('campaigns');
    }

    public function handle(FinalizeProposalCampaignPreparationAction $action): void
    {
        $campaign = Campaign::find($this->campaignId);

        if ($campaign === null) {
            return;
        }

        $action->handle($campaign);
    }
}
