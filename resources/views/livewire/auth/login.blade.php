<div class="space-y-6">
    <div class="text-center">
        <h2 class="text-2xl font-bold leading-8 text-slate-900">Login</h2>
        <p class="mt-1 text-sm text-slate-500">{{ config('app.name') }}</p>
    </div>

    <form wire:submit="authenticate" method="POST" class="space-y-5">
        @if (session('status'))
            <div class="mb-4 rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-700" role="status">
                {{ session('status') }}
            </div>
        @endif

        <div>
            <label for="email" class="block text-sm font-medium leading-6 text-slate-700">Email</label>
            <input id="email" type="email" wire:model="email" autocomplete="username" inputmode="email" required autofocus
                @error('email') aria-invalid="true" aria-describedby="email-error" @enderror
                class="mt-1 block w-full rounded-md border px-3 py-2 text-sm shadow-sm focus:border-blue-600 focus:outline-none focus:ring-1 focus:ring-blue-600 @error('email') border-red-400 @else border-slate-300 @enderror"/>
            @error('email')
                <p id="email-error" class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="block text-sm font-medium leading-6 text-slate-700">Kata sandi</label>
            <input id="password" type="password" wire:model="password" autocomplete="current-password" required
                @error('password') aria-invalid="true" aria-describedby="password-error" @enderror
                class="mt-1 block w-full rounded-md border px-3 py-2 text-sm shadow-sm focus:border-blue-600 focus:outline-none focus:ring-1 focus:ring-blue-600 @error('password') border-red-400 @else border-slate-300 @enderror"/>
            @error('password')
                <p id="password-error" class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex items-center justify-between gap-3">
            <label for="remember" class="flex items-center gap-2 text-sm text-slate-600">
                <input id="remember" type="checkbox" wire:model="remember" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500"/>
                Ingat saya
            </label>
            <a href="{{ route('password.request') }}" wire:navigate class="text-sm text-blue-600 hover:text-blue-700">
                Lupa kata sandi?
            </a>
        </div>

        <button type="submit" wire:loading.attr="disabled" wire:target="authenticate"
            class="w-full rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60">
            <span wire:loading.remove wire:target="authenticate">Masuk</span>
            <span wire:loading wire:target="authenticate">Memproses...</span>
        </button>
    </form>
</div>
