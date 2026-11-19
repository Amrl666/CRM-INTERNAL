<div class="space-y-6">
    <div class="flex items-center justify-between gap-4">
        <div>
            <h2 class="text-lg font-semibold text-slate-900">Buat campaign</h2>
            <p class="mt-1 text-sm text-slate-500">Pilih template email dan penerima. Campaign disimpan sebagai draft untuk ditinjau.</p>
        </div>
        <a href="{{ route('campaigns.index') }}" wire:navigate class="shrink-0 text-sm text-slate-600 hover:text-slate-900">Kembali</a>
    </div>

    @error('form')
        <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{{ $message }}</div>
    @enderror

    <form wire:submit="save" class="bg-white border border-slate-200 rounded-lg p-6 space-y-5">
        <div>
            <label for="campaign-name" class="block text-sm font-medium text-slate-700">
                Nama campaign <span class="text-red-600" aria-hidden="true">*</span>
            </label>
            <input id="campaign-name" type="text" wire:model="name" required maxlength="255" placeholder="Contoh: Follow-up Q1 2027"
                @error('name') aria-invalid="true" aria-describedby="campaign-name-error" @enderror
                class="mt-1 block w-full rounded-md border px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 @error('name') border-red-400 @else border-slate-300 @enderror"/>
            @error('name')
                <p id="campaign-name-error" class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="campaign-template" class="block text-sm font-medium text-slate-700">
                Template email <span class="text-red-600" aria-hidden="true">*</span>
            </label>
            <select id="campaign-template" wire:model="template_id" required
                @error('template_id') aria-invalid="true" aria-describedby="campaign-template-error" @enderror
                class="mt-1 block w-full rounded-md border px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 @error('template_id') border-red-400 @else border-slate-300 @enderror">
                <option value="">Pilih template</option>
                @foreach ($templates as $template)
                    <option value="{{ $template->id }}">{{ $template->name }} — {{ $template->subject }}</option>
                @endforeach
            </select>
            @error('template_id')
                <p id="campaign-template-error" class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <span class="block text-sm font-medium text-slate-700">
                Penerima (lead aktif) <span class="text-red-600" aria-hidden="true">*</span>
            </span>

            @if ($leads->isEmpty())
                <div class="mt-2 rounded-md border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-center text-sm text-slate-500">
                    Belum ada lead aktif dengan kontak. Tambahkan atau lengkapi data lead terlebih dahulu.
                </div>
            @else
                <div class="mt-2 max-h-72 space-y-1 overflow-y-auto rounded-md border border-slate-300 bg-white p-2"
                    role="group" aria-label="Pilih penerima campaign">
                    @foreach ($leads as $lead)
                        <label for="campaign-lead-{{ $lead->id }}"
                            class="flex cursor-pointer items-start gap-3 rounded-md px-2 py-2 text-sm hover:bg-slate-50">
                            <input id="campaign-lead-{{ $lead->id }}" type="checkbox" wire:model="leadIds" value="{{ $lead->id }}"
                                class="mt-0.5 rounded border-slate-300 text-blue-600 focus:ring-blue-500"/>
                            <span class="min-w-0">
                                <span class="block font-medium text-slate-800">{{ $lead->company->name }}</span>
                                <span class="block text-xs text-slate-500">
                                    {{ $lead->contact->full_name }} · {{ $lead->contact->normalized_email ?? 'tanpa email' }}
                                </span>
                            </span>
                        </label>
                    @endforeach
                </div>
            @endif

            <p class="mt-1 text-xs text-slate-500">Hanya lead aktif yang memiliki kontak dan email yang dapat dipilih.</p>
            @error('leadIds')
                <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
            @enderror
            @error('leadIds.*')
                <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex items-center justify-end gap-3 border-t border-slate-100 pt-4">
            <a href="{{ route('campaigns.index') }}" wire:navigate
                class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                Batal
            </a>
            <button type="submit" wire:loading.attr="disabled" wire:target="save" @disabled($templates->isEmpty() || $leads->isEmpty())
                class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60">
                <span wire:loading.remove wire:target="save">Simpan draft</span>
                <span wire:loading wire:target="save">Menyimpan...</span>
            </button>
        </div>
    </form>
</div>
