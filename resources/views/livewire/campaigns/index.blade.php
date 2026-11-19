<div class="space-y-6">
    <x-page-header title="Campaign" description="Kelola campaign email, penerima, dan status persetujuannya.">
        <x-slot:actions>
            @can('create', App\Domain\Campaigns\Models\Campaign::class)
                <a href="{{ route('campaigns.create') }}" wire:navigate
                    class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                    Buat campaign
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="bg-white border border-slate-200 rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-slate-500">
                    <tr>
                        <th class="px-4 py-3 font-medium">Nama</th>
                        <th class="px-4 py-3 font-medium">Template</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3 font-medium">Dibuat</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($campaigns as $campaign)
                        <tr wire:key="campaign-{{ $campaign->id }}">
                            <td class="px-4 py-3">
                                <a href="{{ route('campaigns.show', $campaign) }}" wire:navigate class="font-medium text-slate-900 hover:text-blue-600">
                                    {{ $campaign->name }}
                                </a>
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ $campaign->template?->name ?? 'Template tidak tersedia' }}</td>
                            <td class="px-4 py-3">
                                <x-badge :status="strtoupper($campaign->status->value)" :label="$campaign->status->label()" />
                            </td>
                            <td class="px-4 py-3 text-slate-500">{{ $campaign->created_at->translatedFormat('d M Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-12 text-center">
                                <p class="font-medium text-slate-700">Belum ada campaign.</p>
                                <p class="mt-1 text-sm text-slate-500">Buat campaign email pertama untuk mulai menjangkau lead.</p>
                                @can('create', App\Domain\Campaigns\Models\Campaign::class)
                                    <a href="{{ route('campaigns.create') }}" wire:navigate
                                        class="mt-4 inline-block rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                                        Buat campaign
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
