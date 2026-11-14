<div class="max-w-6xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <h2 class="text-lg font-semibold text-slate-900">Campaign</h2>
        @can('create', App\Domain\Campaigns\Models\Campaign::class)
            <a href="{{ route('campaigns.create') }}" wire:navigate
                class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                Buat campaign
            </a>
        @endcan
    </div>

    <div class="bg-white border border-slate-200 rounded-lg overflow-hidden">
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
                        <td class="px-4 py-3 text-slate-600">{{ $campaign->template->name }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $campaign->status->value }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $campaign->created_at->translatedFormat('d M Y H:i') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-8 text-center text-slate-500">Belum ada campaign.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $campaigns->links() }}
</div>
