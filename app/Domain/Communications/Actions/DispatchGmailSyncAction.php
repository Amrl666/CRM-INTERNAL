<?php

namespace App\Domain\Communications\Actions;

use App\Domain\Communications\DTOs\GmailSyncDispatchResult;
use App\Domain\Communications\Services\GmailAccountAuthenticator;
use App\Domain\Communications\Services\GmailSyncStatus;
use App\Jobs\SyncGmailMessagesJob;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

final class DispatchGmailSyncAction
{
    public function __construct(
        private readonly GmailAccountAuthenticator $authenticator,
        private readonly GmailSyncStatus $status,
    ) {}

    /**
     * Dispatch a Gmail inbox synchronization for the primary account.
     *
     * The scheduler passes respectInterval: true so automatic runs follow the
     * configured interval. Manual runs always bypass the interval.
     */
    public function handle(bool $respectInterval = false): GmailSyncDispatchResult
    {
        $account = $this->authenticator->connectedAccount();

        if ($account === null) {
            return GmailSyncDispatchResult::unavailable();
        }

        if ($respectInterval && ! $this->status->intervalElapsed($account->id)) {
            return GmailSyncDispatchResult::throttled();
        }

        $runId = (string) Str::uuid();

        if (! $this->status->start($account->id, $runId)) {
            return GmailSyncDispatchResult::alreadyRunning();
        }

        $historyId = $account->gmail_history_id;
        $mode = filled($historyId)
            ? SyncGmailMessagesJob::MODE_INCREMENTAL
            : SyncGmailMessagesJob::MODE_FULL;
        $job = null;

        try {
            // PendingDispatch acquires the ShouldBeUnique lock in its
            // destructor. Destroy it here so we can tell whether the job was
            // actually handed to the queue before reporting success.
            $pending = filled($historyId)
                ? SyncGmailMessagesJob::dispatch(
                    $account->id,
                    runId: $runId,
                    mode: SyncGmailMessagesJob::MODE_INCREMENTAL,
                    historyId: (string) $historyId,
                )
                : SyncGmailMessagesJob::dispatch(
                    $account->id,
                    runId: $runId,
                    mode: SyncGmailMessagesJob::MODE_FULL,
                );
            $job = $pending->getJob();
            $pending->onQueue('communications');
            unset($pending);
        } catch (Throwable $e) {
            $this->status->markFailed(
                $account->id,
                'Gagal memulai sinkronisasi Gmail.',
                $runId,
            );

            Log::error('Gmail sync job dispatch failed.', [
                'account_id' => $account->id,
                'run_id' => $runId,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return GmailSyncDispatchResult::failed();
        }

        if (
            $job === null
            || $job->uniqueDispatchSkipped
            || blank($job->uniqueLockOwner)
        ) {
            $this->status->release($account->id, $runId);

            Log::warning('Gmail sync job dispatch was skipped because its unique lock is already held.', [
                'account_id' => $account->id,
                'run_id' => $runId,
                'trigger' => $respectInterval ? 'scheduler' : 'manual',
            ]);

            return GmailSyncDispatchResult::alreadyRunning();
        }

        $this->status->markAttempt($account->id);

        Log::info('Gmail sync job dispatched.', [
            'account_id' => $account->id,
            'run_id' => $runId,
            'trigger' => $respectInterval ? 'scheduler' : 'manual',
            'mode' => $mode,
        ]);

        return GmailSyncDispatchResult::dispatched($account);
    }
}
