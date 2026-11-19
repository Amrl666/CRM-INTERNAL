<div class="flex h-[calc(100vh-8rem)] flex-col gap-3">
    <div class="flex gap-2">
        <button
            type="button"
            wire:click="switchChannel('whatsapp')"
            class="rounded-md px-4 py-2 text-sm font-medium {{ $channel === 'whatsapp' ? 'bg-blue-600 text-white' : 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50' }}"
        >
            WhatsApp
        </button>
        <button
            type="button"
            wire:click="switchChannel('gmail')"
            class="rounded-md px-4 py-2 text-sm font-medium {{ $channel === 'gmail' ? 'bg-blue-600 text-white' : 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50' }}"
        >
            Gmail
        </button>
    </div>

    @if ($channel === 'gmail')
        <div class="min-h-0 flex-1">
            <livewire:inbox.gmail />
        </div>
    @else
        <div wire:poll.3s.visible class="flex min-h-0 flex-1 gap-4">
    <!-- Conversation list -->
    <aside class="flex w-full max-w-sm flex-col overflow-hidden rounded-lg border border-slate-200 bg-white md:w-96">
        <div class="border-b border-slate-200 p-3">
            <input
                type="search"
                wire:model.live.debounce.300ms="search"
                placeholder="Cari nama atau nomor..."
                class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
            />
            <div class="mt-2 flex flex-wrap gap-1">
                @foreach (['all' => 'Semua', 'unread' => 'Belum Dibaca', 'open' => 'Terbuka', 'closed' => 'Selesai'] as $value => $label)
                    <button
                        type="button"
                        wire:click="$set('filter', '{{ $value }}')"
                        class="rounded-full px-3 py-1 text-xs font-medium {{ $filter === $value ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}"
                    >
                        {{ $label }}
                    </button>
                @endforeach
            </div>
        </div>

        <ul class="flex-1 divide-y divide-slate-100 overflow-y-auto">
            @forelse ($conversations as $conversation)
                @php
                    $name = $conversation->contact?->full_name
                        ?? $conversation->display_name
                        ?? $conversation->remote_jid;
                    $phone = $conversation->phone_e164 ?? $conversation->remote_jid;
                    $unread = $conversation->last_read_at === null || ($conversation->last_message_at !== null && $conversation->last_message_at->gt($conversation->last_read_at));
                @endphp
                <li wire:key="conversation-{{ $conversation->id }}">
                    <button
                        type="button"
                        wire:click="selectConversation('{{ $conversation->id }}')"
                        class="flex w-full flex-col gap-1 px-3 py-3 text-left hover:bg-slate-50 {{ $selected === $conversation->id ? 'bg-blue-50' : '' }}"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <span class="truncate text-sm font-medium text-slate-900">{{ $name }}</span>
                            <span class="shrink-0 text-xs text-slate-400">
                                {{ $conversation->last_message_at?->diffForHumans() }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between gap-2">
                            <span class="truncate text-xs text-slate-500">{{ $conversation->last_message_preview ?? '—' }}</span>
                            <span class="flex items-center gap-1">
                                @if ($unread)
                                    <span class="h-2 w-2 rounded-full bg-blue-600"></span>
                                @endif
                                <span class="rounded-full px-2 py-0.5 text-[10px] font-medium {{ $conversation->status === 'open' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600' }}">
                                    {{ $conversation->status === 'open' ? 'Terbuka' : 'Selesai' }}
                                </span>
                            </span>
                        </div>
                        <span class="truncate text-xs text-slate-400">{{ $phone }}</span>
                    </button>
                </li>
            @empty
                <li class="px-3 py-6 text-center text-sm text-slate-500">Belum ada percakapan.</li>
            @endforelse
        </ul>

        <div class="border-t border-slate-200 p-2">
            {{ $conversations->links() }}
        </div>
    </aside>

    <!-- Conversation detail -->
    <section class="flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden rounded-lg border border-slate-200 bg-white">
        @if ($selectedConversation)
            @php
                $conv = $selectedConversation;
                $headerName = $conv->contact?->full_name ?? $conv->display_name ?? $conv->remote_jid;
            @endphp
            <header class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                <div>
                    <h2 class="text-sm font-semibold text-slate-900">{{ $headerName }}</h2>
                    <p class="text-xs text-slate-500">{{ $conv->phone_e164 ?? $conv->remote_jid }}</p>
                </div>
                <div class="flex items-center gap-2">
                    @if ($conv->status === 'open')
                        <button type="button" wire:click="closeConversation('{{ $conv->id }}')" wire:confirm="Tandai percakapan selesai?"
                            class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                            Tandai Selesai
                        </button>
                    @else
                        <button type="button" wire:click="reopenConversation('{{ $conv->id }}')"
                            class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                            Buka Kembali
                        </button>
                    @endif
                </div>
            </header>

            <div class="min-h-0 flex-1 space-y-3 overflow-y-auto bg-slate-50 p-4">
                @foreach ($messages as $message)
                    @php
                        $isInbound = $message->direction === 'inbound';
                        $kind = $this->mediaKind($message);
                        $filename = $this->mediaFilename($message);
                    @endphp
                    <div wire:key="message-{{ $message->id }}" class="flex {{ $isInbound ? 'justify-start' : 'justify-end' }}">
                        <div class="max-w-[75%] rounded-lg px-3 py-2 text-sm {{ $isInbound ? 'bg-white text-slate-900 shadow-sm' : 'bg-blue-600 text-white' }}">
                            @if ($message->body !== null && $message->body !== '')
                                <p class="whitespace-pre-wrap">{{ $message->body }}</p>
                            @endif

                            @if ($kind !== null)
                                <div class="mt-2 rounded border border-slate-200 bg-slate-100 p-2 text-xs text-slate-700">
                                    @if ($kind === 'image')
                                        Gambar
                                    @elseif ($kind === 'video')
                                        Video
                                    @elseif ($kind === 'audio')
                                        Audio
                                    @else
                                        Dokumen{{ $filename ? ': '.$filename : '' }}
                                    @endif
                                </div>
                            @endif

                            <p class="mt-1 text-[10px] {{ $isInbound ? 'text-slate-400' : 'text-blue-100' }}">
                                {{ $message->sent_at?->format('d/m/Y H:i') ?? $message->created_at->format('d/m/Y H:i') }}
                                @if (! $isInbound)
                                    · {{ $message->status }}
                                @endif
                            </p>
                            @if (! $isInbound && $message->status === 'failed')
                                <button type="button" wire:click="retryMessage('{{ $message->id }}')"
                                    class="mt-1 text-[10px] font-medium text-red-600 hover:underline">
                                    Kirim ulang
                                </button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Composer -->
            <div class="border-t border-slate-200 bg-white px-4 py-3">
                <form wire:submit="sendMessage" class="flex items-end gap-2">
                    <textarea
                        wire:model="messageText"
                        rows="2"
                        placeholder="Ketik pesan..."
                        class="flex-1 resize-none rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                    ></textarea>
                    <button type="submit"
    			wire:loading.attr="disabled"
    			wire:target="sendMessage"
    			class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
    			<span wire:loading.remove wire:target="sendMessage">Kirim</span>
    			<span wire:loading wire:target="sendMessage">Mengirim...</span>
		    </button>
                </form>
                @error('messageText')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <!-- Contact / Lead info -->
            <div class="border-t border-slate-200 bg-white px-4 py-3 text-xs text-slate-600">
                @if ($conv->contact)
                    <p><span class="font-medium">Contact:</span> {{ $conv->contact->full_name }} ({{ $conv->contact->email ?? '-' }})</p>
                @else
                    <p><span class="font-medium">Contact:</span> Belum dikenal</p>
                @endif

                @if ($conv->lead)
                    <p><span class="font-medium">Lead:</span> {{ $conv->lead->company?->name ?? '-' }}</p>
                @else
                    <p><span class="font-medium">Lead:</span> Tidak terhubung</p>
                @endif

                <p class="mt-2 text-slate-400">
                    Kualifikasi manual belum tersedia pada tahap ini. Contact dan Lead tidak dibuat otomatis.
                </p>
            </div>
        @else
            <div class="flex flex-1 items-center justify-center text-sm text-slate-500">
                Pilih percakapan untuk melihat detail.
            </div>
        @endif
    </section>
        </div>
    @endif
</div>
