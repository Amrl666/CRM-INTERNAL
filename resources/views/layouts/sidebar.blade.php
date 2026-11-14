<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'B2B Outreach CRM') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="h-full">
<div class="flex h-screen bg-slate-50">
    <!-- Sidebar -->
    <aside class="w-64 bg-white border-r border-slate-200 flex flex-col">
        <div class="h-16 flex items-center px-6 border-b border-slate-200">
            <span class="text-sm font-semibold text-slate-900">{{ config('app.name') }}</span>
        </div>

        <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
            @can('leads.view')
                <a href="{{ route('leads.index') }}" wire:navigate
                    class="flex items-center gap-3 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('leads.*') ? 'bg-blue-50 text-blue-700' : 'text-slate-700 hover:bg-slate-50' }}">
                    Lead
                </a>
            @endcan

            @can('deals.view')
                <a href="{{ route('deals.index') }}" wire:navigate
                    class="flex items-center gap-3 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('deals.*') ? 'bg-blue-50 text-blue-700' : 'text-slate-700 hover:bg-slate-50' }}">
                    Pipeline
                </a>
            @endcan

            @can('campaigns.view')
                <a href="{{ route('campaigns.index') }}" wire:navigate
                    class="flex items-center gap-3 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('campaigns.*') ? 'bg-blue-50 text-blue-700' : 'text-slate-700 hover:bg-slate-50' }}">
                    Campaign
                </a>
            @endcan

            @can('analytics.view')
                <a href="{{ route('analytics.overview') }}" wire:navigate
                    class="flex items-center gap-3 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('analytics.*') ? 'bg-blue-50 text-blue-700' : 'text-slate-700 hover:bg-slate-50' }}">
                    Analitik
                </a>
            @endcan

            @can('tasks.manage')
                <a href="{{ route('tasks.index') }}" wire:navigate
                    class="flex items-center gap-3 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('tasks.*') ? 'bg-blue-50 text-blue-700' : 'text-slate-700 hover:bg-slate-50' }}">
                    Tugas
                </a>
            @endcan
        </nav>

        <div class="border-t border-slate-200 px-3 py-4 space-y-1">
            @can('users.manage')
                <a href="{{ route('users.index') }}" wire:navigate
                    class="flex items-center gap-3 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('users.*') ? 'bg-blue-50 text-blue-700' : 'text-slate-700 hover:bg-slate-50' }}">
                    Pengguna
                </a>
            @endcan
            <a href="{{ route('settings.index') }}"
                class="flex items-center gap-3 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('settings.*') ? 'bg-blue-50 text-blue-700' : 'text-slate-700 hover:bg-slate-50' }}">
                Pengaturan
            </a>
            @can('settings.manage')
                <a href="{{ route('settings.suppression') }}"
                    class="flex items-center gap-3 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('settings.suppression') ? 'bg-blue-50 text-blue-700' : 'text-slate-700 hover:bg-slate-50' }}">
                    Suppression
                </a>
            @endcan
        </div>
    </aside>

    <!-- Main -->
    <div class="flex-1 flex flex-col min-w-0">
        <!-- Top bar -->
        <header class="h-16 bg-white border-b border-slate-200 flex items-center justify-between px-6">
            <h1 class="text-lg font-medium text-slate-900">{{ $heading ?? '' }}</h1>
            <div class="flex items-center gap-4">
                <span class="text-sm text-slate-500">{{ auth()->user()?->name }}</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-sm text-slate-600 hover:text-slate-900">Keluar</button>
                </form>
            </div>
        </header>

        <!-- Content -->
        <main class="flex-1 overflow-y-auto p-6">
            {{ $slot }}
        </main>
    </div>
</div>
@livewireScripts
</body>
</html>