<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Prospectra') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-slate-50 text-slate-900 min-h-screen">
    <div class="min-h-screen flex flex-col md:flex-row">
        <!-- Left: Decorative panel -->
        <div class="hidden md:flex flex-1 items-center justify-center bg-slate-800 relative overflow-hidden">
            <div class="relative text-center px-8">
                <div class="inline-flex items-center justify-center rounded-2xl bg-white p-4 shadow-lg mb-6">
                    <img src="{{ asset('image/logo.png') }}" alt="Prospectra" class="h-24 w-24" />
                </div>
                <h1 class="text-3xl font-bold text-white mb-2">{{ config('app.name') }}</h1>
                <p class="text-slate-300 text-sm max-w-xs mx-auto leading-relaxed">
                    Platform internal untuk mengelola prospek B2B.
                </p>
            </div>
        </div>

        <!-- Right: Login form -->
        <div class="flex-1 flex items-center justify-center p-6 md:p-12 bg-white">
            <div class="w-full max-w-sm">
                {{ $slot }}
            </div>
        </div>
    </div>
    @livewireScripts
</body>
</html