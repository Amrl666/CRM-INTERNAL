<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Dashboard</h1>
            <p class="mt-1 text-sm text-slate-500">Ringkasan lead, deal, dan tugas yang dapat Anda akses.</p>
        </div>
        <span class="text-sm text-slate-500">{{ now()->translatedFormat('l, d F Y') }}</span>
    </div>

    <!-- KPI Cards -->
    <div class="grid grid-cols-2 gap-4 md:grid-cols-2 lg:grid-cols-3">
        <x-stat-card title="Total Lead" :value="$totalLeads" />
        <x-stat-card title="Lead Aktif" :value="$activeLeads" />
        <x-stat-card title="Terkualifikasi" :value="$qualifiedLeads" />
        <x-stat-card title="Deal Terbuka" :value="$openDeals" />
        <x-stat-card title="Deal Menang" :value="$wonDeals" />
        <x-stat-card title="Task Hari Ini" :value="$todayTasks" />
        <x-stat-card title="Outreach Aktif" :value="$outreachActiveCampaigns" />
        <x-stat-card title="Outreach Terjadwal" :value="$outreachScheduled" />
        <x-stat-card title="Outreach Terkirim" :value="$outreachSent" />
        <x-stat-card title="Outreach Gagal" :value="$outreachFailed" />
    </div>

    <!-- Charts -->
    <div class="grid gap-6 md:grid-cols-2">
        <!-- Lead Status Distribution -->
        <div class="rounded-lg border border-slate-200 bg-white p-6">
            <h2 class="text-lg font-medium text-slate-900 mb-4">Distribusi Status Lead</h2>
            <div class="space-y-3">
                @forelse ($leadStatusCounts as $status => $count)
                    @php
                        $total = array_sum($leadStatusCounts);
                        $pct = $total > 0 ? round(($count / $total) * 100) : 0;
                        $label = \App\Domain\Leads\Enums\LeadStatus::tryFrom($status)?->label() ?? $status;
                    @endphp
                    <div>
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-slate-600">{{ $label }}</span>
                            <span class="font-medium text-slate-900">{{ $count }} ({{ $pct }}%)</span>
                        </div>
                        <div class="mt-1 h-2 rounded-full bg-slate-100" role="progressbar" aria-label="{{ $label }}"
                            aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100">
                            <div class="h-2 rounded-full bg-blue-600 transition-all" style="width: {{ $pct }}%"></div>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">Belum ada data lead.</p>
                @endforelse
            </div>
        </div>

        <!-- Deal Stage Distribution -->
        <div class="rounded-lg border border-slate-200 bg-white p-6">
            <h2 class="text-lg font-medium text-slate-900 mb-4">Deal per Stage</h2>
            <div class="space-y-3">
                @forelse ($dealStageCounts as $stage => $count)
                    @php
                        $total = array_sum($dealStageCounts);
                        $pct = $total > 0 ? round(($count / $total) * 100) : 0;
                    @endphp
                    <div>
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-slate-600">{{ $stage }}</span>
                            <span class="font-medium text-slate-900">{{ $count }} ({{ $pct }}%)</span>
                        </div>
                        <div class="mt-1 h-2 rounded-full bg-slate-100" role="progressbar" aria-label="{{ $stage }}"
                            aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100">
                            <div class="h-2 rounded-full bg-green-600 transition-all" style="width: {{ $pct }}%"></div>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">Belum ada data deal.</p>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Quick links -->
    <div class="grid gap-4 md:grid-cols-3">
        <a href="{{ route('leads.index') }}" wire:navigate class="rounded-lg border border-slate-200 bg-white p-5 hover:shadow-md transition-shadow">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-50 text-blue-700">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                    </svg>
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-900">Kelola Lead</p>
                    <p class="text-xs text-slate-500">Import, validasi, dan segmentasi</p>
                </div>
            </div>
        </a>
        <a href="{{ route('proposals.index') }}" wire:navigate class="rounded-lg border border-slate-200 bg-white p-5 hover:shadow-md transition-shadow">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-green-50 text-green-700">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                    </svg>
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-900">Outreach</p>
                    <p class="text-xs text-slate-500">Kirim proposal terpersonalisasi</p>
                </div>
            </div>
        </a>
        <a href="{{ route('deals.index') }}" wire:navigate class="rounded-lg border border-slate-200 bg-white p-5 hover:shadow-md transition-shadow">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-purple-50 text-purple-700">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 006 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0118 16.5h-2.25m-7.5 0h7.5m-7.5 0l-1 3m8.5-3l1 3m0 0l.5 1.5m-.5-1.5h-9.5m0 0l-.5 1.5M9 11.25v1.5M12 9v3.75m3-6v6" />
                    </svg>
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-900">Pipeline</p>
                    <p class="text-xs text-slate-500">Kelola deal dan stage</p>
                </div>
            </div>
        </a>
    </div>
</div>
