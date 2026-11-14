<div class="max-w-5xl mx-auto space-y-6">
    <x-page-header title="Tugas & follow-up" description="Kelola tugas yang menunggak, hari ini, dan sudah selesai.">
        <x-slot name="actions">
            <select wire:model.live="filterStatus" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
                <option value="all">Semua</option>
                <option value="pending">Menunggu / dikerjakan</option>
                <option value="completed">Selesai</option>
            </select>
        </x-slot>
    </x-page-header>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-1">
            <x-card>
                <h3 class="text-sm font-semibold text-slate-900">Buat tugas</h3>
                <form wire:submit="create" class="mt-3 space-y-3">
                    <input type="text" wire:model.live="title" placeholder="Judul tugas" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"/>
                    @error('title') <div class="text-xs text-red-600">{{ $message }}</div> @enderror

                    <select wire:model.live="leadId" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <option value="">Tidak terkait lead</option>
                        @foreach ($leads as $leadOption)
                            <option value="{{ $leadOption->id }}">{{ $leadOption->company->name }}</option>
                        @endforeach
                    </select>

                    <select wire:model.live="priority" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <option value="low">Rendah</option>
                        <option value="medium" @selected($priority === 'medium')>Sedang</option>
                        <option value="high">Tinggi</option>
                    </select>

                    <button type="submit" class="w-full rounded-md bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800">Simpan tugas</button>
                </form>
            </x-card>
        </div>

        <div class="lg:col-span-2 space-y-3">
            @forelse ($tasks as $task)
                <x-card class="py-4">
                    <div class="flex items-start justify-between gap-4">
                        <div class="space-y-1">
                            <div class="font-medium text-slate-900">{{ $task->title }}</div>
                            <div class="text-xs text-slate-500">
                                Prioritas: {{ $task->priority }} &middot;
                                @if ($task->lead) Lead: {{ $task->lead->company->name }} @endif
                                @if ($task->deal) Deal: {{ $task->deal->title }} @endif
                            </div>
                        </div>
                        <div class="text-right space-y-2">
                            <x-badge status="{{ $task->status }}" />
                            @if ($task->due_at)
                                <div class="text-xs {{ $task->isOverdue() ? 'text-red-600' : 'text-slate-500' }}">
                                    Jatuh tempo: {{ $task->due_at->translatedFormat('d M Y H:i') }}
                                </div>
                            @endif
                            @if ($task->status !== 'completed')
                                <button wire:click="complete('{{ $task->id }}')" class="rounded-md border border-slate-300 px-3 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50">Tandai selesai</button>
                            @endif
                        </div>
                    </div>
                </x-card>
            @empty
                <div class="rounded-lg border border-slate-200 bg-white py-12 text-center text-sm text-slate-500">Belum ada tugas.</div>
            @endforelse
        </div>
    </div>
</div>
