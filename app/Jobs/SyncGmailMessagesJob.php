<?php

namespace App\Jobs;

use App\Domain\Communications\Models\CommunicationAccount;
use App\Domain\Communications\Services\GmailApiException;
use App\Domain\Communications\Services\GmailAuthenticationException;
use App\Domain\Communications\Services\GmailHistoryExpiredException;
use App\Domain\Communications\Services\GmailMessageSyncService;
use App\Domain\Communications\Services\GmailSyncStatus;
use App\Jobs\Middleware\WithoutOverlappingOnStore;
use Google\Service\Exception as GoogleServiceException;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class SyncGmailMessagesJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    private const MAX_PAGES = 100;

    public const MODE_FULL = 'full';

    public const MODE_INCREMENTAL = 'incremental';

    public const MODE_RESYNC = 'resync';

    public int $tries = 3;

    public int $timeout = 120;

    public int $uniqueFor = 3600;

    /**
     * Set by the UniqueJobSkipped listener when Laravel refuses to enqueue
     * this job because another identical page job already holds the lock.
     */
    public bool $uniqueDispatchSkipped = false;

    public function __construct(
        public readonly string $accountId,
        public readonly ?string $pageToken = null,
        public readonly int $pageNumber = 1,
        public ?string $runId = null,
        public readonly string $mode = self::MODE_FULL,
        public readonly ?string $historyId = null,
        public readonly ?string $checkpointHistoryId = null,
        public readonly ?string $baselineHistoryId = null,
    ) {
        $this->runId ??= (string) Str::uuid();

        $this->onQueue('communications');
    }

    public function uniqueId(): string
    {
        $tokenHash = hash('sha256', $this->pageToken ?? 'first-page');
        $scope = $this->mode.':'.($this->historyId ?? 'none');

        return "gmail-sync:{$this->accountId}:{$scope}:{$tokenHash}";
    }

    public function uniqueVia(): CacheRepository
    {
        return Cache::store($this->syncCacheStore());
    }

    /**
     * Retry with a longer delay for transient Gmail failures and rate limits.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlappingOnStore(
                "gmail-sync:account:{$this->accountId}",
                releaseAfter: 30,
                expiresAfter: 300,
                store: $this->syncCacheStore(),
            )),
        ];
    }

    /**
     * The shared store used by the running marker, unique lock, and overlap
     * lock. Null means Laravel's configured default cache store.
     */
    private function syncCacheStore(): ?string
    {
        $store = config('communications.email.gmail.sync.cache_store');

        return is_string($store) && $store !== '' ? $store : null;
    }

    public function handle(
        GmailMessageSyncService $service,
        ?GmailSyncStatus $status = null,
    ): void {
        $status ??= app(GmailSyncStatus::class);

        $runId = $status->heartbeat($this->accountId, $this->runId);

        if ($runId === null) {
            Log::warning('Gmail sync job discarded because another run owns the account.', [
                'account_id' => $this->accountId,
                'page_number' => $this->pageNumber,
                'run_id' => $this->runId,
            ]);

            return;
        }

        // A direct dispatch may not carry a run id; persist the run id that
        // was adopted or claimed so retries and the failed() callback keep
        // operating on the same run.
        $this->runId = $runId;

        $account = CommunicationAccount::query()
            ->whereKey($this->accountId)
            ->where('provider', 'gmail')
            ->first();

        if ($account === null || ! $account->isConnected()) {
            $this->recordFailure(
                $status,
                'Akun Gmail tidak tersedia atau tidak terhubung.',
                $runId,
            );

            Log::warning('Gmail sync skipped: account unavailable or disconnected.', [
                'account_id' => $this->accountId,
                'run_id' => $runId,
                'page_number' => $this->pageNumber,
            ]);

            return;
        }

        $baselineHistoryId = $this->historyId;

        if ($this->mode === self::MODE_INCREMENTAL && blank($this->historyId)) {
            $this->handleExpiredHistory(
                $status,
                $account,
                $runId,
                new GmailHistoryExpiredException,
            );

            return;
        }

        try {
            // Capture the baseline before the first full-sync page. The
            // incremental catch-up dispatched at the end will cover messages
            // that arrive while the full pages are being listed.
            if ($this->mode !== self::MODE_INCREMENTAL && blank($baselineHistoryId)) {
                $baselineHistoryId = $service->currentHistoryId($account);

                if (blank($baselineHistoryId)) {
                    throw new RuntimeException(
                        'Gmail tidak memberikan history ID baseline untuk sinkronisasi penuh.'
                    );
                }
            }

            $result = $this->mode === self::MODE_INCREMENTAL
                ? $service->syncHistoryPage(
                    $account,
                    (string) $this->historyId,
                    $this->pageToken,
                )
                : $service->syncPage($account, $this->pageToken);
        } catch (GmailHistoryExpiredException $e) {
            $this->handleExpiredHistory($status, $account, $runId, $e);

            return;
        } catch (GmailAuthenticationException $e) {
            if ($e->shouldDisconnect) {
                $this->recordFailure($status, $e->getMessage(), $runId);

                Log::error('Gmail synchronization requires the primary account to be reconnected.', [
                    'account_id' => $this->accountId,
                    'run_id' => $runId,
                    'page_number' => $this->pageNumber,
                ]);

                return;
            }

            Log::warning('Gmail authentication is temporarily unavailable; the job will retry.', [
                'account_id' => $this->accountId,
                'run_id' => $runId,
                'page_number' => $this->pageNumber,
            ]);

            throw $e;
        } catch (GmailApiException $e) {
            if ($e->retryable) {
                Log::warning('Gmail API request failed; the job will retry.', [
                    'account_id' => $this->accountId,
                    'run_id' => $runId,
                    'page_number' => $this->pageNumber,
                    'http_status' => $e->httpStatus,
                ]);

                throw $e;
            }

            $this->recordFailure($status, $e->getMessage(), $runId);

            Log::error('Gmail synchronization stopped due to a non-retryable API error.', [
                'account_id' => $this->accountId,
                'run_id' => $runId,
                'page_number' => $this->pageNumber,
                'http_status' => $e->httpStatus,
            ]);

            return;
        } catch (GoogleServiceException $e) {
            Log::warning(
                $this->isRateLimited($e)
                    ? 'Gmail API rate limit reached; the job will retry.'
                    : 'Gmail API request failed; the job will retry.',
                [
                    'account_id' => $this->accountId,
                    'run_id' => $runId,
                    'page_number' => $this->pageNumber,
                    'http_status' => $e->getCode(),
                ],
            );

            throw $e;
        }

        $nextPageToken = $result['next_page_token'] ?? null;

        if (filled($nextPageToken)) {
            if ($this->pageNumber >= self::MAX_PAGES) {
                $this->recordFailure(
                    $status,
                    'Sinkronisasi dihentikan karena mencapai batas maksimum halaman.',
                    $runId,
                );

                Log::warning('Gmail sync stopped at maximum pagination limit.', [
                    'account_id' => $this->accountId,
                    'run_id' => $runId,
                    'page_number' => $this->pageNumber,
                    'processed' => $result['processed'],
                ]);

                return;
            }

            $checkpointHistoryId = $result['history_id'] ?? null;

            self::dispatch(
                $this->accountId,
                $nextPageToken,
                $this->pageNumber + 1,
                $runId,
                mode: $this->mode,
                historyId: $this->historyId,
                checkpointHistoryId: is_string($checkpointHistoryId) && $checkpointHistoryId !== ''
                    ? $checkpointHistoryId
                    : $this->checkpointHistoryId,
                baselineHistoryId: $baselineHistoryId,
            )->onQueue('communications');

            return;
        }

        if ($this->mode !== self::MODE_INCREMENTAL) {
            if (blank($baselineHistoryId)) {
                $baselineHistoryId = $service->currentHistoryId($account);
            }

            if (blank($baselineHistoryId)) {
                $this->recordFailure(
                    $status,
                    'Sinkronisasi penuh selesai tetapi Gmail tidak memberikan history ID baseline.',
                    $runId,
                );

                Log::error('Gmail full sync could not schedule incremental catch-up.', [
                    'account_id' => $this->accountId,
                    'run_id' => $runId,
                ]);

                return;
            }

            self::dispatch(
                $this->accountId,
                null,
                1,
                $runId,
                mode: self::MODE_INCREMENTAL,
                historyId: $baselineHistoryId,
            )->onQueue('communications');

            Log::info('Gmail full sync completed; incremental catch-up queued.', [
                'account_id' => $this->accountId,
                'run_id' => $runId,
                'mode' => $this->mode,
            ]);

            // The marker stays owned by this run until the catch-up chain
            // commits the checkpoint and marks the run completed.
            return;
        }

        $this->commitCheckpoint($status, $service, $account, $runId, $result);

        if (! $status->markCompleted($this->accountId, $runId)) {
            Log::warning('Gmail sync completion discarded because another run owns the account.', [
                'account_id' => $this->accountId,
                'run_id' => $runId,
                'page_number' => $this->pageNumber,
            ]);

            return;
        }

        $account->forceFill([
            'last_synced_at' => now(),
        ])->save();

        Log::info('Gmail inbox synchronization completed.', [
            'account_id' => $this->accountId,
            'run_id' => $runId,
            'page_number' => $this->pageNumber,
            'processed' => $result['processed'],
        ]);
    }

    /**
     * Handle a job failure after all retry attempts are exhausted.
     */
    public function failed(?Throwable $exception = null): void
    {
        $message = $exception?->getMessage();

        if (blank($message)) {
            $message = 'Sinkronisasi Gmail gagal tanpa detail error.';
        }

        $status = app(GmailSyncStatus::class);

        if ($status->markFailed($this->accountId, $message, $this->runId)) {
            $status->markAttempt($this->accountId);
        }

        Log::error('Gmail inbox synchronization failed.', [
            'account_id' => $this->accountId,
            'run_id' => $this->runId,
            'page_number' => $this->pageNumber,
            'exception' => $exception ? $exception::class : null,
            'message' => $exception?->getMessage(),
        ]);
    }

    /**
     * Advance the history cursor only after every page has been processed.
     * Exceptions propagate so the queue retry keeps the old checkpoint intact.
     */
    private function commitCheckpoint(
        GmailSyncStatus $status,
        GmailMessageSyncService $service,
        CommunicationAccount $account,
        string $runId,
        array $result,
    ): void {
        $target = filled($result['history_id'] ?? null)
            ? $result['history_id']
            : $this->checkpointHistoryId;

        if (blank($target)) {
            Log::warning('Gmail incremental checkpoint was not advanced because the history response had no id.', [
                'account_id' => $this->accountId,
                'run_id' => $runId,
            ]);

            return;
        }

        $committed = $status->withOwnedRun(
            $this->accountId,
            $runId,
            fn (): bool => $service->commitIncrementalCheckpoint($account, $target),
        );

        if ($committed !== true) {
            Log::warning('Gmail incremental checkpoint was not committed because the run lost ownership.', [
                'account_id' => $this->accountId,
                'run_id' => $runId,
            ]);

            return;
        }

        Log::info('Gmail incremental checkpoint committed.', [
            'account_id' => $this->accountId,
            'run_id' => $runId,
        ]);
    }

    /**
     * Expired history is recoverable: keep CRM data, clear only the invalid
     * cursor, and schedule a full resync under the same run ownership.
     */
    private function handleExpiredHistory(
        GmailSyncStatus $status,
        CommunicationAccount $account,
        string $runId,
        GmailHistoryExpiredException $exception,
    ): void {
        if (! $status->markHistoryExpired($account->id, $runId, $exception->getMessage())) {
            Log::warning('Gmail history expiry was ignored because the run no longer owns the account.', [
                'account_id' => $this->accountId,
                'run_id' => $runId,
            ]);

            return;
        }

        $account->forceFill([
            'gmail_history_id' => null,
        ])->save();

        self::dispatch(
            $account->id,
            null,
            1,
            $runId,
            mode: self::MODE_RESYNC,
        )->onQueue('communications');

        Log::warning('Gmail history expired; safe full resync scheduled.', [
            'account_id' => $this->accountId,
            'run_id' => $runId,
        ]);
    }

    private function recordFailure(
        GmailSyncStatus $status,
        string $message,
        string $runId,
    ): void {
        if ($status->markFailed($this->accountId, $message, $runId)) {
            $status->markAttempt($this->accountId);
        }
    }

    private function isRateLimited(GoogleServiceException $exception): bool
    {
        if ($exception->getCode() === 429) {
            return true;
        }

        $reasons = collect($exception->getErrors() ?? [])
            ->pluck('reason')
            ->filter()
            ->all();

        return array_intersect($reasons, [
            'rateLimitExceeded',
            'userRateLimitExceeded',
            'quotaExceeded',
        ]) !== [];
    }
}
