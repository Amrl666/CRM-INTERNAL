<?php

namespace App\Livewire\Dashboard;

use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Models\CampaignRecipient;
use App\Domain\Campaigns\Models\CampaignRecipientChannel;
use App\Domain\Deals\Models\Deal;
use App\Domain\Leads\Models\Lead;
use App\Domain\Tasks\Models\Task;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.sidebar', ['heading' => 'Dashboard'])]
class Index extends Component
{
    public int $totalLeads = 0;

    public int $activeLeads = 0;

    public int $todayTasks = 0;

    public int $openDeals = 0;

    public int $qualifiedLeads = 0;

    public int $wonDeals = 0;

    public array $leadStatusCounts = [];

    public array $dealStageCounts = [];

    public int $outreachActiveCampaigns = 0;

    public int $outreachScheduled = 0;

    public int $outreachSent = 0;

    public int $outreachFailed = 0;

    public function mount(): void
    {
        $user = auth()->user();
        $leads = Lead::query()->visibleTo($user);
        $deals = Deal::query()->visibleTo($user);

        $this->totalLeads = (clone $leads)->count();
        $this->activeLeads = (clone $leads)->whereNull('archived_at')->count();
        $this->qualifiedLeads = (clone $leads)->where('status', 'QUALIFIED')->count();
        $this->todayTasks = Task::query()
            ->whereDate('due_at', today())
            ->where('status', '!=', 'completed')
            ->where(function ($query) use ($user) {
                $query->whereNull('assigned_to')->orWhere('assigned_to', $user->id);
            })
            ->count();
        $this->openDeals = (clone $deals)->whereNull('closed_at')->count();
        $this->wonDeals = (clone $deals)->where('status', 'won')->count();

        $this->leadStatusCounts = (clone $leads)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $this->dealStageCounts = (clone $deals)
            ->join('pipeline_stages', 'deals.stage_id', '=', 'pipeline_stages.id')
            ->selectRaw('pipeline_stages.name, count(*) as total')
            ->groupBy('pipeline_stages.name')
            ->pluck('total', 'name')
            ->toArray();

        $this->outreachActiveCampaigns = Campaign::query()
            ->where('type', 'proposal')
            ->whereIn('status', ['preparing', 'scheduled', 'running'])
            ->count();

        $channelSource = CampaignRecipientChannel::query();
        $hasChannelRows = (clone $channelSource)->exists();

        if ($hasChannelRows) {
            $this->outreachScheduled = (clone $channelSource)->where('delivery_status', 'SCHEDULED')->count();
            $this->outreachSent = (clone $channelSource)->whereIn('delivery_status', ['SENT', 'ACCEPTED', 'DELIVERED'])->count();
            $this->outreachFailed = (clone $channelSource)->where('delivery_status', 'FAILED')->count();
        } else {
            $this->outreachScheduled = CampaignRecipient::query()->where('delivery_status', 'SCHEDULED')->count();
            $this->outreachSent = CampaignRecipient::query()->whereIn('delivery_status', ['SENT', 'ACCEPTED', 'DELIVERED'])->count();
            $this->outreachFailed = CampaignRecipient::query()->where('delivery_status', 'FAILED')->count();
        }
    }

    public function render()
    {
        return view('livewire.dashboard.index');
    }
}
