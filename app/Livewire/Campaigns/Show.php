<?php

namespace App\Livewire\Campaigns;

use App\Domain\Campaigns\Actions\ApproveCampaignAction;
use App\Domain\Campaigns\Actions\RequestCampaignApprovalAction;
use App\Domain\Campaigns\Enums\CampaignStatus;
use App\Domain\Campaigns\Models\Campaign;
use App\Jobs\GenerateCampaignDraftsJob;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.sidebar', ['heading' => 'Campaign'])]
class Show extends Component
{
    public Campaign $campaign;

    public function mount(Campaign $campaign): void
    {
        $this->authorize('view', $campaign);
        $this->campaign = $campaign;
    }

    public function requestApproval(RequestCampaignApprovalAction $action): void
    {
        $this->authorize('create', Campaign::class);

        try {
            $action->handle($this->campaign, auth()->id());
        } catch (\InvalidArgumentException $e) {
            $this->addError('campaign', $e->getMessage());
        }

        $this->campaign->refresh();
    }

    public function approve(ApproveCampaignAction $action): void
    {
        $this->authorize('approve', Campaign::class);

        try {
            $action->handle($this->campaign, auth()->id());
        } catch (\InvalidArgumentException $e) {
            $this->addError('campaign', $e->getMessage());
        }

        $this->campaign->refresh();
    }

    public function generateDrafts(): void
    {
        $this->authorize('send', Campaign::class);

        if ($this->campaign->status !== CampaignStatus::Approved) {
            $this->addError('campaign', 'Campaign harus disetujui terlebih dahulu.');

            return;
        }

        $this->campaign->update(['status' => CampaignStatus::Sending]);
        GenerateCampaignDraftsJob::dispatch($this->campaign->id);
    }

    public function render()
    {
        return view('livewire.campaigns.show', [
            'recipients' => $this->campaign->recipients()->with('contact.company')->get(),
            'status' => $this->campaign->status,
        ]);
    }
}
