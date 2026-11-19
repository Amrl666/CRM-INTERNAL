<div class="space-y-6">
    <div class="flex items-center justify-between gap-4">
        <div>
            <h2 class="text-lg font-semibold text-slate-900">Impor lead dari CSV / XLSX</h2>
            <p class="mt-1 text-sm text-slate-500">Unggah file, periksa pratinjau, lalu konfirmasi untuk memproses lead.</p>
        </div>
        <a href="{{ route('leads.index') }}" wire:navigate class="shrink-0 text-sm text-slate-600 hover:text-slate-900">Kembali</a>
    </div>

    @if (! $batch)
        <div class="bg-white border border-slate-200 rounded-lg p-6 space-y-5">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h3 class="text-sm font-semibold text-slate-900">1. Unggah file</h3>
                    <p class="mt-1 text-sm text-slate-600">Pilih file CSV atau XLSX yang berisi data lead.</p>
                </div>
                <a href="{{ asset('templates/import-lead-template.csv') }}" download
                    class="inline-flex items-center gap-2 text-sm text-blue-600 hover:text-blue-700 hover:underline">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                    Unduh template CSV
                </a>
            </div>

            <form wire:submit="uploadFile" class="space-y-4">
                <div>
                    <span class="block text-sm font-medium text-slate-700">File CSV / XLSX</span>
                    <label for="lead-import-file"
                        class="mt-2 flex cursor-pointer flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed border-slate-300 bg-slate-50 px-6 py-8 text-center transition hover:border-blue-400 hover:bg-blue-50 focus-within:border-blue-500 focus-within:ring-2 focus-within:ring-blue-500 focus-within:ring-offset-1">
                        <svg class="h-8 w-8 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V9.75m0 0l3 3m-3-3l-3 3M6.75 19.5a4.5 4.5 0 01-1.41-8.775 5.25 5.25 0 0110.233-2.33 3 3 0 013.758 3.848A3.752 3.752 0 0118 19.5H6.75z" />
                        </svg>
                        <span class="text-sm font-medium text-slate-900">Pilih file CSV / XLSX</span>
                        <span class="text-xs text-slate-500">Klik area ini untuk memilih file. Ukuran maksimal 20 MB.</span>
                    </label>
                    <input id="lead-import-file" type="file" wire:model="file" accept=".csv,.xlsx" class="sr-only" />

                    @if ($file)
                        <div class="mt-2 flex items-center justify-between gap-3 rounded-md border border-slate-200 bg-white px-3 py-2 text-sm">
                            <span class="truncate text-slate-700">{{ $file->getClientOriginalName() }}</span>
                            <button type="button" wire:click="resetImport"
                                class="shrink-0 text-sm font-medium text-slate-500 hover:text-red-600">
                                Batalkan
                            </button>
                        </div>
                    @endif

                    <p class="mt-1 text-xs text-slate-500">Format yang didukung: CSV dan XLSX.</p>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-xs text-slate-500">
                        Kolom wajib: <code class="bg-slate-100 px-1 rounded">company_name</code>,
                        <code class="bg-slate-100 px-1 rounded">contact_name</code>. Opsional:
                        <code class="bg-slate-100 px-1 rounded">email</code>,
                        <code class="bg-slate-100 px-1 rounded">phone</code>,
                        <code class="bg-slate-100 px-1 rounded">industry</code>,
                        <code class="bg-slate-100 px-1 rounded">source</code>.
                    </p>
                    <div class="flex items-center gap-3">
                        <span wire:loading wire:target="file" class="text-sm text-slate-500">Mengunggah...</span>
                        <button type="submit" wire:loading.attr="disabled" wire:target="file,uploadFile"
                            class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60">
                            <span wire:loading.remove wire:target="uploadFile">Unggah &amp; pratinjau</span>
                            <span wire:loading wire:target="uploadFile">Memuat pratinjau...</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    @endif

    @error('file')
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
            {{ $message }}
        </div>
    @enderror

    @if ($duplicateFile)
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800" role="status">
            <p class="font-medium">File ini sudah pernah diunggah.</p>
            <p class="mt-1">
                @if ($batchId)
                    Sistem tidak memproses ulang file yang sama untuk mencegah duplikasi data. Lihat status impor di bawah.
                @else
                    File yang identik sudah pernah diunggah sebelumnya. Sistem tidak memproses ulang file yang sama untuk mencegah duplikasi data.
                @endif
            </p>
        </div>
    @endif

    @if (count($rows) > 0 && ! $duplicateFile)
        <div class="bg-white border border-slate-200 rounded-lg overflow-hidden">
            <div class="flex flex-wrap items-start justify-between gap-2 border-b border-slate-200 px-4 py-3">
                <div>
                    <h3 class="text-sm font-semibold text-slate-900">2. Pratinjau data</h3>
                    <p class="mt-1 text-xs text-slate-500">Menampilkan {{ count($rows) }} baris pertama. Periksa data sebelum mengimpor.</p>
                </div>
                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600">{{ count($headers) }} kolom</span>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-slate-500">
                        <tr>
                            @foreach ($headers as $header)
                                <th class="whitespace-nowrap px-4 py-2 font-medium">{{ $header }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($rows as $row)
                            <tr>
                                @foreach ($headers as $header)
                                    <td class="whitespace-nowrap px-4 py-2 text-slate-700">{{ $row[$header] ?? '' }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 px-4 py-3">
                <button type="button" wire:click="resetImport" wire:loading.attr="disabled" wire:target="resetImport"
                    class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60">
                    Ganti file
                </button>
                <button type="button" wire:click="confirm" wire:loading.attr="disabled" wire:target="confirm"
                    class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60">
                    <span wire:loading.remove wire:target="confirm">Konfirmasi impor</span>
                    <span wire:loading wire:target="confirm">Memproses...</span>
                </button>
            </div>
        </div>
    @endif

    @if ($batch && ! ($batch->status === 'ready' && count($rows) > 0))
        @php
            $statusLabels = [
                'pending' => 'Menunggu',
                'ready' => 'Siap diimpor',
                'queued' => 'Dalam antrean',
                'processing' => 'Sedang diproses',
                'completed' => 'Selesai',
                'failed' => 'Gagal',
            ];
            $statusClasses = [
                'pending' => 'bg-slate-100 text-slate-700',
                'ready' => 'bg-blue-50 text-blue-700',
                'queued' => 'bg-amber-50 text-amber-700',
                'processing' => 'bg-amber-50 text-amber-700',
                'completed' => 'bg-green-50 text-green-700',
                'failed' => 'bg-red-50 text-red-700',
            ];
        @endphp

        <div class="bg-white border border-slate-200 rounded-lg p-6 space-y-4" aria-live="polite"
            @if (in_array($batch->status, ['queued', 'processing'], true)) wire:poll.3s @endif>
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 class="text-sm font-semibold text-slate-900">Status impor</h3>
                    <p class="mt-1 text-sm text-slate-600">{{ $batch->filename }}</p>
                    @if ($batch->total_rows > 0)
                        <p class="mt-0.5 text-xs text-slate-500">{{ $batch->total_rows }} baris terbaca</p>
                    @endif
                </div>
                <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $statusClasses[$batch->status] ?? 'bg-slate-100 text-slate-700' }}" role="status">
                    {{ $statusLabels[$batch->status] ?? ucfirst($batch->status) }}
                </span>
            </div>

            @if ($batch->status !== 'ready')
                <div class="grid grid-cols-3 gap-3 text-center">
                    <div class="rounded-md bg-green-50 px-3 py-2">
                        <div class="text-lg font-semibold text-green-700">{{ $batch->success_rows }}</div>
                        <div class="text-xs text-green-700">Sukses</div>
                    </div>
                    <div class="rounded-md bg-amber-50 px-3 py-2">
                        <div class="text-lg font-semibold text-amber-700">{{ $batch->duplicate_rows }}</div>
                        <div class="text-xs text-amber-700">Duplikat</div>
                    </div>
                    <div class="rounded-md bg-red-50 px-3 py-2">
                        <div class="text-lg font-semibold text-red-700">{{ $batch->failed_rows }}</div>
                        <div class="text-xs text-red-700">Gagal</div>
                    </div>
                </div>
            @endif

            @if ($batch->status === 'ready')
                <p class="text-sm text-slate-600">File sudah diunggah dan siap diproses. Konfirmasi untuk mulai mengimpor lead.</p>
            @elseif (in_array($batch->status, ['queued', 'processing'], true))
                <p class="text-sm text-slate-500">Impor berjalan di latar belakang. Halaman ini diperbarui otomatis.</p>
            @elseif ($batch->status === 'completed')
                <p class="text-sm text-green-700">Impor selesai. {{ $batch->success_rows }} lead berhasil ditambahkan.</p>
            @elseif ($batch->status === 'failed')
                <p class="text-sm text-red-700">Impor gagal diproses. Kamu dapat mencoba ulang batch ini tanpa mengulang baris yang sudah selesai.</p>
            @endif

            @if ($batch->status === 'completed' && ($outreachUrl ?? null) !== null)
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-md border border-blue-200 bg-blue-50 px-4 py-3">
                    <p class="text-sm text-blue-700">
                        Semua penerima eligible dari import ini otomatis tersedia sebagai kandidat outreach.
                    </p>
                    <a href="{{ $outreachUrl }}" wire:navigate class="inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                        Buat Outreach dari Import
                    </a>
                </div>
            @endif

            @if ($batch->failed_rows > 0)
                <div class="space-y-1 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">
                    <p class="font-medium">Baris gagal</p>
                    @foreach ($batch->rows->where('status', 'failed')->take(10) as $failedRow)
                        <div wire:key="failed-row-{{ $failedRow->id }}">Baris {{ $failedRow->row_number }}: {{ $failedRow->error }}</div>
                    @endforeach
                    @if ($batch->failed_rows > 10)
                        <div class="text-xs">Dan {{ $batch->failed_rows - 10 }} baris gagal lainnya.</div>
                    @endif
                </div>
            @endif

            <div class="flex flex-wrap items-center justify-end gap-3">
                @if ($batch->status === 'ready')
                    <button type="button" wire:click="resetImport" wire:loading.attr="disabled" wire:target="resetImport"
                        class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60">
                        Ganti file
                    </button>
                    <button type="button" wire:click="confirm" wire:loading.attr="disabled" wire:target="confirm"
                        class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60">
                        <span wire:loading.remove wire:target="confirm">Konfirmasi impor</span>
                        <span wire:loading wire:target="confirm">Memproses...</span>
                    </button>
                @elseif (in_array($batch->status, ['failed', 'processing'], true))
                    <button type="button" wire:click="retry" wire:loading.attr="disabled" wire:target="retry"
                        class="rounded-md bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-700 disabled:cursor-not-allowed disabled:opacity-60">
                        <span wire:loading.remove wire:target="retry">
                            {{ $batch->status === 'failed' ? 'Coba impor lagi' : 'Pulihkan impor' }}
                        </span>
                        <span wire:loading wire:target="retry">Memeriksa batch...</span>
                    </button>
                    @if ($batch->status === 'failed')
                        <button type="button" wire:click="resetImport" wire:loading.attr="disabled" wire:target="resetImport"
                            class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60">
                            Impor file lain
                        </button>
                    @endif
                @elseif ($batch->status === 'completed')
                    <button type="button" wire:click="resetImport" wire:loading.attr="disabled" wire:target="resetImport"
                        class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60">
                        Impor file lain
                    </button>
                @endif
            </div>
        </div>
    @endif
</div>
