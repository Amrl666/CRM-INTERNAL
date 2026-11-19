<div class="space-y-8">
    <div class="sm:mx-auto sm:w-full sm:max-w-sm">
        <h2 class="text-lg font-semibold text-slate-900">Lupa kata sandi</h2>
        <p class="text-sm text-slate-500 mt-1">Masukkan email akun Anda. Kami akan mengirim tautan reset.</p>
    </div>

    <div class="sm:mx-auto sm:w-full sm:max-w-sm">
        @if ($sent)
            <div class="rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-700" role="status">
                Tautan reset telah dikirim. Periksa email Anda.
            </div>
        @else
            <form wire:submit="sendResetLink" class="space-y-5">
                <div>
                    <label for="email" class="block text-sm font-medium leading-6 text-slate-700">Email</label>
                    <input id="email" type="email" wire:model="email" autocomplete="username" inputmode="email" required autofocus
                        @error('email') aria-invalid="true" aria-describedby="email-error" @enderror
                        class="mt-1 block w-full rounded-md border px-3 py-2 text-sm shadow-sm focus:border-blue-600 focus:outline-none focus:ring-1 focus:ring-blue-600 @error('email') border-red-400 @else border-slate-300 @enderror"/>
                    @error('email')
                        <p id="email-error" class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" wire:loading.attr="disabled" wire:target="sendResetLink"
                    class="w-full rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60">
                    <span wire:loading.remove wire:target="sendResetLink">Kirim tautan</span>
                    <span wire:loading wire:target="sendResetLink">Memproses...</span>
                </button>
            </form>
        @endif

        <p class="mt-6 text-center text-sm text-slate-600">
            <a href="{{ route('login') }}" wire:navigate class="text-blue-600 hover:text-blue-700">Kembali ke login</a>
        </p>
    </div>
</div>
