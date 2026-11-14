@props(['default' => ''])

<div x-data="{ activeTab: '{{ $default }}' }" class="w-full">
    <!-- Tab list -->
    <div class="border-b border-slate-200">
        <nav class="-mb-px flex space-x-6" aria-label="Tabs">
            {{ $tabs ?? '' }}
        </nav>
    </div>

    <!-- Tab panels -->
    <div class="mt-4">
        {{ $slot }}
    </div>
</div>
