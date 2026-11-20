<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-lg font-semibold text-slate-900">Buat outreach</h2>
            <p class="mt-1 text-sm text-slate-500">Penerima ditentukan oleh filter saat campaign dimulai. PDF dibuat setelah audiens diambil.</p>
        </div>
        <a href="{{ route('proposals.index') }}" wire:navigate class="text-sm text-slate-600 hover:text-slate-900">Kembali</a>
    </div>

    @error('form')
        <div class="rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">{{ $message }}</div>
    @enderror

    @if ($errors->any())
        <div class="rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">Periksa kembali kolom yang ditandai.</div>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="space-y-6">
            <section class="rounded-lg border border-slate-200 bg-white p-5" id="outreach-audience">
                <h3 class="font-medium text-slate-900">1 · Dasar &amp; audiens</h3>

                <div class="mt-4 space-y-4">
                    <div>
                        <label for="campaign-name" class="block text-sm font-medium text-slate-700">Nama campaign</label>
                        <input id="campaign-name" type="text" wire:model="name" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" />
                        @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="proposal-template" class="block text-sm font-medium text-slate-700">Template proposal</label>
                        <select id="proposal-template" wire:model="proposal_template_id" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            <option value="">Pilih template</option>
                            @foreach ($proposalTemplates as $template)
                                <option value="{{ $template->id }}">{{ $template->name }} v{{ $template->version }}</option>
                            @endforeach
                        </select>
                        @error('proposal_template_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="channel" class="block text-sm font-medium text-slate-700">Channel</label>
                        <select id="channel" wire:model.live="channel" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            @foreach ($channels as $item)
                                <option value="{{ $item->value }}">{{ $item->label() }}</option>
                            @endforeach
                        </select>
                        @error('channel') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        @if ($channel === \App\Domain\Campaigns\Enums\ProposalChannel::Both)
                            <p class="mt-1 text-xs text-slate-500">Setiap channel dievaluasi independen. Jika hanya satu yang eligible, hanya channel itu yang dikirim.</p>
                        @endif
                    </div>

                    <div>
                        <label for="message-template" class="block text-sm font-medium text-slate-700">Template pesan {{ $channel === \App\Domain\Campaigns\Enums\ProposalChannel::WhatsApp ? 'WhatsApp' : 'Email' }}</label>
                        <select id="message-template" wire:model="message_template_id" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            <option value="">Pilih template</option>
                            @foreach ($messageTemplates as $template)
                                <option value="{{ $template->id }}">{{ $template->name }}</option>
                            @endforeach
                        </select>
                        @error('message_template_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    @if ($channel === \App\Domain\Campaigns\Enums\ProposalChannel::Both)
                        <div>
                            <label for="whatsapp-message-template" class="block text-sm font-medium text-slate-700">Template pesan WhatsApp</label>
                            <select id="whatsapp-message-template" wire:model="whatsapp_message_template_id" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                                <option value="">Pilih template</option>
                                @foreach ($whatsappTemplates as $template)
                                    <option value="{{ $template->id }}">{{ $template->name }}</option>
                                @endforeach
                            </select>
                            @error('whatsapp_message_template_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    @endif
                </div>
            </section>

            <section class="rounded-lg border border-slate-200 bg-white p-5">
                <h3 class="font-medium text-slate-900">2 · Kriteria audiens</h3>

                <div class="mt-4 flex gap-4 text-sm">
                    <label class="flex items-center gap-2"><input type="radio" wire:model.live="audienceMode" value="filter" /> Filter otomatis</label>
                    <label class="flex items-center gap-2"><input type="radio" wire:model.live="audienceMode" value="manual" /> Pilih manual</label>
                </div>

                @if ($audienceMode === 'filter')
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block text-xs font-medium text-slate-600">Status lead</label>
                            <select multiple wire:model="leadStatuses" class="mt-1 h-28 w-full rounded-md border border-slate-300 px-2 py-1 text-sm">
                                @foreach ($leadStatuses as $status)
                                    <option value="{{ $status->value }}">{{ $status->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600">Sumber lead</label>
                            <select multiple wire:model="sourceIds" class="mt-1 h-28 w-full rounded-md border border-slate-300 px-2 py-1 text-sm">
                                @foreach ($sources as $source)
                                    <option value="{{ $source->id }}">{{ $source->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600">Batch import</label>
                            <select multiple wire:model="importBatchIds" class="mt-1 h-28 w-full rounded-md border border-slate-300 px-2 py-1 text-sm">
                                @foreach ($importBatches as $batch)
                                    <option value="{{ $batch->id }}">{{ $batch->filename }} ({{ $batch->created_at->format('d M Y') }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600">Tags</label>
                            <select multiple wire:model="tagIds" class="mt-1 h-28 w-full rounded-md border border-slate-300 px-2 py-1 text-sm">
                                @foreach ($tags as $tag)
                                    <option value="{{ $tag->id }}">{{ $tag->name }}</option>
                                @endforeach
                            </select>
                            <select wire:model="tagMatch" class="mt-2 w-full rounded-md border border-slate-300 px-2 py-1 text-xs">
                                <option value="any">Cocok salah satu tag</option>
                                <option value="all">Cocok semua tag</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600">Industri</label>
                            <select multiple wire:model="industries" class="mt-1 h-28 w-full rounded-md border border-slate-300 px-2 py-1 text-sm">
                                @foreach ($industries as $industry)
                                    <option value="{{ $industry }}">{{ $industry }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600">Kota</label>
                            <select multiple wire:model="cities" class="mt-1 h-28 w-full rounded-md border border-slate-300 px-2 py-1 text-sm">
                                @foreach ($cities as $city)
                                    <option value="{{ $city }}">{{ $city }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600">Negara (ISO-2)</label>
                            <select multiple wire:model="countryCodes" class="mt-1 h-20 w-full rounded-md border border-slate-300 px-2 py-1 text-sm">
                                <option value="ID">Indonesia</option>
                                <option value="SG">Singapura</option>
                                <option value="MY">Malaysia</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600">Riwayat outreach</label>
                            <select wire:model="priorOutreach" class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1 text-sm">
                                <option value="any">Semua (duplikat tetap dikecualikan)</option>
                                <option value="never_contacted">Belum pernah di-outreach</option>
                                <option value="failed_only">Pernah gagal, belum berhasil</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600">Email</label>
                            <select wire:model="emailAvailability" class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1 text-sm">
                                <option value="any">Semua</option>
                                <option value="available">Tersedia</option>
                                <option value="unavailable">Tidak tersedia</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600">WhatsApp</label>
                            <select wire:model="whatsappAvailability" class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1 text-sm">
                                <option value="any">Semua</option>
                                <option value="available">Tersedia</option>
                                <option value="unavailable">Tidak tersedia</option>
                            </select>
                        </div>
                    </div>
                @else
                    <p class="mt-4 text-sm text-slate-600">Mode manual menyimpan daftar lead yang dipilih. Maksimal 1.000 lead. Lead tetap di-resolve saat campaign dimulai.</p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach ($manualLeadIds as $leadId)
                            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs text-slate-700">{{ $leadId }}</span>
                        @endforeach
                    </div>
                    @error('manualLeadIds') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                @endif

                <div class="mt-4">
                    <button type="button" wire:click="calculatePreview" wire:loading.attr="disabled" class="rounded-md border border-blue-300 bg-blue-50 px-4 py-2 text-sm font-medium text-blue-800 hover:bg-blue-100">
                        Hitung estimasi penerima
                    </button>
                </div>

                @if ($audiencePreview)
                    <div class="mt-4 rounded-md border border-slate-200 bg-slate-50 p-4 text-sm">
                        <p class="font-medium text-slate-900">Estimasi — bukan snapshot final</p>
                        <p class="mt-1 text-slate-600">{{ $audiencePreview['total_contacts'] }} kontak cocok dengan filter.</p>
                        @foreach ($audiencePreview['channels'] as $channelName => $counts)
                            <p class="mt-1 text-slate-600">{{ $channelName }}: {{ $counts['eligible'] }} eligible, {{ $counts['ineligible'] }} tidak eligible.</p>
                        @endforeach
                        <p class="mt-1 text-xs text-slate-400">Dihitung {{ $audiencePreview['calculated_at'] }}</p>
                    </div>
                @endif
            </section>
        </div>

        <section class="rounded-lg border border-slate-200 bg-white p-5" id="outreach-schedule">
            <h3 class="font-medium text-slate-900">3 · Jadwal &amp; batas pengiriman</h3>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-medium text-slate-600">Timezone</label>
                    <input type="text" wire:model="timezone" class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1 text-sm" />
                    @error('timezone') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600">Mulai terjadwal</label>
                    <input type="datetime-local" wire:model="scheduledStartAt" class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1 text-sm" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600">Jam mulai</label>
                    <input type="time" wire:model="businessStart" class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1 text-sm" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600">Jam selesai</label>
                    <input type="time" wire:model="businessEnd" class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1 text-sm" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600">Rate per jam</label>
                    <input type="number" min="1" max="120" wire:model="rateLimitPerHour" class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1 text-sm" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600">Jarak minimum (detik)</label>
                    <input type="number" min="0" max="3600" wire:model="minSpacingSeconds" class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1 text-sm" />
                </div>
            </div>

            <div class="mt-4">
                <label class="block text-xs font-medium text-slate-600">Hari kerja</label>
                <div class="mt-1 flex flex-wrap gap-3 text-sm">
                    @foreach ([1 => 'Sen', 2 => 'Sel', 3 => 'Rab', 4 => 'Kam', 5 => 'Jum', 6 => 'Sab', 7 => 'Min'] as $day => $label)
                        <label class="flex items-center gap-1"><input type="checkbox" value="{{ $day }}" wire:model="businessDays" /> {{ $label }}</label>
                    @endforeach
                </div>
            </div>

            <div class="mt-4">
                <label class="block text-xs font-medium text-slate-600">Tanggal libur (satu per baris, YYYY-MM-DD)</label>
                <textarea wire:model="excludedDates" rows="3" class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1 text-sm"></textarea>
                @error('excludedDates') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="mt-6 flex justify-end">
                <button type="button" wire:click="save" wire:loading.attr="disabled" class="rounded-md bg-blue-700 px-5 py-2 text-sm font-medium text-white hover:bg-blue-800 disabled:opacity-50">
                    Simpan draft
                </button>
            </div>
        </section>
    </div>
</div>
