<?php

namespace App\Livewire\Dashboard;

use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.sidebar', ['heading' => 'Dashboard'])]
class Index extends Component
{
    public function render()
    {
        return view('livewire.dashboard.index');
    }
}
