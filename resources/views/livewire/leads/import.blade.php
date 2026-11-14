<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <h2 class="text-lg font-semibold text-slate-900">Impor lead dari CSV / XLSX</h2>
        <a href="{{ route('leads.index') }}" wire:navigate class="text-sm text-slate-600 hover:text-slate-900">Kembali</a>
    </div>

    <div class="bg-white border border-slate-200 rounded-lg p-6 space-y-4">
        <p class="text-sm text-slate-600">
            Kolom wajib: <code class="bg-slate-100 px-1 rounded">company_name</code>,
            <code class="bg-slate-100 px-1 rounded">contact_name</code>. Opsional:
            <code class="bg-slate-100 px-1 rounded">email</code>,
            <code class="bg-slate-100 px-1 rounded">phone</code>,
            <code class="bg-slate-100 px-1 rounded">industry</code>,
            <code class="bg-slate-100 px-1 rounded">source</code>.
        </p>

        <input type="file" wire:model="file" accept=".csv,.xlsx" class="text-sm"/>
        <div wire:loading wire:target="file" class="text-sm text-slate-500">Mengunggah...</div>
        @error('file') <div class="text-sm text-red-600">{{ $message }}</div> @enderror

        @if ($duplicateFile)
            <div class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded px-3 py-2">
                File ini sudah pernah diimpor (idempoten). Tidak ada pemrosesan ulang.
            </div>
        @endif
    </div>

    @if (count($rows) > 0 && ! $duplicateFile)
        <div class="bg-white border border-slate-200 rounded-lg overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-200 text-sm text-slate-600">Pratinjau {{ count($rows) }} baris pertama</div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-slate-500">
                        <tr>
                            @foreach ($headers as $header)
                                <th class="px-4 py-2 font-medium">{{ $header }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($rows as $row)
                            <tr>
                                @foreach ($headers as $header)
                                    <td class="px-4 py-2 text-slate-700">{{ $row[$header] ?? '' }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="flex justify-end">
            <button wire:click="confirm" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                Konfirmasi impor
            </button>
        </div>
    @endif

    @if ($batch)
        <div class="bg-white border border-slate-200 rounded-lg p-6 space-y-3" @if (in_array($batch->status, ['queued', 'processing'], true)) wire:poll.3s @endif>
            <div class="flex items-center justify-between text-sm">
                <div>
                    <div class="font-medium text-slate-900">{{ $batch->filename }}</div>
                    <div class="text-slate-500">Status: {{ $batch->status }}</div>
                </div>
                <div class="text-slate-600">
                    {{ $batch->success_rows }} sukses &middot;
                    {{ $batch->duplicate_rows }} duplikat &middot;
                    {{ $batch->failed_rows }} gagal
                </div>
            </div>

            @if ($batch->failed_rows > 0)
                <div class="text-sm text-red-700 space-y-1">
                    @foreach ($batch->rows->where('status', 'failed')->take(10) as $failedRow)
                        <div>Baris {{ $failedRow->row_number }}: {{ $failedRow->error }}</div>
                    @endforeach
                </div>
            @endif
        </div>
    @endif
</div>
