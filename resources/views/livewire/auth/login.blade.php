<div class="space-y-8">
    <div class="sm:mx-auto sm:w-full sm:max-w-sm">
        <h2 class="text-lg font-semibold leading-6 text-slate-900">Masuk ke sistem</h2>
        <p class="text-sm text-slate-500 mt-1">{{ config('app.name') }}</p>
    </div>

    <div class="sm:mx-auto sm:w-full sm:max-w-sm">
        <form wire:submit="authenticate" method="POST" class="space-y-5">
            @if (session('status'))
                <div class="mb-4 text-sm text-green-600 bg-green-50 border border-green-200 rounded px-3 py-2">
                    {{ session('status') }}
                </div>
            @endif

            <div>
                <label for="email" class="block text-sm font-medium leading-6 text-slate-700">Email</label>
                <input id="email" type="email" wire:model="email" autocomplete="username" required
                    class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-blue-600 focus:outline-none focus:ring-1 focus:ring-blue-600"/>
                @error('email') <span class="text-sm text-red-600 mt-1">{{ $message }}</span> @enderror
            </div>

            <div>
                <label for="password" class="block text-sm font-medium leading-6 text-slate-700">Kata sandi</label>
                <input id="password" type="password" wire:model="password" autocomplete="current-password" required
                    class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-blue-600 focus:outline-none focus:ring-1 focus:ring-blue-600"/>
                @error('password') <span class="text-sm text-red-600 mt-1">{{ $message }}</span> @enderror
            </div>

            <div class="flex items-center justify-between">
                <label for="remember" class="flex items-center gap-2 text-sm text-slate-600">
                    <input id="remember" type="checkbox" wire:model="remember" class="rounded border-slate-300"/>
                    Ingat saya
                </label>
                <a href="{{ route('password.request') }}" wire:navigate class="text-sm text-blue-600 hover:text-blue-700">
                    Lupa kata sandi?
                </a>
            </div>

            <button type="submit"
                class="w-full rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                wire:loading.attr="disabled">
                <span wire:loading.remove>Masuk</span>
                <span wire:loading>Memproses...</span>
            </button>
        </form>
    </div>
</div>
