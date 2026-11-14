<div class="max-w-6xl mx-auto space-y-6" @if ($polling) wire:poll.5s @endif>
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <h2 class="text-lg font-semibold text-slate-900">{{ $campaign->name }}</h2>
                <x-badge :status="strtoupper($campaign->status->value)" :label="$campaign->status->label()" />
            </div>
            <p class="mt-1 text-sm text-slate-500">
                {{ $campaign->channel?->label() }} · {{ $campaign->proposalTemplate?->name }} v{{ $campaign->proposal_template_version }} · revisi {{ $campaign->revision }}
            </p>
        </div>
        <a href="{{ route('proposals.index') }}" wire:navigate class="text-sm text-slate-600 hover:text-slate-900">Kembali</a>
    </div>

    @error('campaign')
        <div class="rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">{{ $message }}</div>
    @enderror

    @if ($campaign->channel?->value === 'WHATSAPP')
        <div class="rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            Pengiriman WhatsApp proposal memakai kapabilitas terpisah dan hanya berjalan jika administrator mengaktifkan <code>WHATSAPP_PROPOSAL_CAMPAIGN_ENABLED</code>. Mode WhatsApp umum tetap manual.
        </div>
    @endif

    <div class="flex flex-wrap gap-2">
        @if (in_array($campaign->status, [App\Domain\Campaigns\Enums\CampaignStatus::Draft, App\Domain\Campaigns\Enums\CampaignStatus::Review], true))
            @can('update', $campaign)
                <button wire:click="requestApproval" wire:loading.attr="disabled" data-testid="request-approval"
                    class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-50">
                    Ajukan approval
                </button>
            @endcan
        @endif

        @if ($campaign->status === App\Domain\Campaigns\Enums\CampaignStatus::Review)
            @can('approve', App\Domain\Campaigns\Models\Campaign::class)
                <button wire:click="approve" wire:confirm="Setujui seluruh snapshot campaign ini?" wire:loading.attr="disabled" data-testid="approve-campaign"
                    @disabled($isCreator)
                    class="rounded-md bg-green-700 px-4 py-2 text-sm font-medium text-white hover:bg-green-800 disabled:cursor-not-allowed disabled:opacity-50">
                    Setujui campaign
                </button>
                @if ($isCreator)
                    <p class="w-full text-xs text-amber-700">Pembuat campaign tidak dapat menyetujui campaign sendiri. Minta pengguna lain dengan izin approval.</p>
                @endif
            @endcan
        @endif

        @if ($campaign->status === App\Domain\Campaigns\Enums\CampaignStatus::Approved)
            @can('send', App\Domain\Campaigns\Models\Campaign::class)
                <button wire:click="schedule" wire:confirm="Jadwalkan pengiriman untuk seluruh penerima eligible?" data-testid="schedule-campaign"
                    class="rounded-md bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800">
                    Jadwalkan pengiriman
                </button>
            @endcan
        @endif

        @if (in_array($campaign->status, [App\Domain\Campaigns\Enums\CampaignStatus::Scheduled, App\Domain\Campaigns\Enums\CampaignStatus::Running], true))
            @can('send', App\Domain\Campaigns\Models\Campaign::class)
                <button wire:click="pause" wire:confirm="Jeda semua pengiriman berikutnya?" class="rounded-md border border-amber-300 bg-amber-50 px-4 py-2 text-sm font-medium text-amber-800 hover:bg-amber-100">
                    Jeda campaign
                </button>
            @endcan
        @endif

        @if ($campaign->status === App\Domain\Campaigns\Enums\CampaignStatus::Paused)
            @can('send', App\Domain\Campaigns\Models\Campaign::class)
                <button wire:click="resume" wire:confirm="Lanjutkan campaign dan jadwalkan ulang sisa pengiriman?" class="rounded-md bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800">
                    Lanjutkan campaign
                </button>
            @endcan
        @endif

        @if (! in_array($campaign->status, [App\Domain\Campaigns\Enums\CampaignStatus::Completed, App\Domain\Campaigns\Enums\CampaignStatus::Cancelled], true))
            @can('cancel', App\Domain\Campaigns\Models\Campaign::class)
                <div class="flex items-center gap-2">
                    <input type="text" wire:model="cancelReason" placeholder="Alasan pembatalan (opsional)" class="rounded-md border border-slate-300 px-3 py-2 text-sm"/>
                    <button wire:click="cancel" wire:confirm="Batalkan campaign dan seluruh pengiriman yang tersisa?" class="rounded-md bg-red-700 px-4 py-2 text-sm font-medium text-white hover:bg-red-800">
                        Batalkan campaign
                    </button>
                </div>
            @endcan
        @endif
    </div>

    <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
        <x-stat-card title="Total penerima" :value="array_sum($counts)" />
        <x-stat-card title="Eligible" :value="$eligibleCount" />
        <x-stat-card title="Tidak eligible" :value="$ineligibleCount" />
        <x-stat-card title="PDF siap" :value="$pdfReady" />
        <x-stat-card title="Menunggu PDF" :value="$pdfPending" />
        <x-stat-card title="PDF gagal" :value="$pdfFailed" />
        <x-stat-card title="Terjadwal" :value="$counts['SCHEDULED']" />
        <x-stat-card title="Diproses" :value="$counts['PROCESSING']" />
        <x-stat-card title="Diterima provider" :value="$counts['ACCEPTED']" />
        <x-stat-card title="Terkirim" :value="$counts['SENT']" />
        <x-stat-card title="Tidak diketahui" :value="$counts['UNKNOWN']" />
        <x-stat-card title="Gagal" :value="$counts['FAILED']" />
        <x-stat-card title="Dilewati" :value="$counts['SKIPPED']" />
        <x-stat-card title="Dibatalkan" :value="$counts['CANCELLED']" />
    </div>

    <div class="grid gap-4 md:grid-cols-2">
        <div class="rounded-lg border border-slate-200 bg-white p-5">
            <h3 class="font-medium text-slate-900">Ringkasan approval</h3>
            <dl class="mt-3 space-y-2 text-sm">
                <div class="flex justify-between gap-4"><dt class="text-slate-500">Pembuat</dt><dd class="text-slate-900">{{ $campaign->created_by }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-slate-500">Approver</dt><dd class="text-slate-900">{{ $campaign->approved_by ?? '—' }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-slate-500">Revisi disetujui</dt><dd class="text-slate-900">{{ $campaign->approved_revision ?? '—' }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-slate-500">Pengiriman berikutnya</dt><dd class="text-right text-slate-900">{{ $nextDelivery ? \Illuminate\Support\Carbon::parse($nextDelivery)->timezone($campaign->timezone)->translatedFormat('d M Y H:i') : '—' }}</dd></div>
            </dl>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white p-5">
            <h3 class="font-medium text-slate-900">Penerima diblokir</h3>
            @forelse ($eligibilityReasons as $reason => $total)
                <div class="mt-2 flex items-start justify-between gap-4 text-sm">
                    <span class="text-slate-600">{{ $reason ?: 'Alasan tidak tercatat' }}</span>
                    <span class="tabular-nums font-medium text-slate-900">{{ $total }}</span>
                </div>
            @empty
                <p class="mt-2 text-sm text-slate-500">Tidak ada penerima yang diblokir pada pemeriksaan terakhir.</p>
            @endforelse
        </div>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-5">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h3 class="font-medium text-slate-900">Pratinjau pesan</h3>
                <p class="mt-1 text-sm text-slate-500">Menggunakan data penerima nyata yang dirender sebelum approval.</p>
            </div>
            @if ($preview)
                <span class="text-xs text-slate-500">{{ $preview->proposal_number }}</span>
            @endif
        </div>

        @if ($preview)
            <div class="mt-4 grid gap-4 md:grid-cols-2">
                <div class="rounded-md border border-slate-200 bg-slate-50 p-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Subjek</p>
                    <p class="mt-1 text-sm font-medium text-slate-900">{{ $preview->rendered_subject }}</p>
                    <p class="mt-4 text-xs font-medium uppercase tracking-wide text-slate-500">Isi teks</p>
                    <div class="mt-1 max-h-64 overflow-y-auto whitespace-pre-wrap text-sm text-slate-700">{{ $preview->rendered_body_text }}</div>
                </div>
                <div class="rounded-md border border-slate-200 bg-slate-50 p-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">PDF proposal</p>
                    @if ($preview->pdf_status === App\Domain\Campaigns\Enums\PdfStatus::Ready)
                        <iframe src="{{ route('proposals.recipients.preview', [$campaign, $preview]) }}" title="Pratinjau PDF proposal {{ $preview->proposal_number }}" class="mt-2 h-72 w-full rounded border border-slate-200 bg-white"></iframe>
                        <a href="{{ route('proposals.recipients.download', [$campaign, $preview]) }}" class="mt-2 inline-block text-sm text-blue-700 hover:underline">Unduh PDF</a>
                    @else
                        <p class="mt-2 text-sm text-slate-500">PDF belum siap untuk penerima ini.</p>
                    @endif
                </div>
            </div>
        @else
            <p class="mt-3 text-sm text-slate-500">Belum ada penerima eligible dengan pesan yang dirender. Jalankan pemeriksaan eligibility dan tunggu PDF selesai.</p>
        @endif
    </div>

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
        <div class="border-b border-slate-200 px-4 py-3">
            <h3 class="font-medium text-slate-900">Riwayat pengiriman per penerima</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-slate-500">
                    <tr>
                        <th class="px-4 py-3 font-medium">Kontak</th>
                        <th class="px-4 py-3 font-medium">Alamat</th>
                        <th class="px-4 py-3 font-medium">Eligibility</th>
                        <th class="px-4 py-3 font-medium">PDF</th>
                        <th class="px-4 py-3 font-medium">Pengiriman</th>
                        <th class="px-4 py-3 font-medium">Jadwal</th>
                        <th class="px-4 py-3 font-medium">Catatan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($recipients as $recipient)
                        <tr wire:key="proposal-recipient-{{ $recipient->id }}">
                            <td class="px-4 py-3">
                                <p class="font-medium text-slate-900">{{ $recipient->contact->full_name }}</p>
                                <p class="text-xs text-slate-500">{{ $recipient->contact->company->name }}</p>
                            </td>
                            <td class="px-4 py-3 text-slate-600">
                                {{ $campaign->channel?->value === 'EMAIL' ? ($recipient->contact->normalized_email ?? '—') : ($recipient->contact->phone_e164 ?? '—') }}
                            </td>
                            <td class="px-4 py-3">
                                <x-badge :status="strtoupper($recipient->eligibility_status->value)" :label="$recipient->eligibility_status->label()" />
                                @if ($recipient->eligibility_reason)
                                    <p class="mt-1 max-w-xs text-xs text-slate-500">{{ $recipient->eligibility_reason }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <x-badge :status="strtoupper($recipient->pdf_status->value)" :label="$recipient->pdf_status->label()" />
                                @if ($recipient->pdf_status === App\Domain\Campaigns\Enums\PdfStatus::Ready)
                                    <a href="{{ route('proposals.recipients.preview', [$campaign, $recipient]) }}" target="_blank" rel="noopener" class="mt-1 block text-xs text-blue-700 hover:underline">Lihat PDF</a>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <x-badge :status="strtoupper($recipient->delivery_status->value)" :label="$recipient->delivery_status->label()" />
                                @if ($recipient->attempts > 0)
                                    <p class="mt-1 text-xs text-slate-500">{{ $recipient->attempts }} percobaan</p>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-600">
                                {{ $recipient->scheduled_at ? $recipient->scheduled_at->timezone($campaign->timezone)->translatedFormat('d M Y H:i') : '—' }}
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-500">
                                {{ $recipient->last_error ?? $recipient->skip_reason ?? $recipient->provider_message_id ?? '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center text-slate-500">Belum ada penerima.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{ $recipients->links() }}

    <p class="text-xs text-slate-500">
        Diterima provider tidak sama dengan terkirim. Status <span class="font-medium">Tidak diketahui</span> tidak dikirim ulang otomatis dan harus direkonsiliasi.
    </p>
</div>
