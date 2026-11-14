<?php

namespace App\Livewire\Settings;

use App\Domain\Consent\Enums\Channel;
use App\Domain\Consent\Models\SuppressionEntry;
use App\Domain\Consent\Services\SuppressionService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.sidebar', ['heading' => 'Daftar Suppression'])]
class Suppression extends Component
{
    public string $channel = Channel::Email->value;

    public string $address = '';

    public string $reason = '';

    /** @var array{id: string, channel: string, normalized_address: string, reason?: string}[] */
    public array $entries = [];

    public function mount(): void
    {
        $this->authorize('settings.manage');
        $this->refresh();
    }

    public function add(SuppressionService $service): void
    {
        $this->validate([
            'address' => ['required', 'string'],
            'reason' => ['nullable', 'string', 'max:200'],
        ]);

        if (trim($this->address) === '') {
            return;
        }

        $service->suppress(
            Channel::from($this->channel),
            trim($this->address),
            trim($this->reason) ?: null,
            auth()->id(),
        );

        $this->address = '';
        $this->reason = '';
        $this->refresh();
    }

    private function refresh(): void
    {
        $this->entries = SuppressionEntry::query()
            ->orderByDesc('created_at')
            ->limit(200)
            ->get(['id', 'channel', 'normalized_address', 'reason'])
            ->map(fn ($row) => [
                'id' => (string) $row->id,
                'channel' => $row->channel,
                'normalized_address' => $row->normalized_address,
                'reason' => $row->reason ?? '',
            ])
            ->toArray();
    }

    public function render()
    {
        return view('livewire.settings.suppression');
    }
}
