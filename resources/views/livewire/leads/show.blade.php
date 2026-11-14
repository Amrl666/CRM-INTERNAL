<div class="max-w-6xl mx-auto space-y-6">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <x-card>
                <div class="flex items-start justify-between">
                    <div>
                        <h2 class="text-xl font-semibold text-slate-900" data-testid="lead-company-name">{{ $lead->company->name }}</h2>
                        <p class="text-sm text-slate-500">
                            {{ $lead->contact?->full_name ?? '-' }}
                            @if ($lead->contact?->normalized_email) &middot; {{ $lead->contact->normalized_email }} @endif
                            @if ($lead->contact?->phone_e164) &middot; {{ $lead->contact->phone_e164 }} @endif
                        </p>
                    </div>
                    <div class="text-right space-y-1">
                        <x-badge status="{{ $lead->status->value }}" testId="lead-status" />
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

                <div class="mt-4 flex gap-3">
                    <a href="{{ route('leads.edit', $lead) }}" wire:navigate class="text-sm text-blue-700 hover:text-blue-800">Ubah</a>
                    @can('update', $lead)
                        <button wire:click="archive" wire:confirm="Arsipkan lead ini?" class="text-sm text-red-600 hover:text-red-700" data-testid="btn-archive-lead">Arsipkan</button>
                    @endcan
                </div>
            </x-card>

            <x-card>
                <h3 class="text-sm font-semibold text-slate-900">Aktivitas</h3>
                <ul class="mt-3 space-y-2 text-sm">
                    @forelse ($activities as $activity)
                        <li class="flex justify-between gap-3" wire:key="activity-{{ $activity->id }}">
                            <span class="text-slate-700">{{ $activity->description ?? $activity->type }}</span>
                            <span class="text-slate-500">{{ $activity->occurred_at->translatedFormat('d M Y H:i') }}</span>
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
                        <textarea wire:model="note" rows="3" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" placeholder="Tulis catatan..."></textarea>
                        @error('note') <div class="text-sm text-red-600">{{ $message }}</div> @enderror
                        <div class="text-right">
                            <button type="submit" class="rounded-md bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800">Tambah</button>
                        </div>
                    </form>
                @endcan

                <ul class="mt-4 space-y-3 text-sm">
                    @forelse ($notes as $note)
                        <li wire:key="note-{{ $note->id }}" class="border-t border-slate-100 pt-3">
                            <div class="text-slate-700 whitespace-pre-line">{{ $note->body }}</div>
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
                        <div class="mt-3 space-y-2">
                            @foreach ($transitions as $transition)
                                <button wire:click="changeStatus('{{ $transition->value }}')"
                                    class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">
                                    {{ $transition->label() }}
                                </button>
                            @endforeach
                        </div>
                    @endif
                </x-card>
            @endcan

            @can('assign', \App\Domain\Leads\Models\Lead::class)
                <x-card>
                    <h3 class="text-sm font-semibold text-slate-900">Penugasan</h3>
                    <select wire:change="assign($event.target.value)" class="mt-3 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <option value="">Belum ditugaskan</option>
                        @foreach ($owners as $ownerOption)
                            <option value="{{ $ownerOption->id }}" @selected($ownerId === (string) $ownerOption->id)>{{ $ownerOption->name }}</option>
                        @endforeach
                    </select>
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
                            <x-badge status="UNKNOWN" />
                        </div>
                        <div class="border-t border-slate-200 pt-3 space-y-2">
                            @can('update', $lead)
                                <div class="flex flex-wrap gap-2">
                                    <button wire:click="recordConsent('EMAIL')" data-testid="btn-grant-email-consent"
                                        class="rounded-md bg-blue-700 px-3 py-1 text-xs font-medium text-white hover:bg-blue-800">Beri izin email</button>
                                    <button wire:click="revokeConsent('EMAIL')" data-testid="btn-revoke-email-consent"
                                        class="rounded-md border border-red-300 px-3 py-1 text-xs font-medium text-red-700 hover:bg-red-50">Cabut izin</button>
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
                            <input id="email-subject" type="text" wire:model.live="emailSubject" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"/>
                            @error('emailSubject') <div class="text-xs text-red-600">{{ $message }}</div> @enderror

                            <label class="mt-2 block text-xs font-medium text-slate-700" for="email-body">Isi pesan</label>
                            <textarea id="email-body" rows="4" wire:model.live="emailBody" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"></textarea>
                            @error('emailBody') <div class="text-xs text-red-600">{{ $message }}</div> @enderror

                            <div class="mt-3 flex flex-wrap items-center gap-2">
                                <button wire:click="createDraft" data-testid="btn-create-email-draft"
                                    :disabled="$emailConsent !== 'GRANTED'"
                                    class="rounded-md bg-blue-700 px-3 py-2 text-xs font-medium text-white hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-50">
                                    Buat draft email
                                </button>
                                @php $draftMsg = $messages->where('channel','EMAIL')->firstWhere('status','draft'); @endphp
                                @if ($draftMsg)
                                    <button wire:click="sendDraft('{{ $draftMsg->id }}')" data-testid="btn-send-email-draft"
                                        wire:confirm="Kirim draft email ini?"
                                        class="rounded-md bg-green-700 px-3 py-2 text-xs font-medium text-white hover:bg-green-800">
                                        Kirim draft
                                    </button>
                                @endif
                            </div>

                            @if ($emailConsent !== 'GRANTED')
                                <div class="mt-2 text-xs text-slate-500">Draft tidak dapat dibuat karena kontak belum memiliki izin email yang valid.</div>
                            @endif
                        </div>
                    @endif

                    @if ($lead->contact->phone_e164)
                        <div class="mt-4 text-sm" data-testid="whatsapp-interaction-section">
                            <div class="flex items-center gap-2">
                                <span class="text-slate-600">WhatsApp:</span>
                                <a href="https://wa.me/{{ $lead->contact->phone_e164 }}" target="_blank" rel="noopener"
                                    class="text-xs font-medium text-blue-700 hover:text-blue-800">buka chat</a>
                            </div>
                            <label class="mt-3 block text-xs font-medium text-slate-700" for="wa-summary">Ringkasan interaksi</label>
                            <textarea id="wa-summary" rows="2" wire:model.live="waSummary" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" placeholder="sales sudah menghubungi kontak..."></textarea>
                            <button wire:click="recordWaInteraction" data-testid="btn-record-wa-interaction"
                                class="mt-2 rounded-md border border-slate-300 px-3 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50">
                                Catat interaksi manual
                            </button>
                        </div>
                    @endif

                    @if ($messages->isNotEmpty())
                        <div class="mt-4 text-xs text-slate-500 border-t border-slate-100 pt-3">
                            @foreach ($messages as $message)
                                <div>{{ $message->channel }} &middot; {{ $message->status }}@if ($message->subject) &middot; {{ $message->subject }} @endif</div>
                            @endforeach
                        </div>
                    @endif
                </x-card>
            @endif
        </div>
    </div>
</div>
