<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <label for="pipeline-filter" class="text-sm font-medium text-slate-600">Pipeline</label>
            <select id="pipeline-filter" wire:model.live="pipelineId"
                class="rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                @foreach ($pipelines as $pipelineOption)
                    <option value="{{ $pipelineOption->id }}">{{ $pipelineOption->name }}</option>
                @endforeach
            </select>
        </div>
        @can('create', App\Domain\Deals\Models\Deal::class)
            <a href="{{ route('deals.create') }}" wire:navigate
                class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                Tambah deal
            </a>
        @endcan
    </div>

    @error('board')
        <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{{ $message }}</div>
    @enderror

    @if ($pipeline === null)
        <div class="rounded-lg border border-dashed border-slate-300 bg-white p-10 text-center">
            <p class="text-sm font-medium text-slate-700">Belum ada pipeline.</p>
            <p class="mt-1 text-sm text-slate-500">Hubungi administrator untuk menyiapkan pipeline dan stage deal.</p>
        </div>
    @else
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4" wire:loading.class="opacity-60" wire:target="pipelineId">
            @foreach ($stages as $stage)
                @php
                    $stageDeals = $deals[$stage->id] ?? collect();
                @endphp
                <div class="bg-slate-50 border border-slate-200 rounded-lg p-3" wire:key="stage-{{ $stage->id }}">
                    <div class="flex items-start justify-between gap-2 text-sm font-medium text-slate-700">
                        <div>
                            <div>{{ $stage->name }}</div>
                            <div class="mt-0.5 text-xs font-normal text-slate-500">
                                Rp {{ number_format((float) $stageDeals->sum('value'), 0, ',', '.') }}
                            </div>
                        </div>
                        <span class="rounded-full bg-white px-2 py-0.5 text-xs text-slate-500 ring-1 ring-slate-200">{{ $stageDeals->count() }}</span>
                    </div>

                    <div class="mt-3 space-y-2">
                        @foreach ($stageDeals as $deal)
                            <div class="bg-white border border-slate-200 rounded-md p-3 text-sm space-y-1" wire:key="deal-{{ $deal->id }}">
                                <div class="font-medium text-slate-900">{{ $deal->title }}</div>
                                <div class="text-xs text-slate-500">{{ $deal->company->name }}</div>
                                <div class="text-slate-700">Rp {{ number_format((float) $deal->value, 0, ',', '.') }}</div>

                                @can('update', $deal)
                                    <label for="deal-stage-{{ $deal->id }}" class="sr-only">Pindahkan {{ $deal->title }} ke stage</label>
                                    <select id="deal-stage-{{ $deal->id }}" wire:change="move('{{ $deal->id }}', $event.target.value)"
                                        wire:loading.attr="disabled" wire:target="move"
                                        class="mt-1 w-full rounded border border-slate-300 px-2 py-1 text-xs focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                                        @foreach ($stages as $targetStage)
                                            <option value="{{ $targetStage->id }}" @selected($targetStage->id === $stage->id)>{{ $targetStage->name }}</option>
                                        @endforeach
                                    </select>
                                @endcan
                            </div>
                        @endforeach

                        @if ($stageDeals->isEmpty())
                            <div class="py-4 text-center text-xs text-slate-400">Kosong</div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
