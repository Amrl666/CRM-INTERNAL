<?php

use App\Domain\Communications\Models\CommunicationAccount;
use App\Domain\Communications\Services\GmailMessageSyncService;
use App\Domain\Communications\Services\GmailSyncStatus;
use App\Jobs\Middleware\WithoutOverlappingOnStore;
use App\Jobs\SyncGmailMessagesJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function gmailJobTestAccount(string $email = 'hello@example.test'): CommunicationAccount
{
    return CommunicationAccount::create([
        'provider' => 'gmail',
        'account_email' => $email,
        'display_name' => 'Test Gmail',
        'access_token' => 'test-access-token',
        'refresh_token' => 'test-refresh-token',
        'token_expires_at' => now()->addHour(),
        'connection_status' => 'connected',
    ]);
}

it('dispatches the next page and does not update last_synced_at prematurely', function () {
    $account = gmailJobTestAccount();

    Queue::fake();

    $service = Mockery::mock(GmailMessageSyncService::class);
    $service->shouldReceive('captureCheckpoint')
        ->zeroOrMoreTimes()
        ->andReturn('history-1');
    $service->shouldReceive('currentHistoryId')
        ->zeroOrMoreTimes()
        ->andReturn('baseline-1');
    $service->shouldReceive('syncPage')
        ->once()
        ->withArgs(fn ($receivedAccount, $pageToken) => $receivedAccount->id === $account->id && $pageToken === null
        )
        ->andReturn([
            'processed' => 50,
            'next_page_token' => 'next-page-token',
        ]);

    app()->instance(GmailMessageSyncService::class, $service);

    (new SyncGmailMessagesJob($account->id))->handle($service);

    $runId = app(GmailSyncStatus::class)->currentRunId($account->id);

    expect($runId)->not->toBeNull()
        ->and($account->fresh()->last_synced_at)->toBeNull();

    Queue::assertPushedOn('communications', SyncGmailMessagesJob::class);

    Queue::assertPushed(
        SyncGmailMessagesJob::class,
        fn (SyncGmailMessagesJob $job) => $job->accountId === $account->id
            && $job->pageToken === 'next-page-token'
            && $job->runId === $runId
    );
});

it('does not complete a page when the run loses ownership during the sync', function () {
    $account = gmailJobTestAccount();
    $status = app(GmailSyncStatus::class);
    $oldRunId = (string) Str::uuid();
    $newRunId = (string) Str::uuid();

    $status->start($account->id, $oldRunId);

    $service = Mockery::mock(GmailMessageSyncService::class);
    $service->shouldReceive('captureCheckpoint')
        ->zeroOrMoreTimes()
        ->andReturn('history-1');
    $service->shouldReceive('currentHistoryId')
        ->zeroOrMoreTimes()
        ->andReturn('baseline-1');
    $service->shouldReceive('syncPage')
        ->once()
        ->andReturnUsing(function () use ($status, $account, $oldRunId, $newRunId) {
            $status->markFailed($account->id, 'Old run lost ownership.', $oldRunId);
            $status->start($account->id, $newRunId);

            return [
                'processed' => 10,
                'next_page_token' => null,
            ];
        });

    Queue::fake();

    (new SyncGmailMessagesJob($account->id, null, 1, $oldRunId))
        ->handle($service, $status);

    Queue::assertPushedOn(
        'communications',
        SyncGmailMessagesJob::class,
        fn (SyncGmailMessagesJob $job): bool => $job->runId === $oldRunId
            && $job->mode === SyncGmailMessagesJob::MODE_INCREMENTAL,
    );

    expect($account->fresh()->last_synced_at)->toBeNull()
        ->and($status->currentRunId($account->id))->toBe($newRunId)
        ->and($status->isRunning($account->id))->toBeTrue()
        ->and($status->lastFailure($account->id)['message'])
        ->toBe('Old run lost ownership.');
});

it('does not let an old run mutate a newer run after stale recovery', function () {
    $account = gmailJobTestAccount();
    $status = app(GmailSyncStatus::class);
    $oldRunId = (string) Str::uuid();
    $newRunId = (string) Str::uuid();

    $status->start($account->id, $oldRunId);

    $this->travel(31)->minutes();

    expect($status->start($account->id, $newRunId))->toBeTrue();

    $service = Mockery::mock(GmailMessageSyncService::class);
    $service->shouldReceive('captureCheckpoint')
        ->zeroOrMoreTimes()
        ->andReturn('history-1');
    $service->shouldReceive('currentHistoryId')
        ->zeroOrMoreTimes()
        ->andReturn('baseline-1');
    $service->shouldNotReceive('syncPage');

    Queue::fake();

    (new SyncGmailMessagesJob(
        $account->id,
        'old-page-token',
        2,
        $oldRunId,
    ))->handle($service, $status);

    Queue::assertNothingPushed();

    expect($status->currentRunId($account->id))->toBe($newRunId)
        ->and($status->isRunning($account->id))->toBeTrue()
        ->and($account->fresh()->last_synced_at)->toBeNull();
});

it('queues incremental catch-up after the final full page completes', function () {
    $account = gmailJobTestAccount();
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
        ->withArgs(fn ($receivedAccount, $pageToken) => $receivedAccount->id === $account->id
            && $pageToken === 'next-page-token'
        )
        ->andReturn([
            'processed' => 12,
            'next_page_token' => null,
        ]);

    Queue::fake();

    (new SyncGmailMessagesJob($account->id, 'next-page-token', 2, $runId))
        ->handle($service, $status);

    Queue::assertPushedOn(
        'communications',
        SyncGmailMessagesJob::class,
        fn (SyncGmailMessagesJob $job): bool => $job->runId === $runId
            && $job->mode === SyncGmailMessagesJob::MODE_INCREMENTAL
            && $job->historyId === 'baseline-1',
    );

    expect($account->fresh()->last_synced_at)->toBeNull()
        ->and($status->isRunning($account->id))->toBeTrue();
});

it('skips disconnected accounts without calling Gmail sync service', function () {
    $account = gmailJobTestAccount();

    $account->update([
        'connection_status' => 'disconnected',
    ]);

    $service = Mockery::mock(GmailMessageSyncService::class);
    $service->shouldReceive('captureCheckpoint')
        ->zeroOrMoreTimes()
        ->andReturn('history-1');
    $service->shouldReceive('currentHistoryId')
        ->zeroOrMoreTimes()
        ->andReturn('baseline-1');
    $service->shouldNotReceive('syncPage');

    Queue::fake();

    (new SyncGmailMessagesJob($account->id))->handle($service);

    Queue::assertNothingPushed();

    expect($account->fresh()->last_synced_at)->toBeNull();
});

it('skips missing accounts without calling Gmail sync service', function () {
    $service = Mockery::mock(GmailMessageSyncService::class);
    $service->shouldReceive('captureCheckpoint')
        ->zeroOrMoreTimes()
        ->andReturn('history-1');
    $service->shouldReceive('currentHistoryId')
        ->zeroOrMoreTimes()
        ->andReturn('baseline-1');
    $service->shouldNotReceive('syncPage');

    Queue::fake();

    (new SyncGmailMessagesJob('00000000-0000-4000-8000-000000000001'))
        ->handle($service);

    Queue::assertNothingPushed();
});

it('generates the same unique id for the same account and page token', function () {
    $first = new SyncGmailMessagesJob('account-123', 'page-token-1');
    $second = new SyncGmailMessagesJob('account-123', 'page-token-1');

    expect($first->uniqueId())->toBe($second->uniqueId());
});

it('generates different unique ids for different accounts or page tokens', function () {
    $first = new SyncGmailMessagesJob('account-123', 'page-token-1');
    $differentAccount = new SyncGmailMessagesJob('account-456', 'page-token-1');
    $differentPage = new SyncGmailMessagesJob('account-123', 'page-token-2');

    expect($first->uniqueId())->not->toBe($differentAccount->uniqueId())
        ->and($first->uniqueId())->not->toBe($differentPage->uniqueId());
});

it('uses an account-level overlap lock', function () {
    $job = new SyncGmailMessagesJob('account-123');

    $middleware = $job->middleware();

    expect($middleware)->toHaveCount(1)
        ->and($middleware[0])->toBeInstanceOf(
            WithoutOverlapping::class
        )
        ->and($middleware[0])->toBeInstanceOf(WithoutOverlappingOnStore::class)
        ->and($middleware[0]->store)->toBeNull()
        ->and($middleware[0]->key)->toBe('gmail-sync:account:account-123')
        ->and($middleware[0]->releaseAfter)->toBe(30)
        ->and($middleware[0]->expiresAfter)->toBe(300);
});

it('stops pagination at page 100 without marking synchronization complete', function () {
    $account = gmailJobTestAccount();

    $service = Mockery::mock(GmailMessageSyncService::class);
    $service->shouldReceive('captureCheckpoint')
        ->zeroOrMoreTimes()
        ->andReturn('history-1');
    $service->shouldReceive('currentHistoryId')
        ->zeroOrMoreTimes()
        ->andReturn('baseline-1');
    $service->shouldReceive('syncPage')
        ->once()
        ->withArgs(fn ($receivedAccount, $pageToken) => $receivedAccount->id === $account->id
            && $pageToken === 'page-100-token'
        )
        ->andReturn([
            'processed' => 50,
            'next_page_token' => 'page-101-token',
        ]);

    Queue::fake();

    (new SyncGmailMessagesJob(
        $account->id,
        'page-100-token',
        100,
    ))->handle($service);

    Queue::assertNothingPushed();

    expect($account->fresh()->last_synced_at)->toBeNull();
});
