<div class="space-y-6">
    <x-page-header title="Analitik" description="Ringkasan performa lead dan deal yang dapat Anda akses." />

    @if (auth()->user()->can('leads.view') && ! auth()->user()->can('leads.assign'))
        <div class="rounded-md border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800" role="status">
            Angka pada halaman ini hanya mencakup lead dan deal yang menjadi milik Anda.
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white border border-slate-200 rounded-lg p-4">
            <div class="text-xs text-slate-500">Lead aktif</div>
            <div class="text-2xl font-semibold text-slate-900">{{ number_format($totalLeads) }}</div>
        </div>
        <div class="bg-white border border-slate-200 rounded-lg p-4">
            <div class="text-xs text-slate-500">Terkualifikasi</div>
            <div class="text-2xl font-semibold text-slate-900">{{ number_format($qualifiedLeads) }}</div>
        </div>
        <div class="bg-white border border-slate-200 rounded-lg p-4">
            <div class="text-xs text-slate-500">Nilai deal terbuka</div>
            <div class="text-2xl font-semibold text-slate-900">Rp {{ number_format($openDealsValue, 0, ',', '.') }}</div>
        </div>
        <div class="bg-white border border-slate-200 rounded-lg p-4">
            <div class="text-xs text-slate-500">Menang bulan ini</div>
            <div class="text-2xl font-semibold text-slate-900">{{ number_format($wonDealsThisMonth) }}</div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white border border-slate-200 rounded-lg p-6">
            <h3 class="font-semibold text-slate-900">Lead per status</h3>
            <ul class="mt-3 space-y-2 text-sm">
                @forelse ($leadsByStatus as $status => $total)
                    <li class="flex justify-between gap-3">
                        <span class="text-slate-600">{{ $labels[$status] ?? $status }}</span>
                        <span class="font-medium tabular-nums text-slate-900">{{ $total }}</span>
                    </li>
                @empty
                    <li class="text-slate-500">Belum ada data.</li>
                @endforelse
            </ul>
            <div class="mt-4 pt-4 border-t border-slate-100 text-sm text-slate-600">
                Aktivitas 7 hari terakhir: <span class="font-medium text-slate-900">{{ $activitiesLast7Days }}</span>
                &middot; Nilai deal menang: <span class="font-medium text-slate-900">Rp {{ number_format($wonDealsValue, 0, ',', '.') }}</span>
            </div>
        </div>

        <div class="bg-white border border-slate-200 rounded-lg p-6">
            <h3 class="font-semibold text-slate-900">Aktivitas terbaru</h3>
            <ul class="mt-3 space-y-2 text-sm">
                @forelse ($recentActivities as $activity)
                    <li class="flex justify-between gap-3" wire:key="activity-{{ $activity->id }}">
                        <span class="text-slate-700">
                            {{ $activity->lead?->company?->name ?? '-' }}: {{ $activity->description ?? $activity->type }}
                        </span>
                        <span class="shrink-0 text-slate-500">{{ $activity->occurred_at->translatedFormat('d M H:i') }}</span>
                    </li>
                @empty
                    <li class="text-slate-500">Belum ada aktivitas.</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
