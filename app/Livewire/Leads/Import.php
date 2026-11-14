<?php

namespace App\Livewire\Leads;

use App\Domain\Leads\Models\ImportBatch;
use App\Domain\Leads\Models\Lead;
use App\Domain\Leads\Services\LeadImportService;
use App\Jobs\ProcessLeadImportJob;
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

    /** @var string[] */
    public array $headers = [];

    /** @var array<int, array<string, string>> */
    public array $rows = [];

    public function mount(): void
    {
        $this->authorize('import', Lead::class);
    }

    public function updatedFile(LeadImportService $service): void
    {
        $this->validate([
            'file' => ['required', 'file', 'extensions:csv,xlsx', 'max:20480'],
        ]);

        $path = $this->file->store('imports');
        $absolute = Storage::disk('local')->path($path);
        $hash = hash_file('sha256', $absolute);

        $existing = ImportBatch::where('file_hash', $hash)->first();

        if ($existing !== null) {
            Storage::disk('local')->delete($path);
            $this->batchId = $existing->id;
            $this->duplicateFile = true;

            return;
        }

        $batch = ImportBatch::create([
            'file_hash' => $hash,
            'filename' => $this->file->getClientOriginalName(),
            'status' => 'pending',
            'created_by' => auth()->id(),
        ]);

        $this->batchId = $batch->id;
        $this->duplicateFile = false;

        $preview = $service->preview($absolute, $this->file->getClientOriginalExtension());
        $this->headers = $preview['headers'];
        $this->rows = $preview['rows'];

        $batch->update(['status' => 'ready']);
    }

    public function confirm(): void
    {
        $batch = ImportBatch::findOrFail($this->batchId);

        if ($batch->status !== 'ready') {
            return;
        }

        $files = Storage::disk('local')->files('imports');
        $target = null;

        foreach ($files as $file) {
            if (hash_file('sha256', Storage::disk('local')->path($file)) === $batch->file_hash) {
                $target = $file;
                break;
            }
        }

        if ($target === null) {
            $this->addError('file', 'File impor tidak ditemukan.');

            return;
        }

        $batch->update(['status' => 'queued']);

        ProcessLeadImportJob::dispatch($batch->id, $target);

        $this->redirectRoute('leads.import');
    }

    public function render()
    {
        return view('livewire.leads.import', [
            'batch' => $this->batchId ? ImportBatch::with('rows')->find($this->batchId) : null,
        ]);
    }
}
