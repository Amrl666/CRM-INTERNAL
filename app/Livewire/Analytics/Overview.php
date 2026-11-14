<?php

namespace App\Livewire\Analytics;

use App\Domain\Deals\Enums\DealStatus;
use App\Domain\Deals\Models\Deal;
use App\Domain\Leads\Enums\LeadStatus;
use App\Domain\Leads\Models\Activity;
use App\Domain\Leads\Models\Lead;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.sidebar', ['heading' => 'Analitik'])]
class Overview extends Component
{
    public function mount(): void
    {
        $this->authorize('viewAny', Deal::class);
    }

    public function render()
    {
        $activeLeads = Lead::query()->visibleTo(auth()->user())->active();

        return view('livewire.analytics.overview', [
            'totalLeads' => (clone $activeLeads)->count(),
            'qualifiedLeads' => (clone $activeLeads)->whereNotNull('qualified_at')->count(),
            'leadsByStatus' => (clone $activeLeads)
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->orderByDesc('total')
                ->pluck('total', 'status'),
            'openDealsValue' => (float) Deal::where('status', DealStatus::Open)->sum('value'),
            'wonDealsValue' => (float) Deal::where('status', DealStatus::Won)->sum('value'),
            'wonDealsThisMonth' => Deal::where('status', DealStatus::Won)
                ->whereBetween('closed_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->count(),
            'activitiesLast7Days' => Activity::where('occurred_at', '>=', now()->subDays(7))->count(),
            'recentActivities' => Activity::with('lead.company')->latest('occurred_at')->limit(10)->get(),
            'labels' => collect(LeadStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()]),
        ]);
    }
}
