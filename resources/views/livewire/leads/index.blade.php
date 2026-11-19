<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex flex-wrap items-center gap-3">
            <div>
                <label for="lead-search" class="sr-only">Cari lead</label>
                <input id="lead-search" type="search" wire:model.live.debounce.300ms="search" placeholder="Cari perusahaan, kontak, email..."
                    class="w-72 rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"/>
            </div>
            <div>
                <label for="lead-status-filter" class="sr-only">Filter status lead</label>
                <select id="lead-status-filter" wire:model.live="status"
                    class="rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                    <option value="">Semua status</option>
                    @foreach ($statuses as $statusOption)
                        <option value="{{ $statusOption->value }}">{{ $statusOption->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="lead-priority-filter" class="sr-only">Filter prioritas lead</label>
                <select id="lead-priority-filter" wire:model.live="priority"
                    class="rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                    <option value="">Semua prioritas</option>
                    <option value="low">Rendah</option>
                    <option value="medium">Sedang</option>
                    <option value="high">Tinggi</option>
                </select>
            </div>
            @if ($owners->isNotEmpty())
                <div>
                    <label for="lead-owner-filter" class="sr-only">Filter pemilik lead</label>
                    <select id="lead-owner-filter" wire:model.live="owner"
                        class="rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                        <option value="">Semua pemilik</option>
                        @foreach ($owners as $ownerOption)
                            <option value="{{ $ownerOption->id }}">{{ $ownerOption->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            @if ($search !== '' || $status !== '' || $priority !== '' || $owner !== '')
                <button type="button" wire:click="clearFilters"
                    class="text-sm font-medium text-blue-700 hover:text-blue-800">
                    Reset filter
                </button>
            @endif
        </div>

        <div class="flex gap-2">
            @can('import', App\Domain\Leads\Models\Lead::class)
                <a href="{{ route('leads.import') }}" wire:navigate
                    class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Impor
                </a>
            @endcan
            @can('create', App\Domain\Leads\Models\Lead::class)
                <a href="{{ route('leads.create') }}" wire:navigate
                    class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                    Tambah lead
                </a>
            @endcan
        </div>
    </div>

    @error('archive')
        <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{{ $message }}</div>
    @enderror

    <div class="bg-white border border-slate-200 rounded-lg overflow-hidden" wire:loading.class="opacity-60" wire:target="search,status,priority,owner">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-slate-500">
                    <tr>
                        <th class="px-4 py-3 font-medium">Perusahaan</th>
                        <th class="px-4 py-3 font-medium">Kontak</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3 font-medium">Prioritas</th>
                        <th class="px-4 py-3 font-medium">Pemilik</th>
                        <th class="px-4 py-3 font-medium text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($leads as $lead)
                        <tr wire:key="lead-{{ $lead->id }}">
                            <td class="px-4 py-3">
                                <a href="{{ route('leads.show', $lead) }}" wire:navigate class="font-medium text-slate-900 hover:text-blue-600">
                                    {{ $lead->company->name }}
                                </a>
                                <div class="text-xs text-slate-500">{{ $lead->company->industry ?? '-' }}</div>
                            </td>
                            <td class="px-4 py-3 text-slate-600">
                                {{ $lead->contact?->full_name ?? '-' }}
                                <div class="text-xs text-slate-500">{{ $lead->contact?->normalized_email ?? $lead->contact?->phone_e164 ?? '-' }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="rounded-full bg-slate-100 px-2 py-1 text-xs text-slate-700">{{ $lead->status->label() }}</span>
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ ucfirst($lead->priority) }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $lead->owner?->name ?? '-' }}</td>
                            <td class="px-4 py-3 text-right space-x-3">
                                @can('update', $lead)
                                    <a href="{{ route('leads.edit', $lead) }}" wire:navigate class="text-sm text-blue-600 hover:text-blue-700">Ubah</a>
                                    <button type="button" wire:click="archive('{{ $lead->id }}')" wire:confirm="Arsipkan lead ini?"
                                        wire:loading.attr="disabled" wire:target="archive"
                                        class="text-sm text-red-600 hover:text-red-700 disabled:cursor-not-allowed disabled:opacity-60">
                                        Arsipkan
                                    </button>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center">
                                @if ($search !== '' || $status !== '' || $priority !== '' || $owner !== '')
                                    <p class="text-sm font-medium text-slate-700">Tidak ada lead yang cocok.</p>
                                    <p class="mt-1 text-sm text-slate-500">Coba ubah kata kunci atau reset filter.</p>
                                @else
                                    <p class="text-sm font-medium text-slate-700">Belum ada lead.</p>
                                    <p class="mt-1 text-sm text-slate-500">Tambahkan lead secara manual atau impor dari file CSV/XLSX.</p>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{ $leads->links() }}
</div>
