<?php

namespace App\Domain\Communications\Listeners;

use App\Domain\Communications\Services\GmailSyncStatus;
use App\Jobs\SyncGmailMessagesJob;
use Illuminate\Queue\Events\UniqueJobSkipped;
use Illuminate\Support\Facades\Log;

final class ReleaseGmailSyncOnUniqueJobSkipped
{
    public function __construct(
        private readonly GmailSyncStatus $status,
    ) {}

    /**
     * Laravel silently skips a ShouldBeUnique job when its lock is already
     * held. Release the run marker only when the skipped job owns it, so a
     * stale or newer run is never disturbed.
     */
    public function handle(UniqueJobSkipped $event): void
    {
        $job = $event->job;

        if (! $job instanceof SyncGmailMessagesJob) {
            return;
        }

        $job->uniqueDispatchSkipped = true;

        // A skipped continuation page still has its real counterpart waiting
        // in the queue. Releasing the marker here would let a new run start
        // while that page is pending.
        if ($job->pageNumber > 1) {
            Log::warning('Gmail sync continuation page was skipped because its unique lock is held.', [
                'account_id' => $job->accountId,
                'run_id' => $job->runId,
                'page_number' => $job->pageNumber,
            ]);

            return;
        }

        $released = $this->status->release($job->accountId, $job->runId);

        Log::warning('Gmail sync job was not queued because its unique lock is held.', [
            'account_id' => $job->accountId,
            'run_id' => $job->runId,
            'page_number' => $job->pageNumber,
            'marker_released' => $released,
        ]);
    }
}
