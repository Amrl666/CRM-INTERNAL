<div class="max-w-5xl mx-auto space-y-6" @if ($status === App\Domain\Campaigns\Enums\CampaignStatus::Sending) wire:poll.3s @endif>
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-lg font-semibold text-slate-900">{{ $campaign->name }}</h2>
            <p class="text-sm text-slate-500">{{ $campaign->template->name }} &middot; status: {{ $status->value }}</p>
        </div>
        <a href="{{ route('campaigns.index') }}" wire:navigate class="text-sm text-slate-600 hover:text-slate-900">Kembali</a>
    </div>

    @error('campaign')
        <div class="text-sm text-red-600">{{ $message }}</div>
    @enderror

    <div class="flex flex-wrap gap-2">
        @if ($status === App\Domain\Campaigns\Enums\CampaignStatus::Draft)
            <button wire:click="requestApproval" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                Ajukan approval
            </button>
        @endif

        @if ($status === App\Domain\Campaigns\Enums\CampaignStatus::PendingApproval)
            @can('approve', App\Domain\Campaigns\Models\Campaign::class)
                <button wire:click="approve" class="rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700">
                    Setujui campaign
                </button>
            @endcan
        @endif

        @if ($status === App\Domain\Campaigns\Enums\CampaignStatus::Approved)
            @can('send', App\Domain\Campaigns\Models\Campaign::class)
                <button wire:click="generateDrafts" wire:confirm="Buat draft email untuk seluruh penerima?"
                    class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                    Buat draft email
                </button>
            @endcan
        @endif
    </div>

    <div class="bg-white border border-slate-200 rounded-lg overflow-hidden">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-slate-500">
                <tr>
                    <th class="px-4 py-3 font-medium">Kontak</th>
                    <th class="px-4 py-3 font-medium">Email</th>
                    <th class="px-4 py-3 font-medium">Status</th>
                    <th class="px-4 py-3 font-medium">Catatan</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($recipients as $recipient)
                    <tr wire:key="recipient-{{ $recipient->id }}">
                        <td class="px-4 py-3 text-slate-900">{{ $recipient->contact->full_name }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $recipient->contact->normalized_email ?? '-' }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $recipient->status }}</td>
                        <td class="px-4 py-3 text-xs text-slate-500">
                            {{ $recipient->provider_draft_id ?? $recipient->error ?? '-' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-8 text-center text-slate-500">Tidak ada penerima.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <p class="text-xs text-slate-500">
        Draft dibuat di Gmail (atau provider fake saat lokal). Pengiriman otomatis tidak dilakukan; draft harus dikirim manual dari Gmail setelah ditinjau.
    </p>
</div>
