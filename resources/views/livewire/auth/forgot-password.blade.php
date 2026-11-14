<div class="space-y-8">
    <div class="sm:mx-auto sm:w-full sm:max-w-sm">
        <h2 class="text-lg font-semibold text-slate-900">Lupa kata sandi</h2>
        <p class="text-sm text-slate-500 mt-1">Masukkan email akun Anda. Kami akan mengirim tautan reset.</p>
    </div>

    <div class="sm:mx-auto sm:w-full sm:max-w-sm">
        @if ($sent)
            <div class="text-sm text-green-700 bg-green-50 border border-green-200 rounded px-3 py-2">
                Tautan reset telah dikirim. Periksa email Anda.
            </div>
        @else
            <form wire:submit="sendResetLink" class="space-y-5">
                <div>
                    <label for="email" class="block text-sm font-medium leading-6 text-slate-700">Email</label>
                    <input id="email" type="email" wire:model="email" autocomplete="username" required
                        class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-blue-600 focus:outline-none focus:ring-1 focus:ring-blue-600"/>
                    @error('email') <span class="text-sm text-red-600 mt-1">{{ $message }}</span> @enderror
                </div>

                <button type="submit"
                    class="w-full rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
                    wire:loading.attr="disabled">
                    <span wire:loading.remove>Kirim tautan</span>
                    <span wire:loading>Memproses...</span>
                </button>
            </form>
        @endif

        <p class="mt-6 text-center text-sm text-slate-600">
            <a href="{{ route('login') }}" wire:navigate class="text-blue-600 hover:text-blue-700">Kembali ke login</a>
        </p>
    </div>
</div>
