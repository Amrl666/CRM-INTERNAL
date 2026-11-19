<?php

use App\Domain\Communications\Actions\DispatchGmailSyncAction;
use App\Domain\Communications\DTOs\GmailSyncDispatchResult;
use App\Domain\Communications\Models\CommunicationAccount;
use App\Domain\Communications\Services\GmailSyncStatus;
use App\Jobs\SyncGmailMessagesJob;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'communications.email.gmail.primary_email' => 'enigmativebusiness@gmail.com',
        'communications.email.gmail.sync.enabled' => false,
        'communications.email.gmail.sync.interval_minutes' => 15,
    ]);
});

function gmailSchedulerTestAccount(
    string $email = 'enigmativebusiness@gmail.com',
    string $status = 'connected',
): CommunicationAccount {
    return CommunicationAccount::create([
        'provider' => 'gmail',
        'account_email' => $email,
        'display_name' => 'Enigma Collective',
        'access_token' => 'test-access-token',
        'refresh_token' => 'test-refresh-token',
        'token_expires_at' => now()->addHour(),
        'connection_status' => $status,
    ]);
}

it('registers the Gmail scheduler but leaves it disabled by default', function () {
    Queue::fake();

    gmailSchedulerTestAccount();

    $this->artisan('schedule:run')->assertSuccessful();

    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event): bool => $event->description === 'gmail_inbox_sync');

    expect($event)->not->toBeNull();

    Queue::assertNotPushed(SyncGmailMessagesJob::class);
});

it('dispatches the primary account synchronization when enabled', function () {
    config()->set('communications.email.gmail.sync.enabled', true);

    Queue::fake();

    $account = gmailSchedulerTestAccount();

    $this->artisan('schedule:run')->assertSuccessful();

    Queue::assertPushedOn(
        'communications',
        SyncGmailMessagesJob::class,
        fn (SyncGmailMessagesJob $job): bool => $job->accountId === $account->id
            && $job->runId !== null
            && app(GmailSyncStatus::class)->currentRunId($account->id) === $job->runId,
    );
});

it('does not dispatch again before the configured interval elapses', function () {
    config()->set('communications.email.gmail.sync.enabled', true);

    Queue::fake();

    $account = gmailSchedulerTestAccount();

    $this->artisan('schedule:run')->assertSuccessful();

    $status = app(GmailSyncStatus::class);
    $runId = $status->currentRunId($account->id);

    expect($runId)->not->toBeNull()
        ->and($status->markCompleted($account->id, $runId))->toBeTrue();

    $result = app(DispatchGmailSyncAction::class)->handle(
        respectInterval: true,
    );

    expect($result->status)->toBe(GmailSyncDispatchResult::STATUS_THROTTLED);

    Queue::assertPushed(SyncGmailMessagesJob::class, 1);
});

it('does not use a secondary Gmail account when the primary is unavailable', function () {
    config()->set('communications.email.gmail.sync.enabled', true);

    Queue::fake();

    gmailSchedulerTestAccount('secondary@example.test');

    $this->artisan('schedule:run')->assertSuccessful();

    Queue::assertNotPushed(SyncGmailMessagesJob::class);
});
