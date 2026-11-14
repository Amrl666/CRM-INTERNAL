@props(['title' => '', 'value' => 0])

<div class="bg-white border border-slate-200 rounded-lg p-4">
    <p class="text-sm font-medium text-slate-500">{{ $title }}</p>
    <p class="text-2xl font-semibold text-slate-900 mt-1">{{ number_format($value) }}</p>
</div>