<div class="space-y-6">
    <x-page-header title="Tugas & follow-up" description="Kelola tugas yang menunggak, hari ini, dan sudah selesai.">
        <x-slot name="actions">
            <label for="task-filter" class="sr-only">Filter status tugas</label>
            <select id="task-filter" wire:model.live="filterStatus"
                class="rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                <option value="all">Semua tugas</option>
                <option value="pending">Menunggu / dikerjakan</option>
                <option value="completed">Selesai</option>
            </select>
        </x-slot>
    </x-page-header>

    @error('form')
        <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{{ $message }}</div>
    @enderror

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-1">
            <x-card>
                <h3 class="text-sm font-semibold text-slate-900">Buat tugas</h3>
                <p class="mt-1 text-xs text-slate-500">Tugas akan ditugaskan kepada Anda.</p>

                <form wire:submit="create" class="mt-3 space-y-3">
                    <div>
                        <label for="task-title" class="block text-xs font-medium text-slate-700">
                            Judul tugas <span class="text-red-600" aria-hidden="true">*</span>
                        </label>
                        <input id="task-title" type="text" wire:model="title" required maxlength="255" placeholder="Contoh: Follow up proposal"
                            @error('title') aria-invalid="true" aria-describedby="task-title-error" @enderror
                            class="mt-1 w-full rounded-md border px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 @error('title') border-red-400 @else border-slate-300 @enderror"/>
                        @error('title')
                            <p id="task-title-error" class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="task-lead" class="block text-xs font-medium text-slate-700">Lead terkait</label>
                        <select id="task-lead" wire:model="leadId"
                            @error('leadId') aria-invalid="true" aria-describedby="task-lead-error" @enderror
                            class="mt-1 w-full rounded-md border px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 @error('leadId') border-red-400 @else border-slate-300 @enderror">
                            <option value="">Tidak terkait lead</option>
                            @foreach ($leads as $leadOption)
                                <option value="{{ $leadOption->id }}">{{ $leadOption->company->name }}</option>
                            @endforeach
                        </select>
                        @error('leadId')
                            <p id="task-lead-error" class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <label for="task-priority" class="block text-xs font-medium text-slate-700">Prioritas</label>
                            <select id="task-priority" wire:model="priority"
                                class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                                <option value="low">Rendah</option>
                                <option value="medium">Sedang</option>
                                <option value="high">Tinggi</option>
                            </select>
                            @error('priority')
                                <p class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="task-due" class="block text-xs font-medium text-slate-700">Jatuh tempo</label>
                            <input id="task-due" type="date" wire:model="due_at"
                                @error('due_at') aria-invalid="true" aria-describedby="task-due-error" @enderror
                                class="mt-1 w-full rounded-md border px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 @error('due_at') border-red-400 @else border-slate-300 @enderror"/>
                            @error('due_at')
                                <p id="task-due-error" class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <button type="submit" wire:loading.attr="disabled" wire:target="create"
                        class="w-full rounded-md bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-60">
                        <span wire:loading.remove wire:target="create">Simpan tugas</span>
                        <span wire:loading wire:target="create">Menyimpan...</span>
                    </button>
                </form>
            </x-card>
        </div>

        <div class="lg:col-span-2 space-y-3" wire:loading.class="opacity-60" wire:target="filterStatus">
            @forelse ($tasks as $task)
                <x-card class="py-4" wire:key="task-{{ $task->id }}">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0 space-y-1">
                            <div class="font-medium text-slate-900">{{ $task->title }}</div>
                            <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-slate-500">
                                <span class="rounded-full px-2 py-0.5 font-medium {{ $task->priority === 'high' ? 'bg-red-50 text-red-700' : ($task->priority === 'low' ? 'bg-slate-100 text-slate-600' : 'bg-amber-50 text-amber-700') }}">
                                    Prioritas {{ $task->priority === 'high' ? 'tinggi' : ($task->priority === 'low' ? 'rendah' : 'sedang') }}
                                </span>
                                @if ($task->lead)
                                    <span>Lead: {{ $task->lead->company->name }}</span>
                                @endif
                                @if ($task->deal)
                                    <span>Deal: {{ $task->deal->title }}</span>
                                @endif
                            </div>
                        </div>
                        <div class="shrink-0 text-right space-y-2">
                            <x-badge status="{{ $task->status }}" />
                            @if ($task->due_at)
                                <div class="text-xs {{ $task->isOverdue() ? 'font-medium text-red-600' : 'text-slate-500' }}">
                                    {{ $task->isOverdue() ? 'Terlambat: ' : 'Jatuh tempo: ' }}{{ $task->due_at->translatedFormat('d M Y') }}
                                </div>
                            @endif
                            @if ($task->status !== 'completed')
                                <button type="button" wire:click="complete('{{ $task->id }}')"
                                    wire:loading.attr="disabled" wire:target="complete"
                                    class="rounded-md border border-slate-300 px-3 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60">
                                    <span wire:loading.remove wire:target="complete">Tandai selesai</span>
                                    <span wire:loading wire:target="complete">Menyimpan...</span>
                                </button>
                            @endif
                        </div>
                    </div>
                </x-card>
            @empty
                <div class="rounded-lg border border-dashed border-slate-300 bg-white px-6 py-12 text-center">
                    <p class="text-sm font-medium text-slate-700">Belum ada tugas.</p>
                    <p class="mt-1 text-sm text-slate-500">Buat tugas pertama Anda menggunakan formulir di samping.</p>
                </div>
            @endforelse
        </div>
    </div>
</div>
