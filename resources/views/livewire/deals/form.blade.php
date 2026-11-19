<div class="space-y-6">
    <div class="flex items-center justify-between gap-4">
        <div>
            <h2 class="text-lg font-semibold text-slate-900">{{ $dealId ? 'Ubah deal' : 'Tambah deal' }}</h2>
            <p class="mt-1 text-sm text-slate-500">
                {{ $dealId ? 'Perbarui nilai, jadwal closing, atau stage deal.' : 'Catat peluang penjualan baru dan masukkan ke pipeline.' }}
            </p>
        </div>
        <a href="{{ route('deals.index') }}" wire:navigate class="shrink-0 text-sm text-slate-600 hover:text-slate-900">Kembali</a>
    </div>

    @error('form')
        <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{{ $message }}</div>
    @enderror

    @if ($companies->isEmpty())
        <div class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800" role="status">
            Belum ada perusahaan. Tambahkan lead terlebih dahulu sebelum membuat deal.
        </div>
    @endif

    @if ($stages->isEmpty())
        <div class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800" role="status">
            Belum ada pipeline stage. Hubungi administrator untuk menyiapkan pipeline.
        </div>
    @endif

    <form wire:submit="save" class="bg-white border border-slate-200 rounded-lg p-6 space-y-5">
        <div>
            <label for="deal-company" class="block text-sm font-medium text-slate-700">
                Perusahaan <span class="text-red-600" aria-hidden="true">*</span>
            </label>
            <select id="deal-company" wire:model="company_id" required
                @error('company_id') aria-invalid="true" aria-describedby="deal-company-error" @enderror
                class="mt-1 block w-full rounded-md border px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 @error('company_id') border-red-400 @else border-slate-300 @enderror">
                <option value="">Pilih perusahaan</option>
                @foreach ($companies as $company)
                    <option value="{{ $company->id }}">{{ $company->name }}</option>
                @endforeach
            </select>
            @error('company_id')
                <p id="deal-company-error" class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="deal-title" class="block text-sm font-medium text-slate-700">
                Judul deal <span class="text-red-600" aria-hidden="true">*</span>
            </label>
            <input id="deal-title" type="text" wire:model="title" required placeholder="Contoh: Kontrak tahunan 2027"
                @error('title') aria-invalid="true" aria-describedby="deal-title-error" @enderror
                class="mt-1 block w-full rounded-md border px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 @error('title') border-red-400 @else border-slate-300 @enderror"/>
            @error('title')
                <p id="deal-title-error" class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
            @enderror
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label for="deal-value" class="block text-sm font-medium text-slate-700">
                    Nilai (IDR) <span class="text-red-600" aria-hidden="true">*</span>
                </label>
                <input id="deal-value" type="number" step="0.01" min="0" wire:model="value" required inputmode="decimal"
                    @error('value') aria-invalid="true" aria-describedby="deal-value-error" @enderror
                    class="mt-1 block w-full rounded-md border px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 @error('value') border-red-400 @else border-slate-300 @enderror"/>
                @error('value')
                    <p id="deal-value-error" class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="deal-close-date" class="block text-sm font-medium text-slate-700">Perkiraan closing</label>
                <input id="deal-close-date" type="date" wire:model="expected_close_date"
                    @error('expected_close_date') aria-invalid="true" aria-describedby="deal-close-date-error" @enderror
                    class="mt-1 block w-full rounded-md border px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 @error('expected_close_date') border-red-400 @else border-slate-300 @enderror"/>
                @error('expected_close_date')
                    <p id="deal-close-date-error" class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div>
            <label for="deal-stage" class="block text-sm font-medium text-slate-700">
                Stage <span class="text-red-600" aria-hidden="true">*</span>
            </label>
            <select id="deal-stage" wire:model="stage_id" required
                @error('stage_id') aria-invalid="true" aria-describedby="deal-stage-error" @enderror
                class="mt-1 block w-full rounded-md border px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 @error('stage_id') border-red-400 @else border-slate-300 @enderror">
                @foreach ($stages as $stage)
                    <option value="{{ $stage->id }}">{{ $stage->pipeline->name }} / {{ $stage->name }}</option>
                @endforeach
            </select>
            @error('stage_id')
                <p id="deal-stage-error" class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex items-center justify-end gap-3 border-t border-slate-100 pt-4">
            <a href="{{ route('deals.index') }}" wire:navigate
                class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                Batal
            </a>
            <button type="submit" wire:loading.attr="disabled" wire:target="save" @disabled($companies->isEmpty() || $stages->isEmpty())
                class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60">
                <span wire:loading.remove wire:target="save">{{ $dealId ? 'Simpan perubahan' : 'Simpan deal' }}</span>
                <span wire:loading wire:target="save">Menyimpan...</span>
            </button>
        </div>
    </form>
</div>
