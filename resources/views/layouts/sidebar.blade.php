<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Prospectra') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="h-full" x-data="{ sidebarOpen: localStorage.getItem('sidebarOpen') !== 'false', mobileOpen: false }" x-init="$watch('sidebarOpen', val => localStorage.setItem('sidebarOpen', val))">
<a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-[80] focus:rounded-md focus:bg-blue-700 focus:px-4 focus:py-2 focus:text-sm focus:font-medium focus:text-white">
    Lewati ke konten utama
</a>
<div class="flex h-screen bg-slate-50">
    <!-- Sidebar -->
    <aside
        :class="[sidebarOpen ? 'md:w-64' : 'md:w-16', mobileOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0']"
        class="fixed inset-y-0 left-0 z-50 flex w-64 flex-col overflow-hidden border-r border-slate-200 bg-white transition-all duration-300 ease-in-out md:static md:z-auto"
        aria-label="Navigasi utama"
    >
        <div class="h-16 flex items-center justify-between px-4 border-b border-slate-200">
            <div class="flex items-center gap-2 overflow-hidden" x-show="sidebarOpen" x-transition>
                <img src="{{ asset('image/logo.png') }}" alt="Prospectra" class="h-8 w-8 rounded" />
                <span class="text-sm font-semibold text-slate-900 whitespace-nowrap">{{ config('app.name') }}</span>
            </div>
            <button type="button" @click="sidebarOpen = !sidebarOpen" class="hidden shrink-0 rounded-md p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 md:inline-flex"
                :aria-label="sidebarOpen ? 'Ciutkan menu' : 'Bentangkan menu'" :aria-expanded="sidebarOpen">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                </svg>
            </button>
        </div>

        <nav class="flex-1 space-y-1 overflow-y-auto px-2 py-4" @click="if ($event.target.closest('a')) mobileOpen = false">
            <a href="{{ route('dashboard') }}" wire:navigate
                class="flex items-center gap-3 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('dashboard') ? 'bg-blue-50 text-blue-700' : 'text-slate-700 hover:bg-slate-50' }}"
                :class="sidebarOpen ? '' : 'justify-center'">
                <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />
                </svg>
                <span x-show="sidebarOpen" x-transition class="whitespace-nowrap">Dashboard</span>
            </a>
            @can('inbox.view')
                <a href="{{ route('inbox.index') }}" wire:navigate
                    class="flex items-center gap-3 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('inbox.*') ? 'bg-blue-50 text-blue-700' : 'text-slate-700 hover:bg-slate-50' }}"
                    :class="sidebarOpen ? '' : 'justify-center'">
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z" />
                    </svg>
                    <span x-show="sidebarOpen" x-transition class="whitespace-nowrap">Inbox</span>
                </a>
            @endcan

            @can('leads.view')
                <a href="{{ route('leads.index') }}" wire:navigate
                    class="flex items-center gap-3 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('leads.*') ? 'bg-blue-50 text-blue-700' : 'text-slate-700 hover:bg-slate-50' }}"
                    :class="sidebarOpen ? '' : 'justify-center'">
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                    </svg>
                    <span x-show="sidebarOpen" x-transition class="whitespace-nowrap">Lead</span>
                </a>
            @endcan



            @can('campaigns.view')
                <a href="{{ route('proposals.index') }}" wire:navigate
                    class="flex items-center gap-3 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('proposals.*') ? 'bg-blue-50 text-blue-700' : 'text-slate-700 hover:bg-slate-50' }}"
                    :class="sidebarOpen ? '' : 'justify-center'">
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                    </svg>
                    <span x-show="sidebarOpen" x-transition class="whitespace-nowrap">Outreach</span>
                </a>
            @endcan

            @can('campaigns.view')
                <a href="{{ route('campaigns.index') }}" wire:navigate
                    class="flex items-center gap-3 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('campaigns.*') ? 'bg-blue-50 text-blue-700' : 'text-slate-700 hover:bg-slate-50' }}"
                    :class="sidebarOpen ? '' : 'justify-center'">
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                    </svg>
                    <span x-show="sidebarOpen" x-transition class="whitespace-nowrap">Email Drafts</span>
                </a>
            @endcan



        </nav>

        <div class="space-y-1 border-t border-slate-200 px-2 py-4" @click="if ($event.target.closest('a')) mobileOpen = false">
            @can('deals.view')
                <a href="{{ route('deals.index') }}" wire:navigate
                    class="flex items-center gap-3 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('deals.*') ? 'bg-blue-50 text-blue-700' : 'text-slate-600 hover:bg-slate-50' }}"
                    :class="sidebarOpen ? '' : 'justify-center'">
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 016 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0118 16.5h-2.25m-7.5 0h7.5m-7.5 0l-1 3m8.5-3l1 3m0 0l.5 1.5m-.5-1.5h-9.5m0 0l-.5 1.5M9 11.25v1.5M12 9v3.75m3-6v6" />
                    </svg>
                    <span x-show="sidebarOpen" x-transition class="whitespace-nowrap">Pipeline</span>
                </a>
            @endcan
            @can('tasks.manage')
                <a href="{{ route('tasks.index') }}" wire:navigate
                    class="flex items-center gap-3 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('tasks.*') ? 'bg-blue-50 text-blue-700' : 'text-slate-600 hover:bg-slate-50' }}"
                    :class="sidebarOpen ? '' : 'justify-center'">
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span x-show="sidebarOpen" x-transition class="whitespace-nowrap">Tugas</span>
                </a>
            @endcan
            @can('analytics.view')
                <a href="{{ route('analytics.overview') }}" wire:navigate
                    class="flex items-center gap-3 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('analytics.*') ? 'bg-blue-50 text-blue-700' : 'text-slate-600 hover:bg-slate-50' }}"
                    :class="sidebarOpen ? '' : 'justify-center'">
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
                    </svg>
                    <span x-show="sidebarOpen" x-transition class="whitespace-nowrap">Analitik</span>
                </a>
            @endcan
            @can('users.manage')
                <a href="{{ route('users.index') }}" wire:navigate
                    class="flex items-center gap-3 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('users.*') ? 'bg-blue-50 text-blue-700' : 'text-slate-700 hover:bg-slate-50' }}"
                    :class="sidebarOpen ? '' : 'justify-center'">
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                    </svg>
                    <span x-show="sidebarOpen" x-transition class="whitespace-nowrap">Pengguna</span>
                </a>
            @endcan
            @can('settings.manage')
                <a href="{{ route('settings.templates') }}" wire:navigate
                    class="flex items-center gap-3 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('settings.templates') ? 'bg-blue-50 text-blue-700' : 'text-slate-600 hover:bg-slate-50' }}"
                    :class="sidebarOpen ? '' : 'justify-center'">
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                    </svg>
                    <span x-show="sidebarOpen" x-transition class="whitespace-nowrap">Template</span>
                </a>
            @endcan
            @can('settings.manage')
                <a href="{{ route('settings.index') }}"
                    class="flex items-center gap-3 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('settings.*') ? 'bg-blue-50 text-blue-700' : 'text-slate-700 hover:bg-slate-50' }}"
                    :class="sidebarOpen ? '' : 'justify-center'">
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.744c-.288.215-.438.56-.37.918.028.15.047.302.062.455.04.376.25.716.56.925l1.003.744c.453.336.564.954.26 1.43l-1.296 2.248a1.125 1.125 0 01-1.37.49l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.6 6.6 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.55 6.55 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.744c.288-.215.437-.56.37-.918a6.33 6.33 0 01-.062-.455c-.04-.376-.25-.716-.56-.925l-1.003-.744a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.369-.49l1.216.456c.356.133.751.072 1.076-.124.074-.044.147-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <span x-show="sidebarOpen" x-transition class="whitespace-nowrap">Pengaturan</span>
                </a>
            @endcan
        </div>
    </aside>

    <div x-show="mobileOpen" x-cloak x-transition.opacity @click="mobileOpen = false"
        class="fixed inset-0 z-40 bg-slate-900/40 md:hidden" aria-hidden="true"></div>

    <!-- Main content -->
    <div class="flex-1 flex flex-col min-w-0">
        <header class="flex h-16 items-center justify-between gap-3 border-b border-slate-200 bg-white px-4 sm:px-6">
            <div class="flex min-w-0 items-center gap-3">
                <button type="button" @click="mobileOpen = true" class="rounded-md p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-700 md:hidden"
                    aria-label="Buka menu" :aria-expanded="mobileOpen">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                    </svg>
                </button>
                <h1 class="truncate text-lg font-medium text-slate-900">{{ $heading ?? '' }}</h1>
            </div>
            <div class="flex shrink-0 items-center gap-3 sm:gap-4">
                <span class="hidden text-sm text-slate-500 sm:inline">{{ auth()->user()?->name }}</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-2 rounded-md bg-red-600 px-3 py-2 text-sm font-medium text-white transition-colors hover:bg-red-700">
                        <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" />
                        </svg>
                        <span class="hidden whitespace-nowrap sm:inline">Keluar</span>
                    </button>
                </form>
            </div>
        </header>

        <main id="main-content" class="flex-1 overflow-y-auto p-4 sm:p-6">
            <div class="mx-auto w-full max-w-7xl">
            @if (session('error'))
                <div class="mb-4 flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
                    <svg class="mt-0.5 h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                    </svg>
                    <p>{{ session('error') }}</p>
                </div>
            @elseif (session('status') || session('success'))
                <div class="mb-4 flex items-start gap-3 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800" role="status">
                    <svg class="mt-0.5 h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <p>{{ session('success') ?? session('status') }}</p>
                </div>
            @endif

            {{ $slot }}
            </div>
        </main>
    </div>

    <div
        x-data="{
            toasts: [],
            pushToast(event) {
                const detail = event.detail ?? {};
                if (! detail.message) return;
                const id = Date.now() + Math.random();
                this.toasts.push({ id, message: detail.message, type: detail.type ?? 'success' });
                setTimeout(() => this.dismiss(id), 5000);
            },
            dismiss(id) {
                this.toasts = this.toasts.filter((toast) => toast.id !== id);
            }
        }"
        x-on:notify.window="pushToast($event)"
        class="pointer-events-none fixed inset-x-0 top-4 z-[70] flex flex-col items-center gap-2 px-4 sm:items-end sm:pr-6"
        aria-live="polite"
        aria-atomic="false"
    >
        <template x-for="toast in toasts" :key="toast.id">
            <div
                class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-lg border px-4 py-3 shadow-lg"
                :class="{
                    'border-green-200 bg-green-50 text-green-800': toast.type === 'success',
                    'border-red-200 bg-red-50 text-red-800': toast.type === 'error',
                    'border-amber-200 bg-amber-50 text-amber-800': toast.type === 'warning',
                    'border-blue-200 bg-blue-50 text-blue-800': toast.type === 'info'
                }"
                role="status"
            >
                <p class="flex-1 text-sm" x-text="toast.message"></p>
                <button type="button" @click="dismiss(toast.id)" class="shrink-0 text-current opacity-60 hover:opacity-100" aria-label="Tutup notifikasi">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </template>
    </div>
</div>
@livewireScripts
</body>
</html>