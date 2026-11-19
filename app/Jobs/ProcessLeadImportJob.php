<?php

namespace App\Jobs;

use App\Domain\Leads\Models\ImportBatch;
use App\Domain\Leads\Services\LeadImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
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
        $lock = Cache::lock("lead-import:batch:{$this->batchId}", 660);

        if (! $lock->get()) {
            return;
        }

        try {
            $claimed = ImportBatch::query()
                ->whereKey($this->batchId)
                ->where('status', 'queued')
                ->update(['status' => 'processing']);

            if ($claimed !== 1) {
                return;
            }

            $batch = ImportBatch::findOrFail($this->batchId);

            try {
                $path = Storage::disk('local')->path($this->diskPath);

                if (! is_file($path)) {
                    throw new \RuntimeException('File import tidak ditemukan: '.$this->diskPath);
                }

                $extension = pathinfo($path, PATHINFO_EXTENSION);

                $service->processBatch($batch, $path, $extension);
            } catch (\Throwable $e) {
                ImportBatch::query()
                    ->whereKey($this->batchId)
                    ->where('status', 'processing')
                    ->update(['status' => 'failed']);

                throw $e;
            }
        } finally {
            $lock->release();
        }
    }
}
