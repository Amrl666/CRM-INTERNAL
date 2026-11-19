<div class="space-y-6">
    <x-page-header title="Pengaturan" description="Kelola akses pengguna, integrasi komunikasi, dan daftar suppression." />

    <div class="bg-white border border-slate-200 rounded-lg p-6">
        <h2 class="text-lg font-medium text-slate-900">Pengguna & Akses</h2>
        <p class="text-sm text-slate-600 mt-1">Kelola akun internal, role, dan status aktif pengguna.</p>
        @can('users.manage')
            <a href="{{ route('users.index') }}" wire:navigate
                class="inline-block mt-4 rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                Kelola pengguna
            </a>
        @endcan
    </div>

    <div class="bg-white border border-slate-200 rounded-lg p-6">
        <h2 class="text-lg font-medium text-slate-900">Integrasi Komunikasi</h2>
        <dl class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
            <div>
                <dt class="text-slate-500">Email provider</dt>
                <dd class="mt-1 font-medium text-slate-900">{{ $emailProvider }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">WhatsApp provider</dt>
                <dd class="mt-1 font-medium text-slate-900">{{ $whatsappProvider }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">WhatsApp outbound mode</dt>
                <dd class="mt-1 font-medium text-slate-900">{{ $outboundMode }}</dd>
            </div>
        </dl>
        <p class="mt-4 text-xs text-slate-500">
            Mode <strong>manual</strong> wajib dipertahankan. Pengiriman otomatis via Baileys tidak didukung.
        </p>

        @error('gmail')
            <div class="mt-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" role="alert">{{ $message }}</div>
        @enderror

        <div class="mt-6 border-t border-slate-100 pt-4">
            <h3 class="text-sm font-medium text-slate-900">Akun Gmail</h3>
            <ul class="mt-2 text-sm text-slate-600 space-y-1">
                @forelse ($gmailAccounts as $account)
                    <li wire:key="gmail-account-{{ $account->id }}">{{ $account->account_email }} &middot; {{ $account->connection_status }}</li>
                @empty
                    <li>Belum ada akun terhubung.</li>
                @endforelse
            </ul>
            @can('settings.manage')
                <a href="{{ route('settings.gmail.connect') }}"
                    class="inline-block mt-3 rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Hubungkan Gmail
                </a>
            @endcan
        </div>
    </div>

    @can('settings.manage')
        <div class="bg-white border border-slate-200 rounded-lg p-6">
            <h2 class="text-lg font-medium text-slate-900">Suppression global</h2>
            <p class="text-sm text-slate-600 mt-1">Alamat yang meminta berhenti dihubungi akan otomatis diblokir sebelum pengiriman.</p>
            <a href="{{ route('settings.suppression') }}" wire:navigate
                class="inline-block mt-4 rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                Kelola suppression
            </a>
        </div>
    @endcan
</div>
