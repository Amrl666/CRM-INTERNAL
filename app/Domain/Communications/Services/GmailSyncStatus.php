<?php

namespace App\Domain\Communications\Services;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final class GmailSyncStatus
{
    /**
     * How long the state lock may be held while reading and replacing the
     * running marker. The critical section only performs cache operations.
     */
    private const STATE_LOCK_SECONDS = 10;

    /**
     * How long to wait for the state lock before giving up.
     */
    private const STATE_LOCK_WAIT_SECONDS = 5;

    public function isRunning(string $accountId): bool
    {
        return $this->runningSince($accountId) !== null;
    }

    public function runningSince(string $accountId): ?CarbonImmutable
    {
        $marker = $this->readMarker($accountId);

        if ($marker === null || $this->isStale($marker)) {
            return null;
        }

        return $marker['started_at'];
    }

    /**
     * Return the run id that currently owns the account, if any.
     */
    public function currentRunId(string $accountId): ?string
    {
        $marker = $this->readMarker($accountId);

        if ($marker === null || $this->isStale($marker)) {
            return null;
        }

        return $marker['run_id'];
    }

    /**
     * Atomically claim a synchronization slot for the account.
     *
     * A stale marker (one whose heartbeat has not been refreshed within the
     * configured stale window) may be replaced so a crashed run cannot block
     * the account until the cache entry expires.
     */
    public function start(string $accountId, ?string $runId = null): bool
    {
        $runId = $runId ?: (string) Str::uuid();

        if ($this->cache()->add(
            $this->key($accountId, 'running'),
            $this->newMarker($runId),
            $this->markerTtl(),
        )) {
            return true;
        }

        return $this->withStateLock($accountId, function () use ($accountId, $runId): bool {
            $existing = $this->readMarker($accountId);

            if ($existing === null) {
                return $this->cache()->add(
                    $this->key($accountId, 'running'),
                    $this->newMarker($runId),
                    $this->markerTtl(),
                );
            }

            if (! $this->isStale($existing)) {
                return false;
            }

            $this->cache()->put(
                $this->key($accountId, 'running'),
                $this->newMarker($runId),
                $this->markerTtl(),
            );

            return true;
        }) ?? false;
    }

    /**
     * Refresh the running marker and return the run id that owns it.
     *
     * A null return means the caller no longer owns the active run and must
     * not touch the account's synchronization state.
     */
    public function heartbeat(string $accountId, ?string $runId = null): ?string
    {
        return $this->withStateLock($accountId, function () use ($accountId, $runId): ?string {
            $existing = $this->readMarker($accountId);

            if ($existing === null) {
                $runId ??= (string) Str::uuid();

                return $this->cache()->add(
                    $this->key($accountId, 'running'),
                    $this->newMarker($runId),
                    $this->markerTtl(),
                ) ? $runId : null;
            }

            if ($existing['run_id'] === null) {
                // A marker created before run ownership was introduced can be
                // adopted by the job that is still processing that run.
                if ($runId !== null) {
                    return null;
                }

                $runId = (string) Str::uuid();
            } elseif ($runId === null || $existing['run_id'] !== $runId) {
                return null;
            }

            $this->cache()->put(
                $this->key($accountId, 'running'),
                $this->newMarker($runId, $existing['started_at']),
                $this->markerTtl(),
            );

            return $runId;
        });
    }

    /**
     * Backwards-compatible alias for heartbeat().
     */
    public function markRunning(string $accountId, ?string $runId = null): ?string
    {
        return $this->heartbeat($accountId, $runId);
    }

    /**
     * Clear the running marker only when the caller owns the active run.
     */
    public function markCompleted(string $accountId, ?string $runId = null): bool
    {
        return $this->withStateLock($accountId, function () use ($accountId, $runId): bool {
            if (! $this->ownsMarker($accountId, $runId)) {
                return false;
            }

            $this->cache()->forget($this->key($accountId, 'running'));
            $this->cache()->forget($this->key($accountId, 'failure'));
            $this->cache()->forget($this->key($accountId, 'resync'));
            $this->markAttempt($accountId);

            return true;
        }) ?? false;
    }

    /**
     * Release the running marker only when the caller owns the active run.
     *
     * This is used when a dispatch is skipped before the job ever reaches the
     * queue (for example, a UniqueJobSkipped event). Unlike markCompleted(),
     * it does not record a synchronization attempt or a failure.
     */
    public function release(string $accountId, ?string $runId = null): bool
    {
        return $this->withStateLock($accountId, function () use ($accountId, $runId): bool {
            if (! $this->ownsMarker($accountId, $runId)) {
                return false;
            }

            $this->cache()->forget($this->key($accountId, 'running'));

            return true;
        }) ?? false;
    }

    /**
     * Record a failure only when the caller owns the active run.
     */
    public function markFailed(
        string $accountId,
        string $message,
        ?string $runId = null,
    ): bool {
        return $this->withStateLock($accountId, function () use ($accountId, $runId, $message): bool {
            if (! $this->ownsMarker($accountId, $runId)) {
                return false;
            }

            $this->cache()->forget($this->key($accountId, 'running'));

            $this->cache()->put(
                $this->key($accountId, 'failure'),
                [
                    'message' => Str::limit(trim($message), 500),
                    'occurred_at' => now()->toIso8601String(),
                ],
                now()->addDays($this->failureTtlDays()),
            );

            return true;
        }) ?? false;
    }

    /**
     * Record that the incremental history cursor expired and a full resync is
     * required. Only the run that owns the marker may set this state.
     */
    public function markHistoryExpired(
        string $accountId,
        string $runId,
        string $message,
    ): bool {
        return $this->withStateLock($accountId, function () use (
            $accountId,
            $runId,
            $message,
        ): bool {
            if (! $this->ownsMarker($accountId, $runId)) {
                return false;
            }

            $this->cache()->put(
                $this->key($accountId, 'resync'),
                [
                    'message' => Str::limit(trim($message), 500),
                    'occurred_at' => now()->toIso8601String(),
                ],
                now()->addDays($this->failureTtlDays()),
            );

            return true;
        }) ?? false;
    }

    /**
     * @return array{message: string, occurred_at: CarbonImmutable}|null
     */
    public function historyExpired(string $accountId): ?array
    {
        $resync = $this->cache()->get($this->key($accountId, 'resync'));

        if (! is_array($resync) || blank($resync['message'] ?? null)) {
            return null;
        }

        $occurredAt = $resync['occurred_at'] ?? null;

        return [
            'message' => (string) $resync['message'],
            'occurred_at' => is_string($occurredAt)
                ? CarbonImmutable::parse($occurredAt)
                : CarbonImmutable::now(),
        ];
    }

    /**
     * @return array{message: string, occurred_at: CarbonImmutable}|null
     */
    public function lastFailure(string $accountId): ?array
    {
        $failure = $this->cache()->get($this->key($accountId, 'failure'));

        if (! is_array($failure) || blank($failure['message'] ?? null)) {
            return null;
        }

        $occurredAt = $failure['occurred_at'] ?? null;

        return [
            'message' => (string) $failure['message'],
            'occurred_at' => is_string($occurredAt)
                ? CarbonImmutable::parse($occurredAt)
                : CarbonImmutable::now(),
        ];
    }

    public function markAttempt(string $accountId): void
    {
        $this->cache()->put(
            $this->key($accountId, 'last_attempt'),
            now()->toIso8601String(),
            now()->addMinutes(max(60, $this->intervalMinutes() * 2)),
        );
    }

    public function lastAttemptAt(string $accountId): ?CarbonImmutable
    {
        $value = $this->cache()->get($this->key($accountId, 'last_attempt'));

        return is_string($value) && $value !== ''
            ? CarbonImmutable::parse($value)
            : null;
    }

    public function intervalElapsed(string $accountId): bool
    {
        $lastAttempt = $this->lastAttemptAt($accountId);

        return $lastAttempt === null
            || now()->greaterThanOrEqualTo(
                $lastAttempt->addMinutes($this->intervalMinutes()),
            );
    }

    private function ownsMarker(string $accountId, ?string $runId): bool
    {
        $marker = $this->readMarker($accountId);

        if ($marker === null) {
            return false;
        }

        if ($runId === null) {
            return $marker['run_id'] === null;
        }

        return $marker['run_id'] === $runId;
    }

    /**
     * Run a state mutation only while the caller still owns the active run.
     *
     * Used for checkpoint writes so a stale run can never advance the cursor.
     */
    public function withOwnedRun(
        string $accountId,
        ?string $runId,
        Closure $callback,
    ): mixed {
        return $this->withStateLock($accountId, function () use (
            $accountId,
            $runId,
            $callback,
        ): mixed {
            if (! $this->ownsMarker($accountId, $runId)) {
                return null;
            }

            return $callback();
        });
    }

    /**
     * @return array{run_id: ?string, started_at: CarbonImmutable, heartbeat_at: CarbonImmutable}|null
     */
    private function readMarker(string $accountId): ?array
    {
        $value = $this->cache()->get($this->key($accountId, 'running'));

        if (is_string($value) && $value !== '') {
            $startedAt = CarbonImmutable::parse($value);

            return [
                'run_id' => null,
                'started_at' => $startedAt,
                'heartbeat_at' => $startedAt,
            ];
        }

        if (! is_array($value)) {
            return null;
        }

        $startedAt = $value['started_at'] ?? null;

        if (! is_string($startedAt) || $startedAt === '') {
            return null;
        }

        $heartbeatAt = $value['heartbeat_at'] ?? $startedAt;

        return [
            'run_id' => is_string($value['run_id'] ?? null) && $value['run_id'] !== ''
                ? $value['run_id']
                : null,
            'started_at' => CarbonImmutable::parse($startedAt),
            'heartbeat_at' => is_string($heartbeatAt) && $heartbeatAt !== ''
                ? CarbonImmutable::parse($heartbeatAt)
                : CarbonImmutable::parse($startedAt),
        ];
    }

    /**
     * @return array{run_id: string, started_at: string, heartbeat_at: string}
     */
    private function newMarker(
        string $runId,
        ?CarbonImmutable $startedAt = null,
    ): array {
        $now = CarbonImmutable::now();

        return [
            'run_id' => $runId,
            'started_at' => ($startedAt ?? $now)->toIso8601String(),
            'heartbeat_at' => $now->toIso8601String(),
        ];
    }

    /**
     * @param  array{run_id: ?string, started_at: CarbonImmutable, heartbeat_at: CarbonImmutable}  $marker
     */
    private function isStale(array $marker): bool
    {
        return $marker['heartbeat_at']
            ->addMinutes($this->staleAfterMinutes())
            ->lessThanOrEqualTo(CarbonImmutable::now());
    }

    private function withStateLock(string $accountId, Closure $callback): mixed
    {
        $lock = $this->cache()->lock(
            $this->key($accountId, 'state'),
            self::STATE_LOCK_SECONDS,
        );

        try {
            $lock->block(self::STATE_LOCK_WAIT_SECONDS);
        } catch (LockTimeoutException) {
            return null;
        }

        try {
            return $callback();
        } finally {
            $lock->release();
        }
    }

    private function cache(): Repository
    {
        $store = config('communications.email.gmail.sync.cache_store');

        return Cache::store(
            is_string($store) && $store !== '' ? $store : null,
        );
    }

    private function key(string $accountId, string $suffix): string
    {
        return "gmail-sync:{$accountId}:{$suffix}";
    }

    private function markerTtl(): \DateTimeInterface
    {
        return now()->addMinutes($this->lockTtlMinutes());
    }

    private function lockTtlMinutes(): int
    {
        return max(5, (int) config(
            'communications.email.gmail.sync.lock_ttl_minutes',
            60,
        ));
    }

    private function staleAfterMinutes(): int
    {
        return max(1, min(
            $this->lockTtlMinutes(),
            (int) config(
                'communications.email.gmail.sync.stale_after_minutes',
                30,
            ),
        ));
    }

    private function intervalMinutes(): int
    {
        return max(1, (int) config(
            'communications.email.gmail.sync.interval_minutes',
            15,
        ));
    }

    private function failureTtlDays(): int
    {
        return max(1, (int) config(
            'communications.email.gmail.sync.failure_ttl_days',
            7,
        ));
    }
}
