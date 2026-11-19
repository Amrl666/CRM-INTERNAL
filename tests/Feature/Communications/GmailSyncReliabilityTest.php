<?php

use App\Jobs\SyncGmailMessagesJob;
use Illuminate\Queue\Middleware\WithoutOverlapping;

it('keeps job timeout, horizon timeout, retry_after, and overlap expiry aligned', function () {
    $job = new SyncGmailMessagesJob('account-123');

    $horizonTimeout = (int) config('horizon.defaults.supervisor-1.timeout');
    $redisRetryAfter = (int) config('queue.connections.redis.retry_after');
    $databaseRetryAfter = (int) config('queue.connections.database.retry_after');
    $lockTtlMinutes = (int) config('communications.email.gmail.sync.lock_ttl_minutes');
    $staleAfterMinutes = (int) config('communications.email.gmail.sync.stale_after_minutes');
    $middleware = $job->middleware()[0];

    expect($job->timeout)->toBe(120)
        ->and($horizonTimeout)->toBe(130)
        ->and($redisRetryAfter)->toBe(150)
        ->and($job->timeout)->toBeLessThan($horizonTimeout)
        ->and($horizonTimeout)->toBeLessThan($redisRetryAfter)
        ->and($databaseRetryAfter)->toBeGreaterThan($job->timeout)
        ->and($middleware)->toBeInstanceOf(WithoutOverlapping::class)
        ->and($middleware->expiresAfter)->toBeGreaterThan($redisRetryAfter)
        ->and($middleware->releaseAfter)->toBeLessThan($job->timeout)
        ->and($staleAfterMinutes)->toBe(30)
        ->and($lockTtlMinutes)->toBeGreaterThan($staleAfterMinutes);
});

it('documents the required queue and run ownership settings', function () {
    $env = file_get_contents(base_path('.env.example'));

    expect($env)->toContain('HORIZON_SUPERVISOR_TIMEOUT=130')
        ->and($env)->toContain('REDIS_QUEUE_RETRY_AFTER=150')
        ->and($env)->toContain('GMAIL_SYNC_STALE_AFTER_MINUTES=30');
});
