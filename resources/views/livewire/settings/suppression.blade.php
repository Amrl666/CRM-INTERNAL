<div class="max-w-3xl mx-auto space-y-6">
    <x-page-header title="Suppression global" description="Alamat yang telah diminta berhenti komunikasi akan otomatis diblokir sebelum dikirim." :actions="null">
        <x-slot name="actions">
            <a href="{{ route('settings.index') }}" wire:navigate class="text-sm text-slate-600 hover:text-slate-900">Kembali</a>
        </x-slot>
    </x-page-header>

    <x-card>
        <h2 class="mb-4 text-sm font-medium text-slate-700">Tambah suppression</h2>
        <form wire:submit="add" class="space-y-3">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-4">
                <div class="sm:col-span-1">
                    <label for="channel" class="mb-1 block text-xs font-medium text-slate-700">Kanal</label>
                    <select id="channel" wire:model.live="channel" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <option value="EMAIL">Email</option>
                        <option value="WHATSAPP">WhatsApp</option>
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label for="address" class="mb-1 block text-xs font-medium text-slate-700">Alamat</label>
                    <input id="address" type="text" wire:model.live.debounce.300ms="address" placeholder="email@contoh.com atau +628..." class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"/>
                </div>
                <div class="sm:col-span-1">
                    <label for="reason" class="mb-1 block text-xs font-medium text-slate-700">Alasan</label>
                    <input type="text" wire:model.live.debounce.300ms="reason" placeholder="opsional" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"/>
                </div>
            </div>
            <div class="flex items-center justify-end gap-3">
                <button type="submit" class="rounded-md bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800" data-testid="btn-add-suppression">Simpan suppression</button>
            </div>
        </form>
    </x-card>

    <x-card>
        <h2 class="mb-4 text-sm font-medium text-slate-700">Data saat ini</h2>
        @if (count($entries) === 0)
            <p class="text-sm text-slate-500">Belum ada suppression.</p>
        @else
            <ul class="divide-y divide-slate-200 text-sm">
                @foreach ($entries as $entry)
                    <li class="flex items-start justify-between py-3">
                        <div class="space-y-1">
                            <div class="font-medium text-slate-900">{{ $entry['normalized_address'] }}</div>
                            <div class="text-slate-500">{{ $entry['channel'] }}@if ($entry['reason'] !== '') · {{ $entry['reason'] }}@endif</div>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-card>
</div>
