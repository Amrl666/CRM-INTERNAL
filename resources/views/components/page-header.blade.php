<div {{ $attributes->merge(['class' => 'mb-6']) }}>
    <div class="flex items-start justify-between gap-4">
        <div>
            <h1 class="text-lg font-semibold text-slate-900">{{ $title }}</h1>
            @isset($description)
                <p class="mt-1 text-sm text-slate-500">{{ $description }}</p>
            @endisset
        </div>
        <div class="flex flex-shrink-0 items-center gap-3">
            {{ $actions ?? '' }}
        </div>
    </div>
</div>
