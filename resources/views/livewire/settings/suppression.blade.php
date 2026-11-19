<div class="space-y-6">
    <x-page-header title="Suppression global" description="Alamat yang telah diminta berhenti komunikasi akan otomatis diblokir sebelum dikirim.">

        <x-slot name="actions">
            <a href="{{ route('settings.index') }}" wire:navigate class="text-sm text-slate-600 hover:text-slate-900">Kembali</a>
        </x-slot>
    </x-page-header>

    <x-card>
        <h2 class="mb-1 text-sm font-semibold text-slate-900">Tambah suppression</h2>
        <p class="mb-4 text-sm text-slate-500">Alamat akan dinormalisasi sebelum disimpan agar konsisten saat pengecekan pengiriman.</p>

        <form wire:submit="add" class="space-y-3">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-4">
                <div class="sm:col-span-1">
                    <label for="channel" class="mb-1 block text-xs font-medium text-slate-700">Kanal</label>
                    <select id="channel" wire:model.live="channel"
                        class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                        <option value="EMAIL">Email</option>
                        <option value="WHATSAPP">WhatsApp</option>
                    </select>
                    @error('channel')
                        <p class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p>
                    @enderror
                </div>
                <div class="sm:col-span-2">
                    <label for="address" class="mb-1 block text-xs font-medium text-slate-700">
                        Alamat <span class="text-red-600" aria-hidden="true">*</span>
                    </label>
                    <input id="address" type="text" wire:model="address" required maxlength="320"
                        placeholder="{{ $channel === 'EMAIL' ? 'email@contoh.com' : '+62812...' }}"
                        @error('address') aria-invalid="true" aria-describedby="address-error" @enderror
                        class="w-full rounded-md border px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 @error('address') border-red-400 @else border-slate-300 @enderror"/>
                    @error('address')
                        <p id="address-error" class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p>
                    @enderror
                </div>
                <div class="sm:col-span-1">
                    <label for="reason" class="mb-1 block text-xs font-medium text-slate-700">Alasan</label>
                    <input id="reason" type="text" wire:model="reason" maxlength="200" placeholder="opsional"
                        class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"/>
                    @error('reason')
                        <p class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p>
                    @enderror
                </div>
            </div>
            <div class="flex items-center justify-end gap-3">
                <button type="submit" wire:loading.attr="disabled" wire:target="add" data-testid="btn-add-suppression"
                    class="rounded-md bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-60">
                    <span wire:loading.remove wire:target="add">Simpan suppression</span>
                    <span wire:loading wire:target="add">Menyimpan...</span>
                </button>
            </div>
        </form>
    </x-card>

    <x-card>
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-sm font-semibold text-slate-900">Data saat ini</h2>
            <span class="text-xs text-slate-500">{{ count($entries) }} entri</span>
        </div>

        @if (count($entries) === 0)
            <div class="mt-4 rounded-md border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center">
                <p class="text-sm font-medium text-slate-700">Belum ada suppression.</p>
                <p class="mt-1 text-sm text-slate-500">Tambahkan alamat yang meminta berhenti dihubungi melalui formulir di atas.</p>
            </div>
        @else
            <ul class="mt-2 divide-y divide-slate-200 text-sm">
                @foreach ($entries as $entry)
                    <li class="flex items-start justify-between gap-4 py-3" wire:key="suppression-{{ $entry['id'] }}">
                        <div class="min-w-0 space-y-1">
                            <div class="truncate font-medium text-slate-900">{{ $entry['normalized_address'] }}</div>
                            <div class="text-slate-500">
                                {{ $entry['channel'] === 'WHATSAPP' ? 'WhatsApp' : 'Email' }}
                                @if ($entry['reason'] !== '') · {{ $entry['reason'] }} @endif
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-card>
</div>
