@props(['status' => '', 'label' => null, 'testId' => ''])

@php
    $mapping = [
        'GRANTED' => ['bg-green-100', 'text-green-800'],
        'REVOKED' => ['bg-red-100', 'text-red-800'],
        'UNKNOWN' => ['bg-slate-100', 'text-slate-600'],
        'DRAFT' => ['bg-slate-100', 'text-slate-700'],
        'PENDING' => ['bg-blue-50', 'text-blue-700'],
        'APPROVED' => ['bg-green-50', 'text-green-700'],
        'SENT' => ['bg-green-100', 'text-green-800'],
        'FAILED' => ['bg-red-100', 'text-red-800'],
        'SKIPPED' => ['bg-amber-100', 'text-amber-800'],
        'NEW' => ['bg-slate-100', 'text-slate-700'],
        'RESEARCHING' => ['bg-slate-100', 'text-slate-600'],
        'QUALIFIED' => ['bg-green-50', 'text-green-700'],
        'ASSIGNED' => ['bg-blue-50', 'text-blue-700'],
        'CONTACTED' => ['bg-blue-50', 'text-blue-700'],
        'INTERESTED' => ['bg-green-50', 'text-green-700'],
        'MEETING' => ['bg-green-50', 'text-green-700'],
        'PROPOSAL' => ['bg-sky-50', 'text-sky-700'],
        'NEGOTIATION' => ['bg-sky-50', 'text-sky-700'],
        'WON' => ['bg-green-100', 'text-green-800'],
        'LOST' => ['bg-red-100', 'text-red-800'],
        'OPEN' => ['bg-blue-50', 'text-blue-700'],
        'COMPLETED' => ['bg-slate-100', 'text-slate-600'],
        'CANCELLED' => ['bg-slate-100', 'text-slate-500'],
        'REVIEW' => ['bg-amber-50', 'text-amber-700'],
        'SCHEDULED' => ['bg-purple-50', 'text-purple-700'],
        'RUNNING' => ['bg-blue-100', 'text-blue-800'],
        'PAUSED' => ['bg-amber-100', 'text-amber-800'],
        'GENERATING' => ['bg-blue-50', 'text-blue-700'],
        'READY' => ['bg-green-50', 'text-green-700'],
        'PROCESSING' => ['bg-blue-50', 'text-blue-700'],
        'ACCEPTED' => ['bg-green-100', 'text-green-800'],
        'DELIVERED' => ['bg-green-100', 'text-green-800'],
        'ELIGIBLE' => ['bg-green-50', 'text-green-700'],
        'INELIGIBLE' => ['bg-red-50', 'text-red-700'],
    ];

    $pair = $mapping[strtoupper($status)] ?? ['bg-slate-100', 'text-slate-600'];
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full px-2 py-1 text-xs font-medium {$pair[0]} {$pair[1]}"]) }} @if ($testId !== '') data-testid="{{ $testId }}" @endif>
    {{ $label ?? $status }}
</span>
