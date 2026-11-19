<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-lg font-semibold text-slate-900">Buat outreach</h2>
            <p class="mt-1 text-sm text-slate-500">Satu outreach hanya menggunakan satu channel. PDF dibuat per penerima setelah disimpan.</p>
        </div>
        <a href="{{ route('proposals.index') }}" wire:navigate class="text-sm text-slate-600 hover:text-slate-900">Kembali</a>
    </div>

    @error('form')
        <div class="rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">{{ $message }}</div>
    @enderror

    @if ($errors->any())
        <div class="rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">
            Periksa kembali kolom yang ditandai.
        </div>
    @endif

    <div class="flex items-center gap-2" role="group" aria-label="Wizard alur outreach">
        <a href="#outreach-audience" class="rounded-full bg-blue-600 px-3 py-1 text-xs font-medium text-white">1 · Dasar &amp; Audiens</a>
        <span class="h-0.5 w-8 bg-slate-300" aria-hidden="true"></span>
        <a href="#outreach-schedule" class="rounded-full bg-blue-600 px-3 py-1 text-xs font-medium text-white">2 · Jadwal</a>
        <span class="h-0.5 w-8 bg-slate-300" aria-hidden="true"></span>
        <a href="#outreach-review" class="rounded-full bg-slate-300 px-3 py-1 text-xs font-medium text-slate-600">3 · Review</a>
    </div>

    <form wire:submit="save" class="space-y-6">
        <div id="outreach-audience" class="scroll-mt-24 rounded-lg border border-slate-200 bg-white p-6 space-y-4">
            <h3 class="font-medium text-slate-900">Dasar campaign &amp; audiens</h3>

            <div>
                <label for="proposal-name" class="block text-sm font-medium text-slate-700">Nama campaign</label>
                <input id="proposal-name" type="text" wire:model="name" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"/>
                @error('name') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="proposal-template" class="block text-sm font-medium text-slate-700">Template proposal</label>
                    <select id="proposal-template" wire:model="proposal_template_id" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <option value="">Pilih template</option>
                        @foreach ($proposalTemplates as $template)
                            <option value="{{ $template->id }}">{{ $template->name }} v{{ $template->version }}</option>
                        @endforeach
                    </select>
                    @error('proposal_template_id') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
                </div>

                <div>
                    <label for="proposal-channel" class="block text-sm font-medium text-slate-700">Channel</label>
                    <select id="proposal-channel" wire:model.live="channel" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <option value="EMAIL">Email (Gmail)</option>
                        <option value="WHATSAPP" @disabled(! $whatsAppEnabled)>WhatsApp (Evolution){{ $whatsAppEnabled ? '' : ' — belum diaktifkan' }}</option>
                    </select>
                    @error('channel') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
                </div>
            </div>

            <div>
                <label for="message-template" class="block text-sm font-medium text-slate-700">Template pesan</label>
                <select id="message-template" wire:model="message_template_id" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Pilih template pesan</option>
                    @foreach ($messageTemplates as $template)
                        <option value="{{ $template->id }}">{{ $template->name }}</option>
                    @endforeach
                </select>
                @error('message_template_id') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
            </div>

            <div>
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span class="block text-sm font-medium text-slate-700">Penerima (lead aktif)</span>
                    @if ($leads->isNotEmpty())
                        <button type="button" wire:click="selectAllEligible" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                            Pilih semua dari daftar ini
                        </button>
                    @endif
                </div>
                <p class="mt-1 text-sm text-slate-600">
                    {{ $selectedCount }} penerima dipilih. Penerima tanpa consent akan dilewati saat validasi eligibility.
                </p>

                @if ($leads->isEmpty())
                    <div class="mt-2 rounded-md border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-center text-sm text-slate-500">
                        Belum ada lead aktif dengan kontak. Tambahkan atau lengkapi data lead terlebih dahulu.
                    </div>
                @else
                    <div class="mt-2 max-h-72 space-y-1 overflow-y-auto rounded-md border border-slate-300 bg-white p-2"
                        role="group" aria-label="Pilih penerima proposal">
                        @foreach ($leads as $lead)
                            <label for="proposal-lead-{{ $lead->id }}"
                                class="flex cursor-pointer items-start gap-3 rounded-md px-2 py-2 text-sm hover:bg-slate-50">
                                <input id="proposal-lead-{{ $lead->id }}" type="checkbox" wire:model="leadIds" value="{{ $lead->id }}"
                                    class="mt-0.5 rounded border-slate-300 text-blue-600 focus:ring-blue-500"/>
                                <span class="min-w-0">
                                    <span class="block font-medium text-slate-800">{{ $lead->company->name }}</span>
                                    <span class="block text-xs text-slate-500">
                                        {{ $lead->contact->full_name }} ·
                                        {{ $lead->contact->normalized_email ?? $lead->contact->phone_e164 ?? 'tanpa alamat' }}
                                    </span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                @endif

                <p class="mt-1 text-xs text-slate-500">Penerima tanpa consent akan dilewati saat validasi eligibility.</p>
                @error('leadIds')
                    <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                @enderror
                @error('leadIds.*')
                    <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div id="outreach-schedule" class="scroll-mt-24 rounded-lg border border-slate-200 bg-white p-6 space-y-4">
            <div>
                <h3 class="font-medium text-slate-900">Jadwal bisnis</h3>
                <p class="mt-1 text-sm text-slate-500">Waktu disimpan UTC; slot dihitung pada zona waktu yang dipilih. Pengiriman tidak dijadwalkan di luar jam kerja.</p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="proposal-timezone" class="block text-sm font-medium text-slate-700">Zona waktu</label>
                    <input id="proposal-timezone" type="text" wire:model="timezone" list="timezone-options" placeholder="Asia/Jakarta"
                        class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"/>
                    <datalist id="timezone-options">
                        <option value="Asia/Jakarta"></option>
                        <option value="Asia/Makassar"></option>
                        <option value="Asia/Jayapura"></option>
                        <option value="Asia/Singapore"></option>
                        <option value="UTC"></option>
                    </datalist>
                    @error('timezone') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label for="proposal-start" class="block text-sm font-medium text-slate-700">Mulai jadwal pengiriman (opsional)</label>
                    <input id="proposal-start" type="datetime-local" wire:model="scheduledStartAt" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"/>
                    @error('scheduledStartAt') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
                </div>
            </div>

            <div>
                <span class="block text-sm font-medium text-slate-700">Hari aktif</span>
                <div class="mt-2 flex flex-wrap gap-3">
                    @foreach ([1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'] as $day => $label)
                        <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                            <input type="checkbox" wire:model="businessDays" value="{{ $day }}" class="rounded border-slate-300 text-blue-700 focus:ring-blue-500">
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
                @error('businessDays') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="proposal-business-start" class="block text-sm font-medium text-slate-700">Jam mulai</label>
                    <input id="proposal-business-start" type="time" wire:model="businessStart" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"/>
                    @error('businessStart') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label for="proposal-business-end" class="block text-sm font-medium text-slate-700">Jam selesai</label>
                    <input id="proposal-business-end" type="time" wire:model="businessEnd" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"/>
                    @error('businessEnd') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
                </div>
            </div>

            <div>
                <label for="proposal-excluded" class="block text-sm font-medium text-slate-700">Tanggal libur / dikecualikan</label>
                <textarea id="proposal-excluded" rows="3" wire:model="excludedDates" placeholder="2026-12-25" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"></textarea>
                <p class="mt-1 text-xs text-slate-500">Satu tanggal per baris, format YYYY-MM-DD.</p>
                @error('excludedDates') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="proposal-rate" class="block text-sm font-medium text-slate-700">Batas pengiriman per jam</label>
                    <input id="proposal-rate" type="number" min="1" max="120" wire:model="rateLimitPerHour" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"/>
                    @error('rateLimitPerHour') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label for="proposal-spacing" class="block text-sm font-medium text-slate-700">Jarak minimum antar pengiriman (detik)</label>
                    <input id="proposal-spacing" type="number" min="0" max="3600" wire:model="minSpacingSeconds" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"/>
                    @error('minSpacingSeconds') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
                </div>
            </div>
        </div>

        <div id="outreach-review" class="flex justify-end gap-3 scroll-mt-24">
            <a href="{{ route('proposals.index') }}" wire:navigate class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Batal</a>
            <button type="submit" wire:loading.attr="disabled" wire:target="save" @disabled($leads->isEmpty())
                class="rounded-md bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-50">
                <span wire:loading.remove wire:target="save">Simpan sebagai draft</span>
                <span wire:loading wire:target="save">Menyimpan…</span>
            </button>
        </div>
    </form>
</div>
