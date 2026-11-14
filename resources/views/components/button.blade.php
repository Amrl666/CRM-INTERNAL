@props(['variant' => 'secondary', 'type' => 'button', 'testId' => ''])

<button
    {{ $attributes->merge(['type' => $type]) }}
    @if ($testId !== '') data-testid="{{ $testId }}" @endif
    class="inline-flex items-center gap-2 rounded-md px-4 py-2 text-sm font-medium transition-colors disabled:cursor-not-allowed disabled:opacity-50 @if ($variant === 'primary') bg-blue-700 text-white hover:bg-blue-800 @elseif ($variant === 'danger') bg-red-700 text-white hover:bg-red-800 @elseif ($variant === 'ghost') text-slate-600 hover:bg-slate-50 hover:text-slate-900 @else border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 @endif"
>
    {{ $slot }}
</button>
