<div class="max-w-2xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <h2 class="text-lg font-semibold text-slate-900">{{ $dealId ? 'Ubah deal' : 'Tambah deal' }}</h2>
        <a href="{{ route('deals.index') }}" wire:navigate class="text-sm text-slate-600 hover:text-slate-900">Kembali</a>
    </div>

    <form wire:submit="save" class="bg-white border border-slate-200 rounded-lg p-6 space-y-4">
        <div>
            <label class="block text-sm font-medium text-slate-700">Perusahaan</label>
            <select wire:model="company_id" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                <option value="">Pilih perusahaan</option>
                @foreach ($companies as $company)
                    <option value="{{ $company->id }}">{{ $company->name }}</option>
                @endforeach
            </select>
            @error('company_id') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700">Judul deal</label>
            <input type="text" wire:model="title" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"/>
            @error('title') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700">Nilai (IDR)</label>
                <input type="number" step="0.01" wire:model="value" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"/>
                @error('value') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Perkiraan closing</label>
                <input type="date" wire:model="expected_close_date" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"/>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700">Stage</label>
            <select wire:model="stage_id" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                @foreach ($stages as $stage)
                    <option value="{{ $stage->id }}">{{ $stage->pipeline->name }} / {{ $stage->name }}</option>
                @endforeach
            </select>
            @error('stage_id') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
        </div>

        <div class="flex justify-end">
            <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                Simpan
            </button>
        </div>
    </form>
</div>
