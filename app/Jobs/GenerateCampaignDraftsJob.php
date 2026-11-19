<?php

namespace App\Jobs;

use App\Domain\Campaigns\Actions\GenerateCampaignDraftsAction;
use App\Domain\Campaigns\Exceptions\CampaignNotReadyForDraftGeneration;
use App\Domain\Campaigns\Models\Campaign;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateCampaignDraftsJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $uniqueFor = 3600;

    public function __construct(public readonly string $campaignId)
    {
        $this->onQueue('campaigns');
    }

    public function uniqueId(): string
    {
        return 'generate-campaign-drafts:'.$this->campaignId;
    }

    public function handle(GenerateCampaignDraftsAction $action): void
    {
        $campaign = Campaign::findOrFail($this->campaignId);

        try {
            $action->handle($campaign);
        } catch (CampaignNotReadyForDraftGeneration) {
            // Stale atau duplicate job: tidak perlu diproses ulang.
            return;
        }
    }
}
