<?php

use App\Domain\Communications\Models\CommunicationAccount;
use App\Domain\Communications\Models\Conversation;
use App\Domain\Communications\Services\GmailSyncStatus;
use App\Jobs\SyncGmailMessagesJob;
use App\Livewire\Inbox\Gmail;
use App\Livewire\Inbox\Index;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(RoleAndPermissionSeeder::class)->run();

    config([
        'communications.email.gmail.primary_email' => 'enigmativebusiness@gmail.com',
        'communications.email.gmail.sync.enabled' => false,
        'communications.email.gmail.sync.interval_minutes' => 15,
    ]);
});

function gmailSyncInboxTestUser(string $role = 'admin'): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

function gmailSyncInboxTestAccount(
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

it('dispatches a manual synchronization to the communications queue', function () {
    Queue::fake();

    $user = gmailSyncInboxTestUser();
    $account = gmailSyncInboxTestAccount();

    $this->actingAs($user);

    Livewire::test(Gmail::class)
        ->assertSee('Sync Now')
        ->call('syncNow')
        ->assertSet('syncFeedback', 'Sinkronisasi Gmail dimulai.')
        ->assertSet('syncFeedbackType', 'success')
        ->assertSee('Sedang berjalan...')
        ->assertSeeHtml('data-state="running"');

    $runId = app(GmailSyncStatus::class)->currentRunId($account->id);

    Queue::assertPushedOn(
        'communications',
        SyncGmailMessagesJob::class,
        fn (SyncGmailMessagesJob $job): bool => $job->accountId === $account->id
            && $job->runId === $runId,
    );

    expect($runId)->not->toBeNull()
        ->and(app(GmailSyncStatus::class)->isRunning($account->id))->toBeTrue();
});

it('forbids manual synchronization for a user without inbox.view', function () {
    Queue::fake();

    gmailSyncInboxTestAccount();

    $this->actingAs(User::factory()->create());

    Livewire::test(Gmail::class)->assertForbidden();

    Queue::assertNotPushed(SyncGmailMessagesJob::class);
});

it('does not fall back to a secondary Gmail account when the primary is missing', function () {
    Queue::fake();

    gmailSyncInboxTestAccount('secondary@example.test');

    $this->actingAs(gmailSyncInboxTestUser());

    Livewire::test(Gmail::class)
        ->call('syncNow')
        ->assertSet('syncFeedback', 'Akun Gmail utama belum terhubung.')
        ->assertSet('syncFeedbackType', 'error')
        ->assertSee('Akun Gmail utama belum terhubung.');

    Queue::assertNotPushed(SyncGmailMessagesJob::class);
});

it('does not dispatch when the primary Gmail account is disconnected', function () {
    Queue::fake();

    $account = gmailSyncInboxTestAccount(
        'enigmativebusiness@gmail.com',
        'disconnected',
    );

    $this->actingAs(gmailSyncInboxTestUser());

    Livewire::test(Gmail::class)
        ->call('syncNow')
        ->assertSet('syncFeedback', 'Akun Gmail utama belum terhubung.')
        ->assertSet('syncFeedbackType', 'error');

    Queue::assertNotPushed(SyncGmailMessagesJob::class);

    expect(app(GmailSyncStatus::class)->isRunning($account->id))->toBeFalse();
});

it('prevents duplicate dispatch while a synchronization is already running', function () {
    Queue::fake();

    $user = gmailSyncInboxTestUser();
    gmailSyncInboxTestAccount();

    $this->actingAs($user);

    Livewire::test(Gmail::class)->call('syncNow');

    Livewire::test(Gmail::class)
        ->call('syncNow')
        ->assertSet('syncFeedback', 'Sinkronisasi Gmail sedang berjalan atau sudah menunggu di antrean.')
        ->assertSet('syncFeedbackType', 'info');

    Queue::assertPushed(SyncGmailMessagesJob::class, 1);
});

it('shows the last synchronized time and the last failure', function () {
    $user = gmailSyncInboxTestUser();
    $account = gmailSyncInboxTestAccount();
    $syncedAt = now()->subHour()->startOfMinute();

    $account->update(['last_synced_at' => $syncedAt]);

    $status = app(GmailSyncStatus::class);
    $runId = (string) Str::uuid();

    $status->start($account->id, $runId);
    $status->markFailed(
        $account->id,
        'Gmail API rate limit exceeded.',
        $runId,
    );

    $this->actingAs($user);

    Livewire::test(Gmail::class)
        ->assertSee('Terakhir:')
        ->assertSee($syncedAt->format('d/m/Y H:i'))
        ->assertSee('Gmail API rate limit exceeded.')
        ->assertSeeHtml('data-state="idle"')
        ->assertSeeHtml('aria-disabled="false"');
});

it('disables the sync button while a synchronization is running', function () {
    $user = gmailSyncInboxTestUser();
    $account = gmailSyncInboxTestAccount();

    app(GmailSyncStatus::class)->markRunning($account->id);

    $this->actingAs($user);

    Livewire::test(Gmail::class)
        ->assertSee('Sedang berjalan...')
        ->assertSeeHtml('data-state="running"')
        ->assertSeeHtml('aria-disabled="true"');
});

it('does not expose Gmail access or refresh tokens in the inbox', function () {
    $user = gmailSyncInboxTestUser();
    gmailSyncInboxTestAccount();

    $this->actingAs($user);

    Livewire::test(Gmail::class)
        ->assertDontSee('test-access-token')
        ->assertDontSee('test-refresh-token');
});

it('keeps the WhatsApp inbox unaffected by Gmail synchronization status', function () {
    $user = gmailSyncInboxTestUser();
    $account = gmailSyncInboxTestAccount();

    app(GmailSyncStatus::class)->markRunning($account->id);

    Conversation::create([
        'provider' => 'evolution',
        'instance' => 'default',
        'remote_jid' => '628123@s.whatsapp.net',
        'last_message_at' => now(),
        'last_message_preview' => 'Pesan WhatsApp tetap tampil',
    ]);

    $this->actingAs($user);

    Livewire::test(Index::class)
        ->assertSee('Pesan WhatsApp tetap tampil');
});
