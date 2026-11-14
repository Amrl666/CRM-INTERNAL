<div class="max-w-2xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <h2 class="text-lg font-semibold text-slate-900">Buat campaign</h2>
        <a href="{{ route('campaigns.index') }}" wire:navigate class="text-sm text-slate-600 hover:text-slate-900">Kembali</a>
    </div>

    <form wire:submit="save" class="bg-white border border-slate-200 rounded-lg p-6 space-y-4">
        <div>
            <label class="block text-sm font-medium text-slate-700">Nama campaign</label>
            <input type="text" wire:model="name" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"/>
            @error('name') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700">Template</label>
            <select wire:model="template_id" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                <option value="">Pilih template</option>
                @foreach ($templates as $template)
                    <option value="{{ $template->id }}">{{ $template->name }} — {{ $template->subject }}</option>
                @endforeach
            </select>
            @error('template_id') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700">Penerima (lead aktif)</label>
            <select wire:model="leadIds" multiple size="10" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                @foreach ($leads as $lead)
                    <option value="{{ $lead->id }}">
                        {{ $lead->company->name }} — {{ $lead->contact->full_name }} ({{ $lead->contact->normalized_email ?? 'tanpa email' }})
                    </option>
                @endforeach
            </select>
            @error('leadIds') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
        </div>

        <div class="flex justify-end">
            <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                Simpan draft
            </button>
        </div>
    </form>
</div>
