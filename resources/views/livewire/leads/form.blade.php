<div class="space-y-6">
    <div class="flex items-center justify-between gap-4">
        <div>
            <h2 class="text-lg font-semibold text-slate-900">{{ $leadId ? 'Ubah lead' : 'Tambah lead' }}</h2>
            <p class="mt-1 text-sm text-slate-500">
                {{ $leadId ? 'Perbarui data perusahaan, kontak, dan prioritas lead.' : 'Masukkan data perusahaan dan kontak utama untuk membuat lead baru.' }}
            </p>
        </div>
        <a href="{{ route('leads.index') }}" wire:navigate class="shrink-0 text-sm text-slate-600 hover:text-slate-900">Kembali</a>
    </div>

    @error('form')
        <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{{ $message }}</div>
    @enderror

    <form wire:submit="save" class="bg-white border border-slate-200 rounded-lg p-6 space-y-5">
        <div>
            <label for="lead-company-name" class="block text-sm font-medium text-slate-700">
                Nama perusahaan <span class="text-red-600" aria-hidden="true">*</span>
            </label>
            <input id="lead-company-name" type="text" wire:model="company_name" required autocomplete="organization"
                @error('company_name') aria-invalid="true" aria-describedby="lead-company-name-error" @enderror
                class="mt-1 block w-full rounded-md border px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 @error('company_name') border-red-400 @else border-slate-300 @enderror"/>
            @error('company_name')
                <p id="lead-company-name-error" class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="lead-contact-name" class="block text-sm font-medium text-slate-700">
                Nama kontak <span class="text-red-600" aria-hidden="true">*</span>
            </label>
            <input id="lead-contact-name" type="text" wire:model="contact_name" required autocomplete="name"
                @error('contact_name') aria-invalid="true" aria-describedby="lead-contact-name-error" @enderror
                class="mt-1 block w-full rounded-md border px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 @error('contact_name') border-red-400 @else border-slate-300 @enderror"/>
            @error('contact_name')
                <p id="lead-contact-name-error" class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
            @enderror
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label for="lead-email" class="block text-sm font-medium text-slate-700">Email</label>
                <input id="lead-email" type="email" wire:model="email" autocomplete="email" inputmode="email"
                    @error('email') aria-invalid="true" aria-describedby="lead-email-error" @enderror
                    class="mt-1 block w-full rounded-md border px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 @error('email') border-red-400 @else border-slate-300 @enderror"/>
                @error('email')
                    <p id="lead-email-error" class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="lead-phone" class="block text-sm font-medium text-slate-700">Telepon</label>
                <input id="lead-phone" type="tel" wire:model="phone" placeholder="0812..." autocomplete="tel" inputmode="tel"
                    @error('phone') aria-invalid="true" aria-describedby="lead-phone-error" @enderror
                    class="mt-1 block w-full rounded-md border px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 @error('phone') border-red-400 @else border-slate-300 @enderror"/>
                @error('phone')
                    <p id="lead-phone-error" class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                @enderror
            </div>
        </div>
        <p class="-mt-2 text-xs text-slate-500">Minimal salah satu dari email atau telepon wajib diisi.</p>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label for="lead-industry" class="block text-sm font-medium text-slate-700">Industri</label>
                <input id="lead-industry" type="text" wire:model="industry" autocomplete="organization-title"
                    @error('industry') aria-invalid="true" aria-describedby="lead-industry-error" @enderror
                    class="mt-1 block w-full rounded-md border px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 @error('industry') border-red-400 @else border-slate-300 @enderror"/>
                @error('industry')
                    <p id="lead-industry-error" class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="lead-source" class="block text-sm font-medium text-slate-700">Sumber</label>
                <input id="lead-source" type="text" wire:model="source" placeholder="referral, website..."
                    @error('source') aria-invalid="true" aria-describedby="lead-source-error" @enderror
                    class="mt-1 block w-full rounded-md border px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 @error('source') border-red-400 @else border-slate-300 @enderror"/>
                @error('source')
                    <p id="lead-source-error" class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div>
            <label for="lead-priority" class="block text-sm font-medium text-slate-700">Prioritas</label>
            <select id="lead-priority" wire:model="priority"
                @error('priority') aria-invalid="true" aria-describedby="lead-priority-error" @enderror
                class="mt-1 block w-full rounded-md border px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 @error('priority') border-red-400 @else border-slate-300 @enderror">
                <option value="low">Rendah</option>
                <option value="medium">Sedang</option>
                <option value="high">Tinggi</option>
            </select>
            @error('priority')
                <p id="lead-priority-error" class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex items-center justify-end gap-3 border-t border-slate-100 pt-4">
            <a href="{{ $leadId ? route('leads.show', $leadId) : route('leads.index') }}" wire:navigate
                class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                Batal
            </a>
            <button type="submit" wire:loading.attr="disabled" wire:target="save"
                class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60">
                <span wire:loading.remove wire:target="save">{{ $leadId ? 'Simpan perubahan' : 'Simpan lead' }}</span>
                <span wire:loading wire:target="save">Menyimpan...</span>
            </button>
        </div>
    </form>
</div>
