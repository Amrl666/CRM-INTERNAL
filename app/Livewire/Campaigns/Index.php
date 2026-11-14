<?php

namespace App\Livewire\Campaigns;

use App\Domain\Campaigns\Models\Campaign;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.sidebar', ['heading' => 'Campaign'])]
class Index extends Component
{
    use WithPagination;

    public function mount(): void
    {
        $this->authorize('viewAny', Campaign::class);
    }

    public function render()
    {
        return view('livewire.campaigns.index', [
            'campaigns' => Campaign::with('template')->latest()->paginate(15),
        ]);
    }
}
