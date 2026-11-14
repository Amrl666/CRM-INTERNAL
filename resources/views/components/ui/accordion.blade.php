@props(['title' => '', 'open' => false])

<div x-data="{ open: {{ $open ? 'true' : 'false' }} }" class="rounded-md border border-slate-200">
    <button
        @click="open = !open"
        type="button"
        class="flex w-full items-center justify-between px-4 py-3 text-left text-sm font-medium text-slate-700 hover:bg-slate-50"
    >
        <span>{{ $title }}</span>
        <svg
            class="h-4 w-4 text-slate-400 transition-transform duration-200"
            :class="{ 'rotate-180': open }"
            fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
        >
            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
        </svg>
    </button>

    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 -translate-y-1"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 -translate-y-1"
        x-cloak
        class="border-t border-slate-200 px-4 py-3 text-sm text-slate-600"
        style="display: none;"
    >
        {{ $slot }}
    </div>
</div>
