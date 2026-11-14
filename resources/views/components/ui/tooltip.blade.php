@props(['text' => '', 'position' => 'top'])

<div x-data="{ show: false }" class="relative inline-block">
    <span
        @mouseenter="show = true"
        @mouseleave="show = false"
        @focus="show = true"
        @blur="show = false"
        class="inline-flex"
    >
        {{ $slot }}
    </span>

    <div
        x-show="show"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        x-cloak
        class="absolute z-50 w-64 rounded-md bg-slate-900 px-3 py-2 text-xs text-white shadow-lg"
        style="display: none;"
        :class="{
            'bottom-full left-1/2 -translate-x-1/2 mb-2': '{{ $position }}' === 'top',
            'top-full left-1/2 -translate-x-1/2 mt-2': '{{ $position }}' === 'bottom',
            'right-full top-1/2 -translate-y-1/2 mr-2': '{{ $position }}' === 'left',
            'left-full top-1/2 -translate-y-1/2 ml-2': '{{ $position }}' === 'right',
        }"
    >
        {{ $text ?: $slot }}
        <div
            class="absolute h-2 w-2 rotate-45 bg-slate-900"
            :class="{
                'bottom-[-4px] left-1/2 -translate-x-1/2': '{{ $position }}' === 'top',
                'top-[-4px] left-1/2 -translate-x-1/2': '{{ $position }}' === 'bottom',
                'right-[-4px] top-1/2 -translate-y-1/2': '{{ $position }}' === 'left',
                'left-[-4px] top-1/2 -translate-y-1/2': '{{ $position }}' === 'right',
            }"
        ></div>
    </div>
</div>
