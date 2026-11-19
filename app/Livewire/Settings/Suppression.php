<?php

namespace App\Livewire\Settings;

use App\Domain\Consent\Enums\Channel;
use App\Domain\Consent\Models\SuppressionEntry;
use App\Domain\Consent\Services\SuppressionService;
use App\Support\PhoneNormalizer;
use Illuminate\Validation\Rule;
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
        $this->authorize('settings.manage');

        $channel = Channel::tryFrom($this->channel);

        if ($channel === null) {
            $this->addError('channel', 'Kanal tidak dikenal.');

            return;
        }

        $this->validate([
            'channel' => ['required', Rule::enum(Channel::class)],
            'address' => ['required', 'string', 'max:320'],
            'reason' => ['nullable', 'string', 'max:200'],
        ]);

        $address = trim($this->address);

        if ($channel === Channel::Email && filter_var($address, FILTER_VALIDATE_EMAIL) === false) {
            $this->addError('address', 'Format email tidak valid.');

            return;
        }

        if ($channel === Channel::WhatsApp && PhoneNormalizer::toE164($address) === null) {
            $this->addError('address', 'Nomor WhatsApp tidak valid. Gunakan format Indonesia, misalnya 0812...');

            return;
        }

        try {
            $entry = $service->suppress(
                $channel,
                $address,
                trim($this->reason) ?: null,
                auth()->id(),
            );

            $this->address = '';
            $this->reason = '';
            $this->resetValidation();
            $this->refresh();

            $this->dispatch(
                'notify',
                message: $entry->wasRecentlyCreated ? 'Suppression berhasil ditambahkan.' : 'Alamat tersebut sudah ada di daftar suppression.',
                type: $entry->wasRecentlyCreated ? 'success' : 'info',
            );
        } catch (\Throwable $e) {
            report($e);
            $this->addError('address', 'Suppression tidak dapat disimpan. Silakan coba lagi.');
        }
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
