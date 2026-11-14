@props(['label' => '', 'placement' => 'bottom-start', 'width' => 'w-48'])

<div x-data="{ open: false }" class="relative inline-block text-left">
    <button
        @click="open = !open"
        @click.outside="open = false"
        @keydown.escape.window="open = false"
        type="button"
        class="inline-flex items-center gap-2 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
    >
        {{ $label }}
        <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
        </svg>
    </button>

    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="transform opacity-0 scale-95"
        x-transition:enter-end="transform opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="transform opacity-100 scale-100"
        x-transition:leave-end="transform opacity-0 scale-95"
        x-cloak
        class="absolute z-50 mt-2 {{ $width }} origin-top-right rounded-md bg-white shadow-lg ring-1 ring-black/5 focus:outline-none"
        style="display: none;"
    >
        <div class="py-1" role="menu">
            {{ $slot }}
        </div>
    </div>
</div>
