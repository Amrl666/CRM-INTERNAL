<?php

use App\Domain\Communications\Models\CommunicationAccount;
use App\Domain\Communications\Services\GmailSyncStatus;
use App\Livewire\Inbox\Gmail;
use App\Livewire\Settings\Index;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Google\Client as GoogleClient;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(RoleAndPermissionSeeder::class)->run();

    config([
        'communications.email.gmail.client_id' => 'test-client-id',
        'communications.email.gmail.client_secret' => 'test-client-secret',
        'communications.email.gmail.redirect_uri' => 'https://example.test/settings/gmail/callback',
        'communications.email.gmail.primary_email' => 'primary@example.test',
    ]);

    Cache::flush();
});

function gmailDisconnectAdmin(string $role = 'admin'): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

function gmailDisconnectAccount(array $overrides = []): CommunicationAccount
{
    return CommunicationAccount::create(array_merge([
        'provider' => 'gmail',
        'account_email' => 'primary@example.test',
        'display_name' => 'Primary Gmail',
        'access_token' => 'local-access-token',
        'refresh_token' => 'local-refresh-token',
        'token_expires_at' => now()->addHour(),
        'connection_status' => 'connected',
        'gmail_history_id' => 'history-42',
    ], $overrides));
}

function gmailDisconnectMockClient(int $status, ?array &$transactions = null): GoogleClient
{
    $stack = HandlerStack::create(new MockHandler([
        new Response($status, ['Content-Type' => 'application/json'], '{}'),
    ]));

    if ($transactions !== null) {
        $stack->push(Middleware::history($transactions));
    }

    $client = new GoogleClient;
    $client->setHttpClient(new HttpClient(['handler' => $stack]));

    app()->instance(GoogleClient::class, $client);

    return $client;
}

it('revokes the Gmail token, clears sync state, and keeps the history cursor', function () {
    $admin = gmailDisconnectAdmin();
    $account = gmailDisconnectAccount();

    $status = app(GmailSyncStatus::class);
    $status->start($account->id, 'run-1');
    $status->markFailed($account->id, 'Failure before disconnect.', 'run-1');
    $status->start($account->id, 'run-2');
    $status->markHistoryExpired($account->id, 'run-2', 'History expired.');

    $transactions = [];
    gmailDisconnectMockClient(200, $transactions);

    $this->actingAs($admin);

    Livewire::test(Index::class)
        ->assertSee('Putuskan')
        ->call('disconnectGmail', $account->id)
        ->assertSet('gmailFeedbackType', 'success')
        ->assertSee('telah diputuskan dari aplikasi');

    $fresh = $account->fresh();

    expect($fresh->connection_status)->toBe('disconnected')
        ->and($fresh->access_token)->toBeNull()
        ->and($fresh->refresh_token)->toBeNull()
        ->and($fresh->token_expires_at)->toBeNull()
        ->and($fresh->gmail_history_id)->toBe('history-42')
        ->and($status->isRunning($account->id))->toBeFalse()
        ->and($status->lastFailure($account->id))->toBeNull()
        ->and($status->historyExpired($account->id))->toBeNull();

    expect($transactions)->toHaveCount(1);
    $request = $transactions[0]['request'];

    expect($request->getMethod())->toBe('POST')
        ->and((string) $request->getUri())->toBe(GoogleClient::OAUTH2_REVOKE_URI)
        ->and((string) $request->getBody())->toContain('token=local-refresh-token');
});

it('keeps the local disconnect when Google revocation fails', function () {
    $admin = gmailDisconnectAdmin();
    $account = gmailDisconnectAccount();

    gmailDisconnectMockClient(400);

    $this->actingAs($admin);

    Livewire::test(Index::class)
        ->call('disconnectGmail', $account->id)
        ->assertSet('gmailFeedbackType', 'warning')
        ->assertSee('Pencabutan akses ke Google gagal');

    $fresh = $account->fresh();

    expect($fresh->connection_status)->toBe('disconnected')
        ->and($fresh->access_token)->toBeNull()
        ->and($fresh->refresh_token)->toBeNull()
        ->and($fresh->gmail_history_id)->toBe('history-42');
});

it('does not show or allow disconnecting other Gmail accounts', function () {
    $admin = gmailDisconnectAdmin();
    $primary = gmailDisconnectAccount();
    $secondary = gmailDisconnectAccount([
        'account_email' => 'secondary@example.test',
        'access_token' => 'secondary-access-token',
        'refresh_token' => 'secondary-refresh-token',
        'gmail_history_id' => null,
    ]);

    gmailDisconnectMockClient(200);

    $this->actingAs($admin);

    Livewire::test(Index::class)
        ->assertSee('primary@example.test')
        ->assertDontSee('secondary@example.test')
        ->call('disconnectGmail', $secondary->id)
        ->assertStatus(404);

    expect($primary->fresh()->connection_status)->toBe('connected')
        ->and($secondary->fresh()->connection_status)->toBe('connected')
        ->and($secondary->fresh()->refresh_token)->toBe('secondary-refresh-token');
});

it('forbids users without settings.manage from disconnecting Gmail', function () {
    $agent = gmailDisconnectAdmin('sales_agent');

    $account = gmailDisconnectAccount();
    gmailDisconnectMockClient(200);

    $this->actingAs($agent);

    Livewire::test(Index::class)->assertForbidden();

    expect($account->fresh()->connection_status)->toBe('connected');
});

it('shows the reconnect notice after the primary Gmail account is disconnected', function () {
    $admin = gmailDisconnectAdmin();
    $account = gmailDisconnectAccount();

    $this->actingAs($admin);

    Livewire::test(Gmail::class)
        ->assertDontSee('Koneksi Gmail terputus.');

    $account->update(['connection_status' => 'disconnected']);

    Livewire::test(Gmail::class)
        ->assertSee('Koneksi Gmail terputus.');
});
