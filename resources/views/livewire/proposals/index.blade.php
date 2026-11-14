<div class="max-w-6xl mx-auto space-y-6">
    <x-page-header title="Proposal Campaign" description="Kirim proposal terpersonalisasi dengan approval, jadwal bisnis, dan batas pengiriman per kanal.">
        <x-slot:actions>
            @can('create', App\Domain\Campaigns\Models\Campaign::class)
                <a href="{{ route('proposals.create') }}" wire:navigate
                    class="rounded-md bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800">
                    Buat proposal campaign
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-slate-500">
                <tr>
                    <th class="px-4 py-3 font-medium">Nama</th>
                    <th class="px-4 py-3 font-medium">Channel</th>
                    <th class="px-4 py-3 font-medium">Template</th>
                    <th class="px-4 py-3 font-medium">Penerima</th>
                    <th class="px-4 py-3 font-medium">Status</th>
                    <th class="px-4 py-3 font-medium">Dibuat</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($campaigns as $campaign)
                    <tr wire:key="proposal-{{ $campaign->id }}">
                        <td class="px-4 py-3">
                            <a href="{{ route('proposals.show', $campaign) }}" wire:navigate class="font-medium text-slate-900 hover:text-blue-700">
                                {{ $campaign->name }}
                            </a>
                        </td>
                        <td class="px-4 py-3 text-slate-600">{{ $campaign->channel?->value ?? '-' }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $campaign->proposalTemplate?->name ?? '-' }} v{{ $campaign->proposal_template_version }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-slate-700">{{ $campaign->recipients_count }}</td>
                        <td class="px-4 py-3">
                            <x-badge :status="strtoupper($campaign->status->value)" :label="$campaign->status->label()" />
                        </td>
                        <td class="px-4 py-3 text-slate-500">{{ $campaign->created_at?->translatedFormat('d M Y H:i') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-12 text-center text-slate-500">
                            <p class="font-medium text-slate-700">Belum ada proposal campaign.</p>
                            <p class="mt-1">Buat campaign untuk menghasilkan PDF proposal per penerima dan menjadwalkan pengiriman.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $campaigns->links() }}
</div>
