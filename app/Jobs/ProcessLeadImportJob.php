<?php

namespace App\Jobs;

use App\Domain\Leads\Models\ImportBatch;
use App\Domain\Leads\Services\LeadImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

class ProcessLeadImportJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public function __construct(
        public readonly string $batchId,
        public readonly string $diskPath,
    ) {
        $this->onQueue('imports');
    }

    public function handle(LeadImportService $service): void
    {
        $batch = ImportBatch::findOrFail($this->batchId);

        $batch->update(['status' => 'processing']);

        try {
            $path = Storage::disk('local')->path($this->diskPath);
            $extension = pathinfo($path, PATHINFO_EXTENSION);

            $service->processBatch($batch, $path, $extension);
        } catch (\Throwable $e) {
            $batch->update(['status' => 'failed']);
            throw $e;
        }
    }
}
