<div class="space-y-6" @if ($status === App\Domain\Campaigns\Enums\CampaignStatus::Sending) wire:poll.3s @endif>
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <h2 class="text-lg font-semibold text-slate-900">{{ $campaign->name }}</h2>
                <x-badge :status="strtoupper($status->value)" :label="$status->label()" />
            </div>
            <p class="mt-1 text-sm text-slate-500">
                @if ($campaign->channel === App\Domain\Campaigns\Enums\ProposalChannel::WhatsApp)
                    {{ $campaign->messageTemplate?->name ?? 'Template pesan tidak tersedia' }}
                    @if ($campaign->proposalTemplate)
                        <span class="text-slate-400">· {{ $campaign->proposalTemplate->name }}</span>
                    @endif
                @else
                    {{ $campaign->template?->name ?? 'Template email tidak tersedia' }}
                @endif
            </p>
        </div>
        <a href="{{ route('campaigns.index') }}" wire:navigate class="text-sm text-slate-600 hover:text-slate-900">Kembali</a>
    </div>

    @error('campaign')
        <div class="rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">{{ $message }}</div>
    @enderror

    <div class="flex flex-wrap gap-2">
        @if ($status === App\Domain\Campaigns\Enums\CampaignStatus::Draft)
            <button type="button" wire:click="requestApproval" wire:loading.attr="disabled" wire:target="requestApproval"
                class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60">
                <span wire:loading.remove wire:target="requestApproval">Ajukan approval</span>
                <span wire:loading wire:target="requestApproval">Mengajukan...</span>
            </button>
        @endif

        @if ($status === App\Domain\Campaigns\Enums\CampaignStatus::PendingApproval)
            @can('approve', App\Domain\Campaigns\Models\Campaign::class)
                <button type="button" wire:click="approve" wire:loading.attr="disabled" wire:target="approve"
                    class="rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:cursor-not-allowed disabled:opacity-60">
                    <span wire:loading.remove wire:target="approve">Setujui campaign</span>
                    <span wire:loading wire:target="approve">Menyetujui...</span>
                </button>
            @endcan
        @endif

        @if ($status === App\Domain\Campaigns\Enums\CampaignStatus::Approved)
            @can('send', App\Domain\Campaigns\Models\Campaign::class)
                <button type="button" wire:click="generateDrafts" wire:confirm="Buat draft email untuk seluruh penerima?"
                    wire:loading.attr="disabled" wire:target="generateDrafts"
                    class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60">
                    <span wire:loading.remove wire:target="generateDrafts">Buat draft email</span>
                    <span wire:loading wire:target="generateDrafts">Memproses...</span>
                </button>
            @endcan
        @endif
    </div>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <div class="rounded-lg border border-slate-200 bg-white px-4 py-3">
            <div class="text-xs text-slate-500">Total penerima</div>
            <div class="mt-1 text-lg font-semibold text-slate-900">{{ $recipients->count() }}</div>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white px-4 py-3">
            <div class="text-xs text-slate-500">Draft dibuat</div>
            <div class="mt-1 text-lg font-semibold text-green-700">{{ $recipients->where('status', 'draft_created')->count() }}</div>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white px-4 py-3">
            <div class="text-xs text-slate-500">Dilewati</div>
            <div class="mt-1 text-lg font-semibold text-amber-700">{{ $recipients->where('status', 'skipped')->count() }}</div>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white px-4 py-3">
            <div class="text-xs text-slate-500">Gagal</div>
            <div class="mt-1 text-lg font-semibold text-red-700">{{ $recipients->where('status', 'failed')->count() }}</div>
        </div>
    </div>

    <div class="bg-white border border-slate-200 rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
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
                            <td class="px-4 py-3">
                                <div class="text-slate-900">{{ $recipient->contact->full_name }}</div>
                                <div class="text-xs text-slate-500">{{ $recipient->contact->company?->name }}</div>
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ $recipient->contact->normalized_email ?? '-' }}</td>
                            <td class="px-4 py-3">
                                <x-badge :status="strtoupper($recipient->status)" :label="ucfirst(str_replace('_', ' ', $recipient->status))" />
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-500">
                                {{ $recipient->provider_draft_id ?? $recipient->error ?? '-' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-10 text-center text-slate-500">
                                <p class="font-medium text-slate-700">Tidak ada penerima.</p>
                                <p class="mt-1">Campaign ini belum memiliki penerima yang valid.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <p class="text-xs text-slate-500">
        Draft dibuat di Gmail (atau provider fake saat lokal). Pengiriman otomatis tidak dilakukan; draft harus dikirim manual dari Gmail setelah ditinjau.
    </p>
</div>
