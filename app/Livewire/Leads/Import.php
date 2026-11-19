<?php

namespace App\Livewire\Leads;

use App\Domain\Leads\Models\ImportBatch;
use App\Domain\Leads\Models\Lead;
use App\Domain\Leads\Services\LeadImportService;
use App\Jobs\ProcessLeadImportJob;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.sidebar', ['heading' => 'Impor Lead'])]
class Import extends Component
{
    use WithFileUploads;

    public $file;

    public ?string $batchId = null;

    public bool $duplicateFile = false;

    public ?string $outreachUrl = null;

    /** @var string[] */
    public array $headers = [];

    /** @var array<int, array<string, string>> */
    public array $rows = [];

    public function mount(): void
    {
        $this->authorize('import', Lead::class);
    }

    public function uploadFile(LeadImportService $service): void
    {
        $this->authorize('import', Lead::class);

        $this->validate([
            'file' => ['required', 'file', 'extensions:csv,xlsx', 'max:20480'],
        ]);

        $this->reset('batchId', 'duplicateFile', 'headers', 'rows');
        $this->resetValidation();

        try {
            $path = $this->file->store('imports', 'local');
        } catch (\Throwable $e) {
            report($e);
            $this->addError('file', 'File gagal diunggah. Silakan coba lagi.');

            return;
        }

        if ($path === false) {
            $this->addError('file', 'File gagal disimpan. Silakan coba lagi.');

            return;
        }

        try {
            $absolute = Storage::disk('local')->path($path);
            $hash = hash_file('sha256', $absolute);
        } catch (\Throwable $e) {
            report($e);
            Storage::disk('local')->delete($path);
            $this->addError('file', 'File tidak dapat dibaca. Silakan coba lagi.');

            return;
        }

        if ($hash === false) {
            Storage::disk('local')->delete($path);
            $this->addError('file', 'File tidak dapat dibaca. Silakan coba lagi.');

            return;
        }

        $existing = ImportBatch::where('file_hash', $hash)->first();

        if ($existing !== null) {
            Storage::disk('local')->delete($path);
            $this->duplicateFile = true;

            if ($existing->created_by === auth()->id()) {
                $this->batchId = $existing->id;
            }

            return;
        }

        $batch = ImportBatch::create([
            'file_hash' => $hash,
            'filename' => $this->file->getClientOriginalName(),
            'status' => 'pending',
            'created_by' => auth()->id(),
        ]);

        try {
            $preview = $service->preview($absolute, $this->file->getClientOriginalExtension());
        } catch (\Throwable $e) {
            report($e);
            Storage::disk('local')->delete($path);
            $batch->delete();
            $this->addError('file', 'File tidak dapat dibaca. Pastikan format CSV/XLSX valid dan kolom wajib tersedia.');

            return;
        }

        $this->batchId = $batch->id;
        $this->headers = $preview['headers'];
        $this->rows = $preview['rows'];

        $batch->update(['status' => 'ready']);
    }

    public function confirm(): void
    {
        $this->authorize('import', Lead::class);

        if ($this->batchId === null) {
            return;
        }

        $batch = ImportBatch::query()
            ->whereKey($this->batchId)
            ->where('created_by', auth()->id())
            ->first();

        if ($batch === null) {
            $this->addError('file', 'Batch impor tidak ditemukan. Silakan unggah ulang file.');

            return;
        }

        if ($batch->status !== 'ready') {
            $this->addError('file', 'Status batch impor sudah berubah. Muat ulang halaman untuk melihat status terbaru.');

            return;
        }

        $target = $this->findStoredFile($batch->file_hash);

        if ($target === null) {
            $this->addError('file', 'File impor tidak ditemukan. Silakan unggah ulang.');

            return;
        }

        $claimed = ImportBatch::query()
            ->whereKey($batch->id)
            ->where('status', 'ready')
            ->update(['status' => 'queued']);

        if ($claimed === 0) {
            $this->addError('file', 'Batch impor sudah diproses atau sedang dalam antrean.');

            return;
        }

        try {
            ProcessLeadImportJob::dispatch($batch->id, $target);
        } catch (\Throwable $e) {
            report($e);
            $batch->update(['status' => 'ready']);
            $this->addError('file', 'Impor gagal masuk antrean. Silakan coba lagi.');

            return;
        }

        $this->reset('file', 'headers', 'rows');
        $this->duplicateFile = false;

        $this->dispatch('notify', message: 'Impor masuk antrean dan akan diproses di latar belakang.', type: 'success');
    }

    public function retry(): void
    {
        $this->authorize('import', Lead::class);

        if ($this->batchId === null) {
            return;
        }

        $batch = ImportBatch::query()
            ->whereKey($this->batchId)
            ->where('created_by', auth()->id())
            ->first();

        if ($batch === null) {
            $this->addError('file', 'Batch impor tidak ditemukan.');

            return;
        }

        if (! in_array($batch->status, ['failed', 'processing'], true)) {
            $this->addError('file', 'Hanya batch gagal atau pemrosesan yang terhenti yang dapat dicoba ulang.');

            return;
        }

        $lock = Cache::lock("lead-import:batch:{$batch->id}", 660);

        if (! $lock->get()) {
            $this->addError('file', 'Batch masih sedang diproses oleh worker. Silakan tunggu.');

            return;
        }

        $target = null;
        $claimed = 0;

        try {
            $batch = ImportBatch::query()
                ->whereKey($this->batchId)
                ->where('created_by', auth()->id())
                ->first();

            if ($batch === null || ! in_array($batch->status, ['failed', 'processing'], true)) {
                $this->addError('file', 'Status batch sudah berubah. Muat ulang halaman.');

                return;
            }

            $target = $this->findStoredFile($batch->file_hash);

            if ($target === null) {
                $this->addError('file', 'File sumber tidak ditemukan. Silakan unggah ulang file.');

                return;
            }

            $claimed = ImportBatch::query()
                ->whereKey($batch->id)
                ->whereIn('status', ['failed', 'processing'])
                ->update(['status' => 'queued']);

            if ($claimed !== 1) {
                $this->addError('file', 'Batch sudah berubah status dan tidak dapat dicoba ulang.');

                return;
            }
        } finally {
            $lock->release();
        }

        try {
            ProcessLeadImportJob::dispatch($batch->id, $target);
        } catch (\Throwable $e) {
            report($e);

            ImportBatch::query()
                ->whereKey($batch->id)
                ->where('status', 'queued')
                ->update(['status' => 'failed']);

            $this->addError('file', 'Retry gagal masuk antrean. Silakan coba lagi.');

            return;
        }

        $this->dispatch('notify', message: 'Impor berhasil dimasukkan kembali ke antrean.', type: 'success');
    }

    public function resetImport(): void
    {
        if ($this->batchId !== null && ! $this->duplicateFile) {
            $batch = ImportBatch::query()
                ->whereKey($this->batchId)
                ->where('created_by', auth()->id())
                ->where('status', 'ready')
                ->first();

            if ($batch !== null) {
                $this->deleteStoredFile($batch->file_hash);
                $batch->delete();
            }
        }

        $this->reset('file', 'batchId', 'duplicateFile', 'headers', 'rows');
        $this->resetValidation();
    }

    public function render()
    {
        $batch = null;

        if ($this->batchId !== null) {
            $batch = ImportBatch::with('rows')
                ->whereKey($this->batchId)
                ->where('created_by', auth()->id())
                ->first();
        }

        return view('livewire.leads.import', [
            'batch' => $batch,
            'outreachUrl' => $this->outreachUrl,
        ]);
    }

    private function findStoredFile(string $hash): ?string
    {
        foreach (Storage::disk('local')->files('imports') as $file) {
            $path = Storage::disk('local')->path($file);

            if (is_file($path) && hash_file('sha256', $path) === $hash) {
                return $file;
            }
        }

        return null;
    }

    private function deleteStoredFile(string $hash): void
    {
        $file = $this->findStoredFile($hash);

        if ($file !== null) {
            Storage::disk('local')->delete($file);
        }
    }
}
