<div class="max-w-xl mx-auto">
    <h1 class="text-2xl font-bold text-slate-900 mb-6">Dasbor</h1>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <x-stat-card title="Total Lead" :value="$totalLeads ?? 0"/>
        <x-stat-card title="Lead Aktif" :value="$activeLeads ?? 0"/>
        <x-stat-card title="Task Hari Ini" :value="$todayTasks ?? 0"/>
        <x-stat-card title="Deal Terbuka" :value="$openDeals ?? 0"/>
    </div>

    <div class="bg-white border border-slate-200 rounded-lg p-6">
        <h2 class="text-lg font-medium text-slate-900 mb-4">Selamat datang di B2B Outreach CRM</h2>
        <p class="text-slate-600 text-sm">Sistem ini dikelola sesuai AGENT.md — modular monolith untuk internal sales.</p>
    </div>
</div>