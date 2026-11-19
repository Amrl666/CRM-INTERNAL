<div class="space-y-8">
    <div class="sm:mx-auto sm:w-full sm:max-w-sm">
        <h2 class="text-lg font-semibold text-slate-900">Reset kata sandi</h2>
        <p class="text-sm text-slate-500 mt-1">Masukkan kata sandi baru Anda.</p>
    </div>

    <div class="sm:mx-auto sm:w-full sm:max-w-sm">
        @error('token')
            <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" role="alert">{{ $message }}</div>
        @enderror

        <form wire:submit="resetPassword" class="space-y-5">
            <div>
                <label for="email" class="block text-sm font-medium leading-6 text-slate-700">Email</label>
                <input id="email" type="email" wire:model="email" autocomplete="username" inputmode="email" required
                    @error('email') aria-invalid="true" aria-describedby="email-error" @enderror
                    class="mt-1 block w-full rounded-md border px-3 py-2 text-sm shadow-sm focus:border-blue-600 focus:outline-none focus:ring-1 focus:ring-blue-600 @error('email') border-red-400 @else border-slate-300 @enderror"/>
                @error('email')
                    <p id="email-error" class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password" class="block text-sm font-medium leading-6 text-slate-700">Kata sandi baru</label>
                <input id="password" type="password" wire:model="password" autocomplete="new-password" required autofocus
                    @error('password') aria-invalid="true" aria-describedby="password-error" @enderror
                    class="mt-1 block w-full rounded-md border px-3 py-2 text-sm shadow-sm focus:border-blue-600 focus:outline-none focus:ring-1 focus:ring-blue-600 @error('password') border-red-400 @else border-slate-300 @enderror"/>
                @error('password')
                    <p id="password-error" class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                @enderror
                <p class="mt-1 text-xs text-slate-500">Minimal 8 karakter.</p>
            </div>

            <div>
                <label for="password_confirmation" class="block text-sm font-medium leading-6 text-slate-700">Ulangi kata sandi</label>
                <input id="password_confirmation" type="password" wire:model="password_confirmation" autocomplete="new-password" required
                    class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-blue-600 focus:outline-none focus:ring-1 focus:ring-blue-600"/>
            </div>

            <button type="submit" wire:loading.attr="disabled" wire:target="resetPassword"
                class="w-full rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60">
                <span wire:loading.remove wire:target="resetPassword">Simpan kata sandi</span>
                <span wire:loading wire:target="resetPassword">Memproses...</span>
            </button>
        </form>
    </div>
</div>
