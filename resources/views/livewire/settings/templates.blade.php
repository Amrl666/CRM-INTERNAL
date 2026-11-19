<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h2 class="text-lg font-semibold text-slate-900">Template</h2>
        <div class="flex gap-2">
            <button type="button" wire:click="$set('tab','message')" class="px-3 py-1.5 text-sm rounded-md {{ $tab === 'message' ? 'bg-blue-600 text-white' : 'bg-white border border-slate-300 text-slate-700' }}">Pesan</button>
            <button type="button" wire:click="$set('tab','email')" class="px-3 py-1.5 text-sm rounded-md {{ $tab === 'email' ? 'bg-blue-600 text-white' : 'bg-white border border-slate-300 text-slate-700' }}">Email Draft</button>
        </div>
    </div>

    @if ($tab === 'message')
        <div class="bg-white border border-slate-200 rounded-lg p-4 space-y-4">
            <h3 class="font-medium text-slate-900">{{ $editingMessageId ? 'Edit Template Pesan' : 'Buat Template Pesan' }}</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Nama</label>
                    <input type="text" wire:model="messageName" class="w-full rounded-md border-slate-300 text-sm" />
                    @error('messageName') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Channel</label>
                    <select wire:model="messageChannel" class="w-full rounded-md border-slate-300 text-sm">
                        <option value="EMAIL">Email</option>
                        <option value="WHATSAPP">WhatsApp</option>
                    </select>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Subject (email)</label>
                <input type="text" wire:model="messageSubject" class="w-full rounded-md border-slate-300 text-sm" />
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Body HTML</label>
                <textarea wire:model="messageBodyHtml" rows="4" class="w-full rounded-md border-slate-300 text-sm font-mono"></textarea>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Body Text</label>
                <textarea wire:model="messageBodyText" rows="4" class="w-full rounded-md border-slate-300 text-sm font-mono"></textarea>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">WhatsApp Body</label>
                <textarea wire:model="messageWhatsappBody" rows="3" class="w-full rounded-md border-slate-300 text-sm font-mono"></textarea>
            </div>
            <div class="flex gap-2">
                <button type="button" wire:click="saveMessage" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Simpan</button>
                @if ($editingMessageId)
                    <button type="button" wire:click="resetMessageForm" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700">Batal</button>
                @endif
                <button type="button" wire:click="previewMessage" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Preview</button>
                <button type="button" wire:click="resetPreview" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Reset</button>
            </div>

            @if ($previewSubject !== null)
                <div class="rounded-md border border-slate-200 bg-slate-50 p-4 space-y-2">
                    <h4 class="text-sm font-medium text-slate-900">Preview</h4>
                    <div>
                        <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">Subject</p>
                        <p class="mt-1 text-sm text-slate-800">{{ $previewSubject }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">Body</p>
                        <div class="mt-1 rounded-md border border-slate-200 bg-white p-3 text-sm text-slate-800">{!! $previewBody !!}</div>
                    </div>
                    @if ($previewWhatsappBody !== null)
                        <div>
                            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">WhatsApp</p>
                            <p class="mt-1 text-sm text-slate-800 whitespace-pre-line">{{ $previewWhatsappBody }}</p>
                        </div>
                    @endif
                    @if ($previewUnknownVariables !== [])
                        <div class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                            Perhatian: variabel berikut tidak dikenal dan akan tetap tertulis apa adanya di template: {{ implode(', ', $previewUnknownVariables) }}.
                        </div>
                    @endif
                </div>
            @endif

            <div class="rounded-md border border-slate-200 bg-slate-50 p-4">
                <h4 class="text-sm font-medium text-slate-900">Variabel template</h4>
                <p class="mt-1 text-xs text-slate-600">
                    Tulis isi pesan dengan placeholder di antara kurung kurawal, misalnya <code class="rounded bg-slate-200 px-1">&#123;&#123;contact_name&#125;&#125;</code> atau <code class="rounded bg-slate-200 px-1">&#123;&#123;company_name&#125;&#125;</code>.
                    Dua format penulisan variabel diperbolehkan: <code class="rounded bg-slate-200 px-1">&#123;&#123;contact_name&#125;&#125;</code> dan <code class="rounded bg-slate-200 px-1">&#123;&#123; contact_name &#125;&#125;</code>.
                    Variabel yang tersedia: company_name, contact_name, email, phone, industry, source, proposal_number, proposal_date, sender_name, sender_email, sender_phone, sender_website, sender_company.
                </p>
            </div>
        </div>

        <div class="bg-white border border-slate-200 rounded-lg overflow-hidden">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-slate-500">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium">Nama</th>
                        <th class="px-4 py-3 text-left font-medium">Channel</th>
                        <th class="px-4 py-3 text-left font-medium">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($messageTemplates as $t)
                        <tr wire:key="mt-{{ $t->id }}">
                            <td class="px-4 py-3">{{ $t->name }}</td>
                            <td class="px-4 py-3">{{ $t->channel }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <button type="button" wire:click="editMessage('{{ $t->id }}')" class="text-blue-600 hover:underline text-sm">Edit</button>
                                    <button type="button" wire:click="deleteMessage('{{ $t->id }}')" class="text-red-600 hover:underline text-sm">Hapus</button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="bg-white border border-slate-200 rounded-lg p-4 space-y-4">
            <h3 class="font-medium text-slate-900">{{ $editingEmailId ? 'Edit Template Email' : 'Buat Template Email' }}</h3>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Nama</label>
                <input type="text" wire:model="emailName" class="w-full rounded-md border-slate-300 text-sm" />
                @error('emailName') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Subject</label>
                <input type="text" wire:model="emailSubject" class="w-full rounded-md border-slate-300 text-sm" />
                @error('emailSubject') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Body</label>
                <textarea wire:model="emailBody" rows="6" class="w-full rounded-md border-slate-300 text-sm font-mono"></textarea>
                @error('emailBody') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="flex gap-2">
                <button type="button" wire:click="saveEmail" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Simpan</button>
                @if ($editingEmailId)
                    <button type="button" wire:click="resetEmailForm" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700">Batal</button>
                @endif
                <button type="button" wire:click="previewEmail" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Preview</button>
                <button type="button" wire:click="resetPreview" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Reset</button>
            </div>

            @if ($previewSubject !== null)
                <div class="rounded-md border border-slate-200 bg-slate-50 p-4 space-y-2">
                    <h4 class="text-sm font-medium text-slate-900">Preview</h4>
                    <div>
                        <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">Subject</p>
                        <p class="mt-1 text-sm text-slate-800">{{ $previewSubject }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">Body</p>
                        <div class="mt-1 rounded-md border border-slate-200 bg-white p-3 text-sm text-slate-800">{!! $previewBody !!}</div>
                    </div>
                    @if ($previewUnknownVariables !== [])
                        <div class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                            Perhatian: variabel berikut tidak dikenal dan akan tetap tertulis apa adanya di template: {{ implode(', ', $previewUnknownVariables) }}.
                        </div>
                    @endif
                </div>
            @endif

            <div class="rounded-md border border-slate-200 bg-slate-50 p-4">
                <h4 class="text-sm font-medium text-slate-900">Variabel template</h4>
                <p class="mt-1 text-xs text-slate-600">
                    Tulis isi email dengan placeholder di antara kurung kurawal, misalnya <code class="rounded bg-slate-200 px-1">&#123;&#123;contact_name&#125;&#125;</code> atau <code class="rounded bg-slate-200 px-1">&#123;&#123;company_name&#125;&#125;</code>.
                    Variabel yang tersedia: company_name, contact_name, email, phone, industry, source, proposal_number, proposal_date, sender_name, sender_email, sender_phone, sender_website, sender_company.
                </p>
            </div>
        </div>

        <div class="bg-white border border-slate-200 rounded-lg overflow-hidden">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-slate-500">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium">Nama</th>
                        <th class="px-4 py-3 text-left font-medium">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($emailTemplates as $t)
                        <tr wire:key="et-{{ $t->id }}">
                            <td class="px-4 py-3">{{ $t->name }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <button type="button" wire:click="editEmail('{{ $t->id }}')" class="text-blue-600 hover:underline text-sm">Edit</button>
                                    <button type="button" wire:click="deleteEmail('{{ $t->id }}')" class="text-red-600 hover:underline text-sm">Hapus</button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <div class="text-xs text-slate-500">
        Template digunakan saat membuat Outreach (Message Template) dan Email Drafts (Email Template).
    </div>
</div>