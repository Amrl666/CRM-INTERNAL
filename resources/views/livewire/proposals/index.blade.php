<div class="space-y-6">
    <x-page-header title="Outreach" description="Kirim outreach terpersonalisasi dengan approval, jadwal bisnis, dan batas pengiriman per kanal.">
        <x-slot:actions>
            @can('create', App\Domain\Campaigns\Models\Campaign::class)
                <a href="{{ route('proposals.create') }}" wire:navigate
                    class="rounded-md bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800">
                    Buat proposal campaign
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
        @foreach ([
            ['label' => 'Terjadwal', 'value' => $monitoring['scheduled'], 'tone' => 'bg-blue-50 text-blue-700'],
            ['label' => 'Diproses', 'value' => $monitoring['processing'], 'tone' => 'bg-amber-50 text-amber-700'],
            ['label' => 'Terkirim', 'value' => $monitoring['sent'], 'tone' => 'bg-green-50 text-green-700'],
            ['label' => 'Gagal', 'value' => $monitoring['failed'], 'tone' => 'bg-red-50 text-red-700'],
            ['label' => 'Tidak diketahui', 'value' => $monitoring['unknown'], 'tone' => 'bg-slate-100 text-slate-700'],
            ['label' => 'Tidak eligible', 'value' => $monitoring['ineligible'], 'tone' => 'bg-slate-50 text-slate-500'],
        ] as $stat)
            <div class="rounded-lg border border-slate-200 bg-white px-4 py-3">
                <div class="text-xs text-slate-500">{{ $stat['label'] }}</div>
                <div class="mt-1 text-xl font-semibold text-slate-900 tabular-nums">{{ $stat['value'] }}</div>
            </div>
        @endforeach
    </div>

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-slate-500">
                    <tr>
                        <th class="px-4 py-3 font-medium">Nama</th>
                        <th class="px-4 py-3 font-medium">Channel</th>
                        <th class="px-4 py-3 font-medium">Template</th>
                        <th class="px-4 py-3 font-medium text-right">Penerima</th>
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
                            <td class="px-4 py-3 text-slate-600">{{ $campaign->channel?->label() ?? '-' }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $campaign->proposalTemplate?->name ?? '-' }} v{{ $campaign->proposal_template_version }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-slate-700">{{ $campaign->recipients_count }}</td>
                            <td class="px-4 py-3">
                                <x-badge :status="strtoupper($campaign->status->value)" :label="$campaign->status->label()" />
                            </td>
                            <td class="px-4 py-3 text-slate-500">{{ $campaign->created_at?->translatedFormat('d M Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-12 text-center">
                                <p class="font-medium text-slate-700">Belum ada proposal campaign.</p>
                                <p class="mt-1 text-sm text-slate-500">Buat campaign untuk menghasilkan PDF proposal per penerima dan menjadwalkan pengiriman.</p>
                                @can('create', App\Domain\Campaigns\Models\Campaign::class)
                                    <a href="{{ route('proposals.create') }}" wire:navigate
                                        class="mt-4 inline-block rounded-md bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800">
                                        Buat proposal campaign
                                    </a>
                                @endcan
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{ $campaigns->links() }}
</div>
