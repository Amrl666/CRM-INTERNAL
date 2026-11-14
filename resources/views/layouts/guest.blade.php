<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'B2B Outreach CRM') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-slate-50 text-slate-900 min-h-screen flex flex-col justify-center py-12 sm:px-6 lg:px-8">
    <div class="w-full max-w-md mx-auto">{{ $slot }}</div>
    @livewireScripts
</body>
</html>