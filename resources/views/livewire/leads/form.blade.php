<div class="max-w-2xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <h2 class="text-lg font-semibold text-slate-900">{{ $leadId ? 'Ubah lead' : 'Tambah lead' }}</h2>
        <a href="{{ route('leads.index') }}" wire:navigate class="text-sm text-slate-600 hover:text-slate-900">Kembali</a>
    </div>

    <form wire:submit="save" class="bg-white border border-slate-200 rounded-lg p-6 space-y-4">
        <div>
            <label class="block text-sm font-medium text-slate-700">Nama perusahaan</label>
            <input type="text" wire:model="company_name" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"/>
            @error('company_name') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700">Nama kontak</label>
            <input type="text" wire:model="contact_name" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"/>
            @error('contact_name') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700">Email</label>
                <input type="email" wire:model="email" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"/>
                @error('email') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Telepon</label>
                <input type="text" wire:model="phone" placeholder="0812..." class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"/>
                @error('phone') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700">Industri</label>
                <input type="text" wire:model="industry" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"/>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Sumber</label>
                <input type="text" wire:model="source" placeholder="referral, website..." class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"/>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700">Prioritas</label>
            <select wire:model="priority" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                <option value="low">Rendah</option>
                <option value="medium">Sedang</option>
                <option value="high">Tinggi</option>
            </select>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                Simpan
            </button>
        </div>
    </form>
</div>
