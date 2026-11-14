<div class="space-y-6">
    <div class="flex items-center justify-between gap-4">
        <select wire:model.live="pipelineId" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
            @foreach ($pipelines as $pipelineOption)
                <option value="{{ $pipelineOption->id }}">{{ $pipelineOption->name }}</option>
            @endforeach
        </select>
        @can('create', App\Domain\Deals\Models\Deal::class)
            <a href="{{ route('deals.create') }}" wire:navigate
                class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                Tambah deal
            </a>
        @endcan
    </div>

    @if ($pipeline === null)
        <div class="bg-white border border-slate-200 rounded-lg p-8 text-center text-slate-500">
            Belum ada pipeline. Jalankan <code class="bg-slate-100 px-1">php artisan db:seed</code>.
        </div>
    @else
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            @foreach ($stages as $stage)
                <div class="bg-slate-50 border border-slate-200 rounded-lg p-3" wire:key="stage-{{ $stage->id }}">
                    <div class="flex items-center justify-between text-sm font-medium text-slate-700">
                        <span>{{ $stage->name }}</span>
                        <span class="text-xs text-slate-500">{{ ($deals[$stage->id] ?? collect())->count() }}</span>
                    </div>

                    <div class="mt-3 space-y-2">
                        @foreach ($deals[$stage->id] ?? [] as $deal)
                            <div class="bg-white border border-slate-200 rounded-md p-3 text-sm space-y-1" wire:key="deal-{{ $deal->id }}">
                                <div class="font-medium text-slate-900">{{ $deal->title }}</div>
                                <div class="text-xs text-slate-500">{{ $deal->company->name }}</div>
                                <div class="text-slate-700">Rp {{ number_format((float) $deal->value, 0, ',', '.') }}</div>

                                @can('update', $deal)
                                    <select wire:change="move('{{ $deal->id }}', $event.target.value)"
                                        class="mt-1 w-full rounded border border-slate-300 px-2 py-1 text-xs">
                                        @foreach ($stages as $targetStage)
                                            <option value="{{ $targetStage->id }}" @selected($targetStage->id === $stage->id)>{{ $targetStage->name }}</option>
                                        @endforeach
                                    </select>
                                @endcan
                            </div>
                        @endforeach

                        @if (($deals[$stage->id] ?? collect())->isEmpty())
                            <div class="text-xs text-slate-400 text-center py-4">Kosong</div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
