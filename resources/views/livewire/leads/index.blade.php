<div class="max-w-6xl mx-auto space-y-6">
    @if (session('status'))
        <div class="text-sm text-green-700 bg-green-50 border border-green-200 rounded px-3 py-2">
            {{ session('status') }}
        </div>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex flex-wrap gap-3">
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari perusahaan, kontak, email..."
                class="w-72 rounded-md border border-slate-300 px-3 py-2 text-sm"/>
            <select wire:model.live="status" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
                <option value="">Semua status</option>
                @foreach ($statuses as $statusOption)
                    <option value="{{ $statusOption->value }}">{{ $statusOption->label() }}</option>
                @endforeach
            </select>
            <select wire:model.live="priority" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
                <option value="">Semua prioritas</option>
                <option value="low">Rendah</option>
                <option value="medium">Sedang</option>
                <option value="high">Tinggi</option>
            </select>
            @if ($owners->isNotEmpty())
                <select wire:model.live="owner" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Semua pemilik</option>
                    @foreach ($owners as $ownerOption)
                        <option value="{{ $ownerOption->id }}">{{ $ownerOption->name }}</option>
                    @endforeach
                </select>
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

    <div class="bg-white border border-slate-200 rounded-lg overflow-hidden">
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
                            <a href="{{ route('leads.edit', $lead) }}" wire:navigate class="text-sm text-blue-600 hover:text-blue-700">Ubah</a>
                            @can('update', $lead)
                                <button wire:click="archive('{{ $lead->id }}')" wire:confirm="Arsipkan lead ini?"
                                    class="text-sm text-red-600 hover:text-red-700">Arsipkan</button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-slate-500">Belum ada lead.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $leads->links() }}
</div>
