<?php

namespace App\Livewire\Proposals;

use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Models\CampaignRecipient;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.sidebar', ['heading' => 'Outreach'])]
class Index extends Component
{
    use WithPagination;

    public function mount(): void
    {
        $this->authorize('viewAny', Campaign::class);
    }

    public function render()
    {
        $stats = [
            'scheduled' => CampaignRecipient::query()->where('delivery_status', 'SCHEDULED')->count(),
            'processing' => CampaignRecipient::query()->where('delivery_status', 'PROCESSING')->count(),
            'sent' => CampaignRecipient::query()
                ->whereIn('delivery_status', ['SENT', 'ACCEPTED', 'DELIVERED'])
                ->count(),
            'failed' => CampaignRecipient::query()->where('delivery_status', 'FAILED')->count(),
            'unknown' => CampaignRecipient::query()->where('delivery_status', 'UNKNOWN')->count(),
            'ineligible' => CampaignRecipient::query()
                ->where('eligibility_status', 'INELIGIBLE')
                ->count(),
        ];

        return view('livewire.proposals.index', [
            'campaigns' => Campaign::query()
                ->where('type', 'proposal')
                ->with(['proposalTemplate'])
                ->withCount('recipients')
                ->latest()
                ->paginate(15),
            'monitoring' => $stats,
        ]);
    }
}
