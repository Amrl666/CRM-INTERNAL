<div class="space-y-6">
    @foreach (['status', 'ownerId', 'consent'] as $errorKey)
        @error($errorKey)
            <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{{ $message }}</div>
        @enderror
    @endforeach

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <x-card>
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-semibold text-slate-900" data-testid="lead-company-name">{{ $lead->company->name }}</h2>
                        <p class="text-sm text-slate-500">
                            {{ $lead->contact?->full_name ?? '-' }}
                            @if ($lead->contact?->normalized_email) &middot; {{ $lead->contact->normalized_email }} @endif
                            @if ($lead->contact?->phone_e164) &middot; {{ $lead->contact->phone_e164 }} @endif
                        </p>
                    </div>
                    <div class="text-right space-y-1">
                        <x-badge status="{{ $lead->status->value }}" :label="$lead->status->label()" testId="lead-status" />
                        @if ($lead->isArchived())
                            <div class="text-xs text-red-600">Diarsipkan</div>
                        @endif
                    </div>
                </div>

                <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                    <div><dt class="text-slate-500">Pemilik</dt><dd class="text-slate-900">{{ $lead->owner?->name ?? '-' }}</dd></div>
                    <div><dt class="text-slate-500">Prioritas</dt><dd class="text-slate-900">{{ ucfirst($lead->priority) }}</dd></div>
                    <div><dt class="text-slate-500">Sumber</dt><dd class="text-slate-900">{{ $lead->source?->name ?? '-' }}</dd></div>
                    <div><dt class="text-slate-500">Dibuat</dt><dd class="text-slate-900">{{ $lead->created_at?->translatedFormat('d M Y H:i') }}</dd></div>
                </dl>

                <div class="mt-4 flex flex-wrap items-center gap-3">
                    @can('update', $lead)
                        <a href="{{ route('leads.edit', $lead) }}" wire:navigate class="text-sm text-blue-700 hover:text-blue-800">Ubah</a>
                    @endcan
                    @can('update', $lead)
                        <button type="button" wire:click="archive" wire:confirm="Arsipkan lead ini?" wire:loading.attr="disabled" wire:target="archive"
                            class="text-sm text-red-600 hover:text-red-700 disabled:cursor-not-allowed disabled:opacity-60" data-testid="btn-archive-lead">
                            <span wire:loading.remove wire:target="archive">Arsipkan</span>
                            <span wire:loading wire:target="archive">Mengarsipkan...</span>
                        </button>
                    @endcan
                </div>
            </x-card>

            <x-card>
                <h3 class="text-sm font-semibold text-slate-900">Aktivitas</h3>
                <ul class="mt-3 space-y-2 text-sm">
                    @forelse ($activities as $activity)
                        <li class="flex justify-between gap-3" wire:key="activity-{{ $activity->id }}">
                            <span class="text-slate-700">{{ $activity->description ?? $activity->type }}</span>
                            <span class="shrink-0 text-slate-500">{{ $activity->occurred_at->translatedFormat('d M Y H:i') }}</span>
                        </li>
                    @empty
                        <li class="text-slate-500">Belum ada aktivitas.</li>
                    @endforelse
                </ul>
            </x-card>

            <x-card>
                <h3 class="text-sm font-semibold text-slate-900">Catatan</h3>

                @can('update', $lead)
                    <form wire:submit="addNote" class="mt-3 space-y-2">
                        <label for="lead-note" class="sr-only">Catatan baru</label>
                        <textarea id="lead-note" wire:model="note" rows="3" maxlength="2000"
                            @error('note') aria-invalid="true" aria-describedby="lead-note-error" @enderror
                            class="w-full rounded-md border px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 @error('note') border-red-400 @else border-slate-300 @enderror"
                            placeholder="Tulis catatan..."></textarea>
                        @error('note')
                            <p id="lead-note-error" class="text-sm text-red-600" role="alert">{{ $message }}</p>
                        @enderror
                        <div class="flex justify-end">
                            <button type="submit" wire:loading.attr="disabled" wire:target="addNote"
                                class="rounded-md bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-60">
                                <span wire:loading.remove wire:target="addNote">Tambah catatan</span>
                                <span wire:loading wire:target="addNote">Menyimpan...</span>
                            </button>
                        </div>
                    </form>
                @endcan

                <ul class="mt-4 space-y-3 text-sm">
                    @forelse ($notes as $note)
                        <li wire:key="note-{{ $note->id }}" class="border-t border-slate-100 pt-3">
                            <div class="whitespace-pre-line text-slate-700">{{ $note->body }}</div>
                            <div class="text-xs text-slate-500">{{ $note->created_at->translatedFormat('d M Y H:i') }}</div>
                        </li>
                    @empty
                        <li class="text-slate-500">Belum ada catatan.</li>
                    @endforelse
                </ul>
            </x-card>
        </div>

        <div class="space-y-6">
            @can('update', $lead)
                <x-card>
                    <h3 class="text-sm font-semibold text-slate-900">Ubah status</h3>
                    @if (count($transitions) === 0)
                        <p class="mt-2 text-sm text-slate-500">Status final, tidak ada transisi.</p>
                    @else
                        <div class="mt-3 space-y-2" role="group" aria-label="Transisi status lead">
                            @foreach ($transitions as $transition)
                                <button type="button" wire:key="transition-{{ $transition->value }}"
                                    wire:click="changeStatus('{{ $transition->value }}')"
                                    wire:loading.attr="disabled" wire:target="changeStatus"
                                    class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60">
                                    {{ $transition->label() }}
                                </button>
                            @endforeach
                        </div>
                        <p class="mt-2 text-xs text-slate-500">Perubahan status akan tercatat di aktivitas lead.</p>
                    @endif
                </x-card>
            @endcan

            @can('assign', \App\Domain\Leads\Models\Lead::class)
                <x-card>
                    <h3 class="text-sm font-semibold text-slate-900">Penugasan</h3>
                    <label for="lead-owner" class="mt-3 block text-xs font-medium text-slate-600">Pemilik lead</label>
                    <select id="lead-owner" wire:change="assign($event.target.value)" wire:loading.attr="disabled" wire:target="assign"
                        class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                        <option value="">Belum ditugaskan</option>
                        @foreach ($owners as $ownerOption)
                            <option value="{{ $ownerOption->id }}" @selected($ownerId === (string) $ownerOption->id)>{{ $ownerOption->name }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-slate-500">Hanya pengguna aktif yang dapat dipilih.</p>
                </x-card>
            @endcan

            @if ($lead->contact)
                <x-card>
                    <h3 class="text-sm font-semibold text-slate-900">Izin komunikasi</h3>
                    <div class="mt-3 space-y-3 text-sm">
                        <div class="flex items-center justify-between">
                            <span class="text-slate-600">Email</span>
                            <x-badge status="{{ $emailConsent }}" testId="consent-email" />
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-slate-600">WhatsApp</span>
                            <x-badge status="{{ $waConsent }}" testId="consent-whatsapp" />
                        </div>
                        <div class="border-t border-slate-200 pt-3 space-y-2">
                            @can('update', $lead)
                                <div class="flex flex-wrap gap-2">
                                    <button type="button" wire:click="recordConsent('EMAIL')" data-testid="btn-grant-email-consent"
                                        wire:loading.attr="disabled" wire:target="recordConsent"
                                        class="rounded-md bg-blue-700 px-3 py-1 text-xs font-medium text-white hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-60">
                                        Beri izin email
                                    </button>
                                    <button type="button" wire:click="revokeConsent('EMAIL')" data-testid="btn-revoke-email-consent"
                                        wire:loading.attr="disabled" wire:target="revokeConsent"
                                        class="rounded-md border border-red-300 px-3 py-1 text-xs font-medium text-red-700 hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-60">
                                        Cabut izin
                                    </button>
                                    <button type="button" wire:click="recordConsent('WHATSAPP')" data-testid="btn-grant-whatsapp-consent"
                                        wire:loading.attr="disabled" wire:target="recordConsent"
                                        class="rounded-md bg-green-700 px-3 py-1 text-xs font-medium text-white hover:bg-green-800 disabled:cursor-not-allowed disabled:opacity-60">
                                        Beri izin WhatsApp
                                    </button>
                                    <button type="button" wire:click="revokeConsent('WHATSAPP')" data-testid="btn-revoke-whatsapp-consent"
                                        wire:loading.attr="disabled" wire:target="revokeConsent"
                                        class="rounded-md border border-red-300 px-3 py-1 text-xs font-medium text-red-700 hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-60">
                                        Cabut izin WhatsApp
                                    </button>
                                </div>
                            @endcan
                        </div>
                    </div>
                </x-card>

                <x-card>
                    <h3 class="text-sm font-semibold text-slate-900">Komunikasi</h3>

                    @if ($lead->contact->normalized_email)
                        <div class="mt-3 space-y-2 text-sm" data-testid="email-draft-section">
                            <label class="block text-xs font-medium text-slate-700" for="email-subject">Subjek</label>
                            <input id="email-subject" type="text" wire:model="emailSubject" maxlength="500"
                                @error('emailSubject') aria-invalid="true" aria-describedby="email-subject-error" @enderror
                                class="w-full rounded-md border px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 @error('emailSubject') border-red-400 @else border-slate-300 @enderror"/>
                            @error('emailSubject')
                                <p id="email-subject-error" class="text-xs text-red-600" role="alert">{{ $message }}</p>
                            @enderror

                            <label class="mt-2 block text-xs font-medium text-slate-700" for="email-body">Isi pesan</label>
                            <textarea id="email-body" rows="4" wire:model="emailBody"
                                @error('emailBody') aria-invalid="true" aria-describedby="email-body-error" @enderror
                                class="w-full rounded-md border px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 @error('emailBody') border-red-400 @else border-slate-300 @enderror"></textarea>
                            @error('emailBody')
                                <p id="email-body-error" class="text-xs text-red-600" role="alert">{{ $message }}</p>
                            @enderror

                            <div class="mt-2 rounded-md border border-slate-200 bg-slate-50 p-3" data-testid="email-compose-preview">
                                <p class="mb-2 text-xs font-medium uppercase tracking-wide text-slate-500">Pratinjau email</p>
                                <div class="max-h-72 overflow-auto rounded-md border border-slate-200 bg-white p-3 text-sm">
                                    {!! $this->composePreviewHtml !!}
                                </div>
                                <p class="mt-2 text-xs text-slate-500">
                                    Tampilan ini mengikuti isi pesan dan footer banner yang akan dibuat di Gmail.
                                </p>
                            </div>

                            @error('emailReasons')
                                <p class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700" role="alert">{{ $message }}</p>
                            @enderror
                            @error('emailDraft')
                                <p class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700" role="alert">{{ $message }}</p>
                            @enderror

                            <div class="mt-3 flex flex-wrap items-center gap-2">
                                <button type="button" wire:click="createDraft" data-testid="btn-create-email-draft"
                                    @disabled($emailConsent !== 'GRANTED')
                                    wire:loading.attr="disabled" wire:target="createDraft"
                                    class="rounded-md bg-blue-700 px-3 py-2 text-xs font-medium text-white hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-50">
                                    <span wire:loading.remove wire:target="createDraft">Buat draft email</span>
                                    <span wire:loading wire:target="createDraft">Membuat draft...</span>
                                </button>
                                @php $draftMsg = $messages->where('channel','EMAIL')->firstWhere('status','draft'); @endphp
                                @if ($draftMsg)
                                    <button type="button" wire:click="sendDraft('{{ $draftMsg->id }}')" data-testid="btn-send-email-draft"
                                        wire:confirm="Kirim draft email ini?"
                                        wire:loading.attr="disabled" wire:target="sendDraft"
                                        class="rounded-md bg-green-700 px-3 py-2 text-xs font-medium text-white hover:bg-green-800 disabled:cursor-not-allowed disabled:opacity-60">
                                        <span wire:loading.remove wire:target="sendDraft">Kirim draft</span>
                                        <span wire:loading wire:target="sendDraft">Mengirim...</span>
                                    </button>
                                @endif
                            </div>

                            @if ($emailConsent !== 'GRANTED')
                                <div class="mt-2 text-xs text-slate-500">Draft tidak dapat dibuat karena kontak belum memiliki izin email yang valid.</div>
                            @endif
                        </div>
                    @else
                        <p class="mt-3 text-sm text-slate-500">Kontak belum memiliki email. Tambahkan email pada data lead untuk membuat draft.</p>
                    @endif

                    @if ($lead->contact->phone_e164)
                        <div class="mt-4 text-sm" data-testid="whatsapp-interaction-section">
                            <div class="flex items-center gap-2">
                                <span class="text-slate-600">WhatsApp:</span>
                                <a href="https://wa.me/{{ ltrim($lead->contact->phone_e164, '+') }}" target="_blank" rel="noopener"
                                    class="text-xs font-medium text-blue-700 hover:text-blue-800">buka chat</a>
                            </div>
                            <label class="mt-3 block text-xs font-medium text-slate-700" for="wa-summary">Ringkasan interaksi</label>
                            <textarea id="wa-summary" rows="2" wire:model="waSummary" maxlength="2000"
                                @error('waSummary') aria-invalid="true" aria-describedby="wa-summary-error" @enderror
                                class="w-full rounded-md border px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 @error('waSummary') border-red-400 @else border-slate-300 @enderror"
                                placeholder="sales sudah menghubungi kontak..."></textarea>
                            @error('waSummary')
                                <p id="wa-summary-error" class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p>
                            @enderror
                            <button type="button" wire:click="recordWaInteraction" data-testid="btn-record-wa-interaction"
                                wire:loading.attr="disabled" wire:target="recordWaInteraction"
                                class="mt-2 rounded-md border border-slate-300 px-3 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60">
                                <span wire:loading.remove wire:target="recordWaInteraction">Catat interaksi manual</span>
                                <span wire:loading wire:target="recordWaInteraction">Menyimpan...</span>
                            </button>
                        </div>
                    @endif

                    @if ($messages->isNotEmpty())
                        <div class="mt-4 border-t border-slate-100 pt-3 text-xs text-slate-500">
                            @foreach ($messages as $message)
                                <div wire:key="message-{{ $message->id }}" class="py-1">
                                    <div class="flex items-center justify-between gap-2">
                                        <span>{{ $message->channel }} &middot; {{ $message->status }}@if ($message->subject) &middot; {{ $message->subject }} @endif</span>
                                        @if ($message->channel === 'EMAIL')
                                            <button type="button" wire:click="toggleDraftPreview('{{ $message->id }}')"
                                                class="shrink-0 text-blue-700 hover:underline">
                                                {{ $previewMessageId === $message->id ? 'Sembunyikan' : 'Lihat isi' }}
                                            </button>
                                        @endif
                                    </div>
                                    @if ($previewMessageId === $message->id && $this->draftPreviewHtml !== '')
                                        <div class="mt-2 rounded-md border border-slate-200 bg-white p-3 text-sm">
                                            {!! $this->draftPreviewHtml !!}
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </x-card>
            @endif
        </div>
    </div>
</div>
