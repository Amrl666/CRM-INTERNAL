<div class="space-y-6">
    <div class="flex items-center justify-between gap-4">
        <div>
            <h2 class="text-lg font-semibold text-slate-900">{{ $userId ? 'Ubah pengguna' : 'Tambah pengguna' }}</h2>
            <p class="mt-1 text-sm text-slate-500">
                {{ $userId ? 'Perbarui profil, role, atau status akun pengguna.' : 'Buat akun internal baru dan tentukan role aksesnya.' }}
            </p>
        </div>
        <a href="{{ route('users.index') }}" wire:navigate class="shrink-0 text-sm text-slate-600 hover:text-slate-900">Kembali</a>
    </div>

    @error('form')
        <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{{ $message }}</div>
    @enderror

    <form wire:submit="save" class="bg-white border border-slate-200 rounded-lg p-6 space-y-5">
        <div>
            <label for="name" class="block text-sm font-medium text-slate-700">
                Nama <span class="text-red-600" aria-hidden="true">*</span>
            </label>
            <input id="name" type="text" wire:model="name" required autocomplete="name"
                @error('name') aria-invalid="true" aria-describedby="name-error" @enderror
                class="mt-1 block w-full rounded-md border px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 @error('name') border-red-400 @else border-slate-300 @enderror"/>
            @error('name')
                <p id="name-error" class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="email" class="block text-sm font-medium text-slate-700">
                Email <span class="text-red-600" aria-hidden="true">*</span>
            </label>
            <input id="email" type="email" wire:model="email" required autocomplete="email" inputmode="email"
                @error('email') aria-invalid="true" aria-describedby="email-error" @enderror
                class="mt-1 block w-full rounded-md border px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 @error('email') border-red-400 @else border-slate-300 @enderror"/>
            @error('email')
                <p id="email-error" class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="phone" class="block text-sm font-medium text-slate-700">Telepon</label>
            <input id="phone" type="tel" wire:model="phone" autocomplete="tel" inputmode="tel"
                @error('phone') aria-invalid="true" aria-describedby="phone-error" @enderror
                class="mt-1 block w-full rounded-md border px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 @error('phone') border-red-400 @else border-slate-300 @enderror"/>
            @error('phone')
                <p id="phone-error" class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="position" class="block text-sm font-medium text-slate-700">Jabatan</label>
            <input id="position" type="text" wire:model="position" autocomplete="organization-title"
                placeholder="Contoh: CEO & Founder"
                @error('position') aria-invalid="true" aria-describedby="position-error" @enderror
                class="mt-1 block w-full rounded-md border px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 @error('position') border-red-400 @else border-slate-300 @enderror"/>
            @error('position')
                <p id="position-error" class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
            @enderror
            <p class="mt-1 text-xs text-slate-500">Digunakan sebagai tanda tangan pada footer email draft.</p>
        </div>

        <div>
            <label for="role" class="block text-sm font-medium text-slate-700">
                Role <span class="text-red-600" aria-hidden="true">*</span>
            </label>
            <select id="role" wire:model="role" required
                @error('role') aria-invalid="true" aria-describedby="role-error" @enderror
                class="mt-1 block w-full rounded-md border px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 @error('role') border-red-400 @else border-slate-300 @enderror">
                @foreach ($roles as $roleName)
                    <option value="{{ $roleName }}">{{ $roleName }}</option>
                @endforeach
            </select>
            @error('role')
                <p id="role-error" class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
            @enderror
        </div>

        <div class="rounded-md border border-slate-200 bg-slate-50 px-4 py-3">
            <div class="flex items-start gap-3">
                <input id="is_active" type="checkbox" wire:model="is_active"
                    class="mt-0.5 rounded border-slate-300 text-blue-600 focus:ring-blue-500"/>
                <div>
                    <label for="is_active" class="text-sm font-medium text-slate-800">Akun aktif</label>
                    <p class="text-xs text-slate-500">Akun nonaktif tidak dapat login, tetapi data dan riwayatnya tetap tersimpan.</p>
                    @error('is_active')
                        <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label for="password" class="block text-sm font-medium text-slate-700">
                    Kata sandi {{ $userId ? '(kosongkan jika tidak diubah)' : '' }}
                    @unless ($userId) <span class="text-red-600" aria-hidden="true">*</span> @endunless
                </label>
                <input id="password" type="password" wire:model="password" autocomplete="new-password"
                    @error('password') aria-invalid="true" aria-describedby="password-error" @enderror
                    class="mt-1 block w-full rounded-md border px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 @error('password') border-red-400 @else border-slate-300 @enderror"/>
                @error('password')
                    <p id="password-error" class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                @enderror
                <p class="mt-1 text-xs text-slate-500">Minimal 8 karakter.</p>
            </div>

            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-slate-700">Ulangi kata sandi</label>
                <input id="password_confirmation" type="password" wire:model="password_confirmation" autocomplete="new-password"
                    class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"/>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3 border-t border-slate-100 pt-4">
            <a href="{{ route('users.index') }}" wire:navigate
                class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                Batal
            </a>
            <button type="submit" wire:loading.attr="disabled" wire:target="save"
                class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60">
                <span wire:loading.remove wire:target="save">{{ $userId ? 'Simpan perubahan' : 'Buat pengguna' }}</span>
                <span wire:loading wire:target="save">Menyimpan...</span>
            </button>
        </div>
    </form>
</div>
