<div class="space-y-8">
    <div class="sm:mx-auto sm:w-full sm:max-w-sm">
        <h2 class="text-lg font-semibold text-slate-900">Reset kata sandi</h2>
        <p class="text-sm text-slate-500 mt-1">Masukkan kata sandi baru Anda.</p>
    </div>

    <div class="sm:mx-auto sm:w-full sm:max-w-sm">
        <form wire:submit="resetPassword" class="space-y-5">
            <div>
                <label for="email" class="block text-sm font-medium leading-6 text-slate-700">Email</label>
                <input id="email" type="email" wire:model="email" autocomplete="username" required
                    class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-blue-600 focus:outline-none focus:ring-1 focus:ring-blue-600"/>
                @error('email') <span class="text-sm text-red-600 mt-1">{{ $message }}</span> @enderror
            </div>

            <div>
                <label for="password" class="block text-sm font-medium leading-6 text-slate-700">Kata sandi baru</label>
                <input id="password" type="password" wire:model="password" autocomplete="new-password" required
                    class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-blue-600 focus:outline-none focus:ring-1 focus:ring-blue-600"/>
                @error('password') <span class="text-sm text-red-600 mt-1">{{ $message }}</span> @enderror
            </div>

            <div>
                <label for="password_confirmation" class="block text-sm font-medium leading-6 text-slate-700">Ulangi kata sandi</label>
                <input id="password_confirmation" type="password" wire:model="password_confirmation" autocomplete="new-password" required
                    class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-blue-600 focus:outline-none focus:ring-1 focus:ring-blue-600"/>
            </div>

            <button type="submit"
                class="w-full rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
                wire:loading.attr="disabled">
                <span wire:loading.remove>Simpan kata sandi</span>
                <span wire:loading>Memproses...</span>
            </button>
        </form>
    </div>
</div>
