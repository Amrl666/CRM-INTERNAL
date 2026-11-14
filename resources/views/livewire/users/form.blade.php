<div class="max-w-2xl mx-auto space-y-6">
    @if (session('status'))
        <div class="text-sm text-green-700 bg-green-50 border border-green-200 rounded px-3 py-2">
            {{ session('status') }}
        </div>
    @endif

    <form wire:submit="save" class="bg-white border border-slate-200 rounded-lg p-6 space-y-5">
        <div>
            <label for="name" class="block text-sm font-medium text-slate-700">Nama</label>
            <input id="name" type="text" wire:model="name" required
                class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm"/>
            @error('name') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
        </div>

        <div>
            <label for="email" class="block text-sm font-medium text-slate-700">Email</label>
            <input id="email" type="email" wire:model="email" required
                class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm"/>
            @error('email') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
        </div>

        <div>
            <label for="phone" class="block text-sm font-medium text-slate-700">Telepon</label>
            <input id="phone" type="text" wire:model="phone"
                class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm"/>
            @error('phone') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
        </div>

        <div>
            <label for="role" class="block text-sm font-medium text-slate-700">Role</label>
            <select id="role" wire:model="role" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                @foreach ($roles as $roleName)
                    <option value="{{ $roleName }}">{{ $roleName }}</option>
                @endforeach
            </select>
            @error('role') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
        </div>

        <div class="flex items-center gap-2">
            <input id="is_active" type="checkbox" wire:model="is_active" class="rounded border-slate-300"/>
            <label for="is_active" class="text-sm text-slate-700">Akun aktif</label>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label for="password" class="block text-sm font-medium text-slate-700">
                    Kata sandi {{ $user ? '(kosongkan jika tidak diubah)' : '' }}
                </label>
                <input id="password" type="password" wire:model="password" autocomplete="new-password"
                    class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm"/>
                @error('password') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
            </div>

            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-slate-700">Ulangi kata sandi</label>
                <input id="password_confirmation" type="password" wire:model="password_confirmation" autocomplete="new-password"
                    class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm"/>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('users.index') }}" wire:navigate class="text-sm text-slate-600 hover:text-slate-900">Batal</a>
            <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
                wire:loading.attr="disabled">
                <span wire:loading.remove>Simpan</span>
                <span wire:loading>Menyimpan...</span>
            </button>
        </div>
    </form>
</div>
