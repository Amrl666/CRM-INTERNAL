<?php

namespace App\Livewire\Proposals;

use App\Domain\Campaigns\Models\Campaign;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.sidebar', ['heading' => 'Proposal Campaign'])]
class Index extends Component
{
    use WithPagination;

    public function mount(): void
    {
        $this->authorize('viewAny', Campaign::class);
    }

    public function render()
    {
        return view('livewire.proposals.index', [
            'campaigns' => Campaign::query()
                ->where('type', 'proposal')
                ->with(['proposalTemplate'])
                ->withCount('recipients')
                ->latest()
                ->paginate(15),
        ]);
    }
}
