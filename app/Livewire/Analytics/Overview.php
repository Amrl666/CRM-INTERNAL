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
        $this->authorize('analytics.view');
    }

    public function render()
    {
        $user = auth()->user();

        $leadScope = $user->can('leads.view')
            ? Lead::query()->visibleTo($user)
            : Lead::query();

        $dealScope = $user->can('deals.view')
            ? Deal::query()->visibleTo($user)
            : Deal::query();

        $activeLeads = (clone $leadScope)->active();
        $visibleDeals = $dealScope;

        return view('livewire.analytics.overview', [
            'totalLeads' => (clone $activeLeads)->count(),
            'qualifiedLeads' => (clone $activeLeads)->whereNotNull('qualified_at')->count(),
            'leadsByStatus' => (clone $activeLeads)
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->orderByDesc('total')
                ->pluck('total', 'status'),
            'openDealsValue' => (float) (clone $visibleDeals)->where('status', DealStatus::Open)->sum('value'),
            'wonDealsValue' => (float) (clone $visibleDeals)->where('status', DealStatus::Won)->sum('value'),
            'wonDealsThisMonth' => (clone $visibleDeals)->where('status', DealStatus::Won)
                ->whereBetween('closed_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->count(),
            'activitiesLast7Days' => Activity::query()
                ->where('occurred_at', '>=', now()->subDays(7))
                ->when(
                    $user->can('leads.view'),
                    fn ($query) => $query->whereHas('lead', fn ($leadQuery) => $leadQuery->visibleTo($user)),
                )
                ->count(),
            'recentActivities' => Activity::query()
                ->with('lead.company')
                ->when(
                    $user->can('leads.view'),
                    fn ($query) => $query->whereHas('lead', fn ($leadQuery) => $leadQuery->visibleTo($user)),
                )
                ->latest('occurred_at')
                ->limit(10)
                ->get(),
            'labels' => collect(LeadStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()]),
        ]);
    }
}
