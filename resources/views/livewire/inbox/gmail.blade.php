<div class="flex h-full min-h-0 gap-4" @if ($syncRunning) wire:poll.15s @endif>
    <aside class="flex w-full max-w-sm flex-col overflow-hidden rounded-lg border border-slate-200 bg-white md:w-96">
        <div class="border-b border-slate-200 p-3">
            <input
                type="search"
                wire:model.live.debounce.300ms="search"
                placeholder="Cari subject, pengirim, atau isi email..."
                class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
            />

            <div class="mt-2 flex flex-wrap gap-1">
                @foreach (['all' => 'Semua', 'unread' => 'Belum Dibaca', 'starred' => 'Berbintang'] as $value => $label)
                    <button
                        type="button"
                        wire:click="$set('filter', '{{ $value }}')"
                        class="rounded-full px-3 py-1 text-xs font-medium {{ $filter === $value ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}"
                    >
                        {{ $label }}
                    </button>
                @endforeach
            </div>

            <div class="mt-3 rounded-md border border-slate-200 bg-slate-50 p-2.5">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="text-xs font-semibold text-slate-700">Sinkronisasi Gmail</p>

                        @if (!$statusAccount)
                            <p class="mt-0.5 text-xs text-slate-500">
                                Akun Gmail utama belum terhubung.
                            </p>
                        @elseif ($reconnectRequired)
                            <p class="mt-0.5 text-xs font-medium text-red-600" data-testid="gmail-reconnect-notice">
                                Koneksi Gmail terputus. Hubungkan ulang akun utama di Pengaturan.
                            </p>
                        @elseif ($resync)
                            <p class="mt-0.5 text-xs font-medium text-amber-600" data-testid="gmail-resync-notice">
                                Riwayat Gmail kedaluwarsa. {{ $syncRunning ? 'Sinkronisasi penuh sedang berjalan...' : 'Sinkronisasi penuh akan dijalankan otomatis.' }}
                            </p>
                        @elseif ($syncRunning)
                            <p class="mt-0.5 text-xs font-medium text-blue-600">
                                Sedang berjalan...
                            </p>
                        @elseif ($statusAccount->last_synced_at)
                            <p class="mt-0.5 truncate text-xs text-slate-500">
                                Terakhir: {{ $statusAccount->last_synced_at->format('d/m/Y H:i') }}
                            </p>
                        @else
                            <p class="mt-0.5 text-xs text-slate-500">
                                Belum pernah disinkronkan.
                            </p>
                        @endif

                        @if ($statusAccount?->account_email)
                            <p class="mt-0.5 truncate text-xs text-slate-400">
                                {{ $statusAccount->account_email }}
                            </p>
                        @endif
                    </div>

                    <button
                        type="button"
                        wire:click="syncNow"
                        wire:loading.attr="disabled"
                        wire:target="syncNow"
                        @disabled($syncRunning || $account === null)
                        aria-disabled="{{ $syncRunning || $account === null ? 'true' : 'false' }}"
                        data-testid="gmail-sync-button"
                        data-state="{{ $syncRunning ? 'running' : 'idle' }}"
                        class="shrink-0 rounded-md bg-blue-600 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:bg-slate-300 disabled:text-slate-500"
                    >
                        Sync Now
                    </button>
                </div>

                @if ($syncFeedback)
                    <p class="mt-2 text-xs {{ $syncFeedbackType === 'error' ? 'text-red-600' : ($syncFeedbackType === 'success' ? 'text-emerald-600' : 'text-slate-600') }}">
                        {{ $syncFeedback }}
                    </p>
                @endif

                @if ($lastFailure)
                    <p class="mt-2 text-xs text-red-600" title="{{ $lastFailure['message'] }}">
                        Gagal {{ $lastFailure['occurred_at']->format('d/m/Y H:i') }}:
                        {{ \Illuminate\Support\Str::limit($lastFailure['message'], 90) }}
                    </p>
                @endif
            </div>
        </div>

        <ul class="flex-1 divide-y divide-slate-100 overflow-y-auto">
            @forelse ($messages as $message)
                @php
                    $unread = $message->is_read === false || $message->is_read === null;
                    $sender = $message->sender_name ?: $message->sender_email ?: 'Pengirim tidak diketahui';
                @endphp

                <li wire:key="gmail-message-{{ $message->id }}">
                    <button
                        type="button"
                        wire:click="selectMessage('{{ $message->id }}')"
                        class="flex w-full flex-col gap-1 px-3 py-3 text-left hover:bg-slate-50 {{ $selected === $message->id ? 'bg-blue-50' : '' }}"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <span class="truncate text-sm {{ $unread ? 'font-bold text-slate-900' : 'font-medium text-slate-700' }}">
                                {{ $sender }}
                            </span>
                            <span class="shrink-0 text-xs text-slate-400">
                                {{ $message->received_at?->format('d/m H:i') ?? $message->created_at?->format('d/m H:i') }}
                            </span>
                        </div>

                        <div class="flex items-center gap-2">
                            <span class="truncate text-xs {{ $unread ? 'font-semibold text-slate-800' : 'text-slate-600' }}">
                                {{ $message->subject ?: '(Tanpa subject)' }}
                            </span>
                            @if ($message->is_starred)
                                <span class="text-amber-500" title="Berbintang">★</span>
                            @endif
                        </div>

                        <span class="line-clamp-2 text-xs text-slate-500">
                            {{ \Illuminate\Support\Str::limit(strip_tags((string) $message->body), 110) }}
                        </span>
                    </button>
                </li>
            @empty
                <li class="px-3 py-8 text-center text-sm text-slate-500">
                    @if (!$account)
                        Akun Gmail utama belum terhubung.
                    @else
                        Belum ada email tersinkronisasi.
                    @endif
                </li>
            @endforelse
        </ul>

        <div class="border-t border-slate-200 p-2">
            {{ $messages->links() }}
        </div>
    </aside>

    <section class="flex min-w-0 flex-1 flex-col overflow-hidden rounded-lg border border-slate-200 bg-white">
        @if ($selectedMessage)
            <header class="border-b border-slate-200 px-4 py-3">
                <h2 class="text-base font-semibold text-slate-900">
                    {{ $selectedMessage->subject ?: '(Tanpa subject)' }}
                </h2>
                <p class="mt-1 text-xs text-slate-500">
                    {{ $selectedMessage->sender_name ?: $selectedMessage->sender_email }}
                    @if ($selectedMessage->sender_email && $selectedMessage->sender_name)
                        &lt;{{ $selectedMessage->sender_email }}&gt;
                    @endif
                </p>
                <p class="mt-1 text-xs text-slate-500">
                    Kepada:
                    {{ collect($selectedMessage->to_recipients ?? [])->pluck('email')->filter()->implode(', ') ?: '-' }}
                </p>
                @if (!empty($selectedMessage->cc_recipients))
                    <p class="mt-1 text-xs text-slate-500">
                        CC: {{ collect($selectedMessage->cc_recipients)->pluck('email')->filter()->implode(', ') }}
                    </p>
                @endif
            </header>

            <div class="flex-1 space-y-4 overflow-y-auto bg-slate-50 p-4">
                @foreach ($threadMessages as $message)
                    <article wire:key="gmail-thread-message-{{ $message->id }}" class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-2">
                            <div class="text-sm font-medium text-slate-800">
                                {{ $message->sender_name ?: $message->sender_email ?: 'Pengirim tidak diketahui' }}
                            </div>
                            <time class="text-xs text-slate-400">
                                {{ $message->received_at?->format('d/m/Y H:i') ?? $message->created_at?->format('d/m/Y H:i') }}
                            </time>
                        </div>

                        <div class="whitespace-pre-wrap break-words pt-3 text-sm text-slate-700">{{ $message->body }}</div>

                        <div class="mt-3 flex flex-wrap gap-2 text-xs text-slate-400">
                            <span>{{ $message->direction === 'outbound' ? 'Terkirim' : 'Diterima' }}</span>
                            @if ($message->is_read === false)
                                <span>· Belum dibaca</span>
                            @endif
                            @if ($message->is_starred)
                                <span>· ★ Berbintang</span>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        @else
            <div class="flex flex-1 items-center justify-center text-sm text-slate-500">
                Pilih email untuk melihat isi dan riwayat thread.
            </div>
        @endif
    </section>
</div>
