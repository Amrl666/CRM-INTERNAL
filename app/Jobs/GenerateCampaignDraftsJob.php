<?php

namespace App\Jobs;

use App\Domain\Campaigns\Actions\GenerateCampaignDraftsAction;
use App\Domain\Campaigns\Models\Campaign;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateCampaignDraftsJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public function __construct(public readonly string $campaignId)
    {
        $this->onQueue('campaigns');
    }

    public function handle(GenerateCampaignDraftsAction $action): void
    {
        $campaign = Campaign::findOrFail($this->campaignId);

        $action->handle($campaign);
    }
}
