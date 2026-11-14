<?php

namespace App\Livewire\Dashboard;

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

    public function mount(): void
    {
        $this->totalLeads = Lead::query()->count();
        $this->activeLeads = Lead::query()->whereNull('archived_at')->count();
        $this->qualifiedLeads = Lead::query()->where('status', 'QUALIFIED')->count();
        $this->todayTasks = Task::query()->whereDate('due_at', today())->where('status', '!=', 'completed')->count();
        $this->openDeals = Deal::query()->whereNull('closed_at')->count();
        $this->wonDeals = Deal::query()->where('status', 'won')->count();

        $this->leadStatusCounts = Lead::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $this->dealStageCounts = Deal::query()
            ->join('pipeline_stages', 'deals.stage_id', '=', 'pipeline_stages.id')
            ->selectRaw('pipeline_stages.name, count(*) as total')
            ->groupBy('pipeline_stages.name')
            ->pluck('total', 'name')
            ->toArray();
    }

    public function render()
    {
        return view('livewire.dashboard.index');
    }
}
