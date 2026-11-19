<?php

use App\Domain\Communications\Actions\DispatchGmailSyncAction;
use App\Domain\Communications\DTOs\GmailSyncDispatchResult;
use App\Domain\Communications\Models\CommunicationAccount;
use App\Domain\Communications\Services\GmailSyncStatus;
use App\Jobs\SyncGmailMessagesJob;
use Illuminate\Bus\UniqueLock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Events\UniqueJobSkipped;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'communications.email.gmail.primary_email' => 'hello@example.test',
        'communications.email.gmail.sync.enabled' => false,
        'communications.email.gmail.sync.interval_minutes' => 15,
        'communications.email.gmail.sync.cache_store' => 'array',
    ]);
});

afterEach(function () {
    Cache::store('array')->flush();
    Cache::store('database')->flush();
});

function gmailDispatchReliabilityAccount(): CommunicationAccount
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

it('does not report dispatched when the unique lock is already held', function () {
    $account = gmailDispatchReliabilityAccount();

    Queue::fake();

    $probe = new SyncGmailMessagesJob($account->id);
    $lock = Cache::store('array')->lock(
        UniqueLock::getKey($probe),
        $probe->uniqueFor,
    );

    expect($lock->get())->toBeTrue();

    try {
        $result = app(DispatchGmailSyncAction::class)->handle();
    } finally {
        $lock->release();
    }

    expect($result->status)->toBe(GmailSyncDispatchResult::STATUS_ALREADY_RUNNING)
        ->and($result->isDispatched())->toBeFalse()
        ->and(app(GmailSyncStatus::class)->isRunning($account->id))->toBeFalse();

    Queue::assertNothingPushed();
});

it('fires UniqueJobSkipped and does not enqueue the duplicate job', function () {
    $account = gmailDispatchReliabilityAccount();

    Queue::fake();
    Event::fake([UniqueJobSkipped::class]);

    $job = new SyncGmailMessagesJob($account->id);
    $lock = Cache::store('array')->lock(
        UniqueLock::getKey($job),
        $job->uniqueFor,
    );

    expect($lock->get())->toBeTrue();

    try {
        $pending = dispatch($job);
        unset($pending);
    } finally {
        $lock->release();
    }

    Event::assertDispatched(
        UniqueJobSkipped::class,
        fn (UniqueJobSkipped $event): bool => $event->job === $job,
    );

    Queue::assertNothingPushed();
});

it('keeps the running marker when a skipped job is a continuation page', function () {
    $account = gmailDispatchReliabilityAccount();
    $status = app(GmailSyncStatus::class);
    $runId = (string) Str::uuid();

    $status->start($account->id, $runId);

    Queue::fake();

    $job = new SyncGmailMessagesJob(
        $account->id,
        'page-2',
        2,
        $runId,
        mode: SyncGmailMessagesJob::MODE_INCREMENTAL,
        historyId: '100',
    );

    $lock = Cache::store('array')->lock(
        UniqueLock::getKey($job),
        $job->uniqueFor,
    );

    expect($lock->get())->toBeTrue();

    try {
        $pending = dispatch($job);
        unset($pending);
    } finally {
        $lock->release();
    }

    expect($status->isRunning($account->id))->toBeTrue()
        ->and($status->currentRunId($account->id))->toBe($runId);

    Queue::assertNothingPushed();
});

it('reports dispatched only after the job reaches the queue', function () {
    $account = gmailDispatchReliabilityAccount();

    Queue::fake();

    $result = app(DispatchGmailSyncAction::class)->handle();

    expect($result->isDispatched())->toBeTrue();

    Queue::assertPushedOn(
        'communications',
        SyncGmailMessagesJob::class,
        fn (SyncGmailMessagesJob $job): bool => $job->accountId === $account->id
            && filled($job->uniqueLockOwner),
    );
});

it('releases only the running marker owned by the skipped run', function () {
    $status = app(GmailSyncStatus::class);
    $accountId = (string) Str::uuid();
    $ownerRunId = (string) Str::uuid();
    $otherRunId = (string) Str::uuid();

    $status->start($accountId, $ownerRunId);

    expect($status->release($accountId, $otherRunId))->toBeFalse()
        ->and($status->release($accountId, null))->toBeFalse()
        ->and($status->currentRunId($accountId))->toBe($ownerRunId)
        ->and($status->isRunning($accountId))->toBeTrue()
        ->and($status->release($accountId, $ownerRunId))->toBeTrue()
        ->and($status->isRunning($accountId))->toBeFalse();
});

it('treats scheduler and manual dispatch consistently on a unique-lock conflict', function () {
    $account = gmailDispatchReliabilityAccount();

    Queue::fake();

    $probe = new SyncGmailMessagesJob($account->id);
    $lock = Cache::store('array')->lock(
        UniqueLock::getKey($probe),
        $probe->uniqueFor,
    );

    expect($lock->get())->toBeTrue();

    try {
        $manual = app(DispatchGmailSyncAction::class)->handle();
        $scheduler = app(DispatchGmailSyncAction::class)->handle(
            respectInterval: true,
        );
    } finally {
        $lock->release();
    }

    expect($manual->status)->toBe(GmailSyncDispatchResult::STATUS_ALREADY_RUNNING)
        ->and($scheduler->status)->toBe(GmailSyncDispatchResult::STATUS_ALREADY_RUNNING)
        ->and($manual->isDispatched())->toBeFalse()
        ->and($scheduler->isDispatched())->toBeFalse();

    Queue::assertNothingPushed();
});

it('uses a Gmail cache store that differs from CACHE_STORE for all sync locks', function () {
    config([
        'cache.default' => 'array',
        'communications.email.gmail.sync.cache_store' => 'database',
    ]);

    $account = gmailDispatchReliabilityAccount();

    Queue::fake();

    // Running marker and state lock live on the Gmail store.
    $runId = (string) Str::uuid();
    $status = app(GmailSyncStatus::class);
    $markerKey = "gmail-sync:{$account->id}:running";

    $status->start($account->id, $runId);

    expect(Cache::store('database')->has($markerKey))->toBeTrue()
        ->and(Cache::store('array')->has($markerKey))->toBeFalse();

    $status->release($account->id, $runId);

    // ShouldBeUnique lock is acquired through uniqueVia() on the Gmail store.
    app(DispatchGmailSyncAction::class)->handle();

    $probe = new SyncGmailMessagesJob($account->id);
    $uniqueKey = UniqueLock::getKey($probe);

    $gmailLock = Cache::store('database')->lock(
        $uniqueKey,
        $probe->uniqueFor,
    );
    $defaultLock = Cache::store('array')->lock(
        $uniqueKey,
        $probe->uniqueFor,
    );

    expect($gmailLock->get())->toBeFalse()
        ->and($defaultLock->get())->toBeTrue();

    $defaultLock->release();

    // WithoutOverlapping lock uses the same Gmail store.
    $middleware = $probe->middleware()[0];
    $overlapKey = $middleware->getLockKey($probe);

    expect($middleware)->toBeInstanceOf(WithoutOverlapping::class)
        ->and($middleware->store)->toBe('database');

    $overlapLock = Cache::store('database')->lock(
        $overlapKey,
        $middleware->expiresAfter,
    );

    expect($overlapLock->get())->toBeTrue();

    $invoked = false;
    $probe->withFakeQueueInteractions();

    try {
        $middleware->handle($probe, function () use (&$invoked): void {
            $invoked = true;
        });
    } finally {
        $overlapLock->release();
    }

    expect($invoked)->toBeFalse();

    $probe->assertReleased(30);

    $defaultOverlapLock = Cache::store('array')->lock(
        $overlapKey,
        $middleware->expiresAfter,
    );

    expect($defaultOverlapLock->get())->toBeTrue();

    $defaultOverlapLock->release();
});
