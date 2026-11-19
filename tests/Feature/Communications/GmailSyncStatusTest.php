<?php

use App\Domain\Communications\Models\CommunicationAccount;
use App\Domain\Communications\Services\GmailApiException;
use App\Domain\Communications\Services\GmailAuthenticationException;
use App\Domain\Communications\Services\GmailMessageSyncService;
use App\Domain\Communications\Services\GmailSyncStatus;
use App\Jobs\SyncGmailMessagesJob;
use Google\Service\Exception as GoogleServiceException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'communications.email.gmail.primary_email' => 'hello@example.test',
        'communications.email.gmail.sync.enabled' => false,
        'communications.email.gmail.sync.interval_minutes' => 15,
        'communications.email.gmail.sync.lock_ttl_minutes' => 60,
        'communications.email.gmail.sync.stale_after_minutes' => 30,
        'communications.email.gmail.sync.failure_ttl_days' => 7,
    ]);
});

function gmailSyncStatusTestAccount(): CommunicationAccount
{
    return CommunicationAccount::create([
        'provider' => 'gmail',
        'account_email' => 'hello@example.test',
        'display_name' => 'Test Gmail',
        'access_token' => 'test-access-token',
        'refresh_token' => 'test-refresh-token',
        'token_expires_at' => now()->addHour(),
        'connection_status' => 'connected',
    ]);
}

it('claims a running slot atomically and respects the configured interval', function () {
    $status = app(GmailSyncStatus::class);
    $accountId = (string) Str::uuid();

    expect($status->isRunning($accountId))->toBeFalse()
        ->and($status->start($accountId))->toBeTrue();

    $runId = $status->currentRunId($accountId);

    expect($runId)->not->toBeNull()
        ->and($status->start($accountId))->toBeFalse()
        ->and($status->isRunning($accountId))->toBeTrue()
        ->and($status->runningSince($accountId))->not->toBeNull();

    expect($status->markCompleted($accountId, $runId))->toBeTrue();

    expect($status->isRunning($accountId))->toBeFalse()
        ->and($status->intervalElapsed($accountId))->toBeFalse();

    $this->travel(16)->minutes();

    expect($status->intervalElapsed($accountId))->toBeTrue();
});

it('stores a failure and clears it after a successful completion', function () {
    $status = app(GmailSyncStatus::class);
    $accountId = (string) Str::uuid();

    $status->start($accountId);
    $runId = $status->currentRunId($accountId);

    expect($status->markFailed(
        $accountId,
        '  Gmail API down  ',
        $runId,
    ))->toBeTrue();

    $failure = $status->lastFailure($accountId);

    expect($status->isRunning($accountId))->toBeFalse()
        ->and($failure)->not->toBeNull()
        ->and($failure['message'])->toBe('Gmail API down')
        ->and($failure['occurred_at'])->not->toBeNull();

    $status->start($accountId);
    $nextRunId = $status->currentRunId($accountId);

    expect($status->markCompleted($accountId, $nextRunId))->toBeTrue()
        ->and($status->lastFailure($accountId))->toBeNull();
});

it('rejects heartbeats from a run that does not own the marker', function () {
    $status = app(GmailSyncStatus::class);
    $accountId = (string) Str::uuid();
    $ownerRunId = (string) Str::uuid();
    $otherRunId = (string) Str::uuid();

    $status->start($accountId, $ownerRunId);

    expect($status->heartbeat($accountId, $otherRunId))->toBeNull()
        ->and($status->currentRunId($accountId))->toBe($ownerRunId)
        ->and($status->isRunning($accountId))->toBeTrue();

    $this->travel(31)->minutes();

    expect($status->isRunning($accountId))->toBeFalse()
        ->and($status->start($accountId, $otherRunId))->toBeTrue()
        ->and($status->currentRunId($accountId))->toBe($otherRunId);
});

it('only clears the running marker when the completing run owns it', function () {
    $status = app(GmailSyncStatus::class);
    $accountId = (string) Str::uuid();
    $ownerRunId = (string) Str::uuid();
    $otherRunId = (string) Str::uuid();

    $status->start($accountId, $ownerRunId);

    expect($status->markCompleted($accountId, $otherRunId))->toBeFalse()
        ->and($status->markCompleted($accountId, null))->toBeFalse()
        ->and($status->isRunning($accountId))->toBeTrue()
        ->and($status->currentRunId($accountId))->toBe($ownerRunId);
});

it('only records a failure when the failing run owns the marker', function () {
    $status = app(GmailSyncStatus::class);
    $accountId = (string) Str::uuid();
    $ownerRunId = (string) Str::uuid();
    $otherRunId = (string) Str::uuid();

    $status->start($accountId, $ownerRunId);

    expect($status->markFailed($accountId, 'Old failure', $otherRunId))->toBeFalse()
        ->and($status->lastFailure($accountId))->toBeNull()
        ->and($status->isRunning($accountId))->toBeTrue()
        ->and($status->currentRunId($accountId))->toBe($ownerRunId);

    expect($status->markFailed($accountId, 'Current failure', $ownerRunId))->toBeTrue()
        ->and($status->lastFailure($accountId)['message'])->toBe('Current failure')
        ->and($status->isRunning($accountId))->toBeFalse();
});

it('reclaims a stale running marker after the stale window', function () {
    $status = app(GmailSyncStatus::class);
    $accountId = (string) Str::uuid();
    $oldRunId = (string) Str::uuid();
    $newRunId = (string) Str::uuid();

    $status->start($accountId, $oldRunId);

    $this->travel(31)->minutes();

    expect($status->isRunning($accountId))->toBeFalse()
        ->and($status->currentRunId($accountId))->toBeNull()
        ->and($status->start($accountId, $newRunId))->toBeTrue()
        ->and($status->currentRunId($accountId))->toBe($newRunId)
        ->and($status->isRunning($accountId))->toBeTrue();
});

it('adopts a legacy timestamp marker for a directly dispatched job', function () {
    $status = app(GmailSyncStatus::class);
    $accountId = (string) Str::uuid();

    Cache::store(config('communications.email.gmail.sync.cache_store'))->put(
        "gmail-sync:{$accountId}:running",
        now()->toIso8601String(),
        now()->addMinutes(60),
    );

    expect($status->runningSince($accountId))->not->toBeNull()
        ->and($status->currentRunId($accountId))->toBeNull();

    $adoptedRunId = $status->heartbeat($accountId);

    expect($adoptedRunId)->not->toBeNull()
        ->and($status->currentRunId($accountId))->toBe($adoptedRunId)
        ->and($status->isRunning($accountId))->toBeTrue();
});

it('clears running status and records last_synced_at after the final page', function () {
    $account = gmailSyncStatusTestAccount();
    $status = app(GmailSyncStatus::class);
    $runId = (string) Str::uuid();

    $status->start($account->id, $runId);
    $status->markFailed($account->id, 'Previous failure', $runId);
    $status->start($account->id, $runId);

    $service = Mockery::mock(GmailMessageSyncService::class);
    $service->shouldReceive('captureCheckpoint')
        ->zeroOrMoreTimes()
        ->andReturn('history-1');
    $service->shouldReceive('currentHistoryId')
        ->zeroOrMoreTimes()
        ->andReturn('baseline-1');
    $service->shouldReceive('syncPage')
        ->once()
        ->andReturn([
            'processed' => 3,
            'next_page_token' => null,
        ]);

    Queue::fake();

    (new SyncGmailMessagesJob($account->id, null, 1, $runId))
        ->handle($service, $status);

    Queue::assertPushedOn(
        'communications',
        SyncGmailMessagesJob::class,
        fn (SyncGmailMessagesJob $job): bool => $job->runId === $runId
            && $job->mode === SyncGmailMessagesJob::MODE_INCREMENTAL
            && $job->historyId === 'baseline-1',
    );

    expect($account->fresh()->last_synced_at)->toBeNull()
        ->and($status->isRunning($account->id))->toBeTrue()
        ->and($status->lastFailure($account->id)['message'])->toBe('Previous failure');
});

it('keeps the running status while another page is queued', function () {
    $account = gmailSyncStatusTestAccount();
    $status = app(GmailSyncStatus::class);
    $runId = (string) Str::uuid();

    $status->start($account->id, $runId);

    $service = Mockery::mock(GmailMessageSyncService::class);
    $service->shouldReceive('captureCheckpoint')
        ->zeroOrMoreTimes()
        ->andReturn('history-1');
    $service->shouldReceive('currentHistoryId')
        ->zeroOrMoreTimes()
        ->andReturn('baseline-1');
    $service->shouldReceive('syncPage')
        ->once()
        ->andReturn([
            'processed' => 50,
            'next_page_token' => 'page-2',
        ]);

    Queue::fake();

    (new SyncGmailMessagesJob($account->id, null, 1, $runId))
        ->handle($service, $status);

    expect($status->isRunning($account->id))->toBeTrue()
        ->and($status->currentRunId($account->id))->toBe($runId)
        ->and($status->lastFailure($account->id))->toBeNull()
        ->and($account->fresh()->last_synced_at)->toBeNull();

    Queue::assertPushedOn('communications', SyncGmailMessagesJob::class);
    Queue::assertPushed(
        SyncGmailMessagesJob::class,
        fn (SyncGmailMessagesJob $job): bool => $job->runId === $runId,
    );
});

it('records a failure when the job exhausts its retries', function () {
    $account = gmailSyncStatusTestAccount();
    $status = app(GmailSyncStatus::class);
    $runId = (string) Str::uuid();

    $status->start($account->id, $runId);

    $job = new SyncGmailMessagesJob($account->id, null, 2, $runId);
    $job->failed(new RuntimeException('Gmail API unavailable'));

    $failure = $status->lastFailure($account->id);

    expect($status->isRunning($account->id))->toBeFalse()
        ->and($failure)->not->toBeNull()
        ->and($failure['message'])->toBe('Gmail API unavailable');
});

it('records a stale job failure without overwriting the newer run', function () {
    $account = gmailSyncStatusTestAccount();
    $status = app(GmailSyncStatus::class);
    $oldRunId = (string) Str::uuid();
    $newRunId = (string) Str::uuid();

    $status->start($account->id, $oldRunId);

    $this->travel(31)->minutes();

    $status->start($account->id, $newRunId);

    (new SyncGmailMessagesJob($account->id, null, 2, $oldRunId))
        ->failed(new RuntimeException('Stale failure'));

    expect($status->isRunning($account->id))->toBeTrue()
        ->and($status->currentRunId($account->id))->toBe($newRunId)
        ->and($status->lastFailure($account->id))->toBeNull();
});

it('stops retrying an authentication failure that requires reconnection', function () {
    $account = gmailSyncStatusTestAccount();
    $status = app(GmailSyncStatus::class);

    $service = Mockery::mock(GmailMessageSyncService::class);
    $service->shouldReceive('captureCheckpoint')
        ->zeroOrMoreTimes()
        ->andReturn('history-1');
    $service->shouldReceive('currentHistoryId')
        ->zeroOrMoreTimes()
        ->andReturn('baseline-1');
    $service->shouldReceive('syncPage')
        ->once()
        ->andThrow(new GmailAuthenticationException(
            'Refresh token Gmail sudah tidak berlaku.',
            true,
        ));

    (new SyncGmailMessagesJob($account->id))->handle($service, $status);

    $failure = $status->lastFailure($account->id);

    expect($status->isRunning($account->id))->toBeFalse()
        ->and($failure)->not->toBeNull()
        ->and($failure['message'])->toBe('Refresh token Gmail sudah tidak berlaku.');
});

it('rethrows transient authentication failures so the queue can retry', function () {
    $account = gmailSyncStatusTestAccount();
    $status = app(GmailSyncStatus::class);

    $service = Mockery::mock(GmailMessageSyncService::class);
    $service->shouldReceive('captureCheckpoint')
        ->zeroOrMoreTimes()
        ->andReturn('history-1');
    $service->shouldReceive('currentHistoryId')
        ->zeroOrMoreTimes()
        ->andReturn('baseline-1');
    $service->shouldReceive('syncPage')
        ->once()
        ->andThrow(new GmailAuthenticationException(
            'Layanan autentikasi Google sementara tidak tersedia.',
            false,
        ));

    $job = new SyncGmailMessagesJob($account->id);

    expect(fn () => $job->handle($service, $status))
        ->toThrow(GmailAuthenticationException::class);

    expect($status->isRunning($account->id))->toBeTrue()
        ->and($status->lastFailure($account->id))->toBeNull();
});

it('rethrows Gmail rate limit errors with exponential backoff', function () {
    $account = gmailSyncStatusTestAccount();
    $status = app(GmailSyncStatus::class);

    $exception = new GoogleServiceException(
        'Rate limit exceeded',
        429,
        null,
        [['reason' => 'rateLimitExceeded']],
    );

    $service = Mockery::mock(GmailMessageSyncService::class);
    $service->shouldReceive('captureCheckpoint')
        ->zeroOrMoreTimes()
        ->andReturn('history-1');
    $service->shouldReceive('currentHistoryId')
        ->zeroOrMoreTimes()
        ->andReturn('baseline-1');
    $service->shouldReceive('syncPage')
        ->once()
        ->andThrow($exception);

    $job = new SyncGmailMessagesJob($account->id);

    expect(fn () => $job->handle($service, $status))
        ->toThrow(GoogleServiceException::class);

    expect($job->backoff())->toBe([60, 300, 900])
        ->and($job->tries)->toBe(3)
        ->and($job->timeout)->toBe(120)
        ->and($job->queue)->toBe('communications')
        ->and($status->isRunning($account->id))->toBeTrue();
});

it('records a failure without retrying a permanent Gmail API error', function () {
    $account = gmailSyncStatusTestAccount();
    $status = app(GmailSyncStatus::class);

    $service = Mockery::mock(GmailMessageSyncService::class);
    $service->shouldReceive('captureCheckpoint')
        ->zeroOrMoreTimes()
        ->andReturn('history-1');
    $service->shouldReceive('currentHistoryId')
        ->zeroOrMoreTimes()
        ->andReturn('baseline-1');
    $service->shouldReceive('syncPage')
        ->once()
        ->andThrow(new GmailApiException(
            'Gmail menolak permintaan sinkronisasi (HTTP 403).',
            retryable: false,
            httpStatus: 403,
        ));

    $job = new SyncGmailMessagesJob($account->id);

    expect(fn () => $job->handle($service, $status))->not->toThrow(Throwable::class);

    expect($status->isRunning($account->id))->toBeFalse()
        ->and($status->lastFailure($account->id)['message'])
        ->toBe('Gmail menolak permintaan sinkronisasi (HTTP 403).');
});

it('rethrows Gmail server errors so the queue can retry with backoff', function () {
    $account = gmailSyncStatusTestAccount();
    $status = app(GmailSyncStatus::class);

    $exception = new GoogleServiceException(
        'Backend Error',
        503,
        null,
        [['reason' => 'backendError']],
    );

    $service = Mockery::mock(GmailMessageSyncService::class);
    $service->shouldReceive('captureCheckpoint')
        ->zeroOrMoreTimes()
        ->andReturn('history-1');
    $service->shouldReceive('currentHistoryId')
        ->zeroOrMoreTimes()
        ->andReturn('baseline-1');
    $service->shouldReceive('syncPage')
        ->once()
        ->andThrow($exception);

    $job = new SyncGmailMessagesJob($account->id);

    expect(fn () => $job->handle($service, $status))
        ->toThrow(GoogleServiceException::class);

    expect($job->backoff())->toBe([60, 300, 900])
        ->and($status->isRunning($account->id))->toBeTrue()
        ->and($status->lastFailure($account->id))->toBeNull();
});

it('records a failure when the Gmail account is missing', function () {
    $status = app(GmailSyncStatus::class);
    $accountId = '00000000-0000-4000-8000-000000000001';

    $service = Mockery::mock(GmailMessageSyncService::class);
    $service->shouldReceive('captureCheckpoint')
        ->zeroOrMoreTimes()
        ->andReturn('history-1');
    $service->shouldReceive('currentHistoryId')
        ->zeroOrMoreTimes()
        ->andReturn('baseline-1');
    $service->shouldNotReceive('syncPage');

    (new SyncGmailMessagesJob($accountId))->handle($service, $status);

    expect($status->isRunning($accountId))->toBeFalse()
        ->and($status->lastFailure($accountId))->not->toBeNull();
});
