<?php

use App\Domain\Communications\Models\CommunicationAccount;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Google\Client as GoogleClient;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);

    config([
        'communications.email.gmail.client_id' => 'test-client-id',
        'communications.email.gmail.client_secret' => 'test-client-secret',
        'communications.email.gmail.redirect_uri' => 'https://example.test/settings/gmail/callback',
        'communications.email.gmail.primary_email' => 'hello@example.test',
    ]);
});

function gmailOAuthControllerAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole('admin');

    return $user;
}

function gmailOAuthControllerGoogleResponse(int $status, array $body): Response
{
    return new Response(
        $status,
        ['Content-Type' => 'application/json'],
        json_encode($body, JSON_THROW_ON_ERROR),
    );
}

function gmailOAuthControllerMockGoogleClient(array $responses): GoogleClient
{
    $client = new GoogleClient;

    $client->setHttpClient(new HttpClient([
        'handler' => HandlerStack::create(
            new MockHandler($responses),
        ),
    ]));

    app()->instance(GoogleClient::class, $client);

    return $client;
}

it('redirects unauthenticated users to login', function () {
    $this->get(route('settings.gmail.connect'))
        ->assertRedirect(route('login'));

    $this->get(route('settings.gmail.callback'))
        ->assertRedirect(route('login'));
});

it('forbids users without settings permission', function () {
    $user = User::factory()->create();
    $user->assignRole('sales_agent');

    $this->actingAs($user)
        ->get(route('settings.gmail.connect'))
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('settings.gmail.callback'))
        ->assertForbidden();
});

it('rejects callback with invalid OAuth state', function () {
    $admin = gmailOAuthControllerAdmin();

    $this->actingAs($admin)
        ->withSession(['gmail_oauth_state' => 'expected-state'])
        ->get(route('settings.gmail.callback', [
            'state' => 'incorrect-state',
            'code' => 'test-code',
        ]))
        ->assertRedirect(route('settings.index'))
        ->assertSessionHasErrors('gmail');

    expect(CommunicationAccount::query()->count())->toBe(0);
});

it('rejects callback when OAuth state is missing', function () {
    $admin = gmailOAuthControllerAdmin();

    $this->actingAs($admin)
        ->get(route('settings.gmail.callback', [
            'code' => 'test-code',
        ]))
        ->assertRedirect(route('settings.index'))
        ->assertSessionHasErrors('gmail');

    expect(CommunicationAccount::query()->count())->toBe(0);
});

it('rejects callback when authorization code is missing', function () {
    $admin = gmailOAuthControllerAdmin();

    $this->actingAs($admin)
        ->withSession(['gmail_oauth_state' => 'valid-state'])
        ->get(route('settings.gmail.callback', [
            'state' => 'valid-state',
        ]))
        ->assertRedirect(route('settings.index'))
        ->assertSessionHasErrors('gmail');

    expect(CommunicationAccount::query()->count())->toBe(0);
});

it('handles Google authorization cancellation without exchanging a token', function () {
    $admin = gmailOAuthControllerAdmin();

    $this->actingAs($admin)
        ->withSession(['gmail_oauth_state' => 'valid-state'])
        ->get(route('settings.gmail.callback', [
            'state' => 'valid-state',
            'error' => 'access_denied',
        ]))
        ->assertRedirect(route('settings.index'))
        ->assertSessionHasErrors('gmail');

    expect(CommunicationAccount::query()->count())->toBe(0);
});

it('redirects to settings after successful Gmail authorization', function () {
    $admin = gmailOAuthControllerAdmin();

    gmailOAuthControllerMockGoogleClient([
        gmailOAuthControllerGoogleResponse(200, [
            'access_token' => 'test-access-token',
            'refresh_token' => 'test-refresh-token',
            'expires_in' => 3600,
            'scope' => 'https://www.googleapis.com/auth/gmail.readonly https://www.googleapis.com/auth/gmail.send openid email profile',
            'token_type' => 'Bearer',
        ]),
        gmailOAuthControllerGoogleResponse(200, [
            'email' => 'hello@example.test',
            'name' => 'Prospectra Gmail',
            'verified_email' => true,
        ]),
    ]);

    $this->actingAs($admin)
        ->withSession(['gmail_oauth_state' => 'valid-state'])
        ->get(route('settings.gmail.callback', [
            'state' => 'valid-state',
            'code' => 'test-authorization-code',
        ]))
        ->assertRedirect(route('settings.index'))
        ->assertSessionHas(
            'status',
            'Gmail terhubung: hello@example.test',
        );

    $account = CommunicationAccount::query()
        ->where('provider', 'gmail')
        ->where('account_email', 'hello@example.test')
        ->first();

    expect($account)->not->toBeNull()
        ->and($account->connection_status)->toBe('connected')
        ->and($account->refresh_token)->toBe('test-refresh-token')
        ->and(CommunicationAccount::query()->count())->toBe(1);
});

it('does not connect the account when required Gmail scopes are missing', function () {
    $admin = gmailOAuthControllerAdmin();

    gmailOAuthControllerMockGoogleClient([
        gmailOAuthControllerGoogleResponse(200, [
            'access_token' => 'test-access-token',
            'refresh_token' => 'test-refresh-token',
            'expires_in' => 3600,
            'scope' => 'openid email profile',
            'token_type' => 'Bearer',
        ]),
    ]);

    $this->actingAs($admin)
        ->withSession(['gmail_oauth_state' => 'valid-state'])
        ->get(route('settings.gmail.callback', [
            'state' => 'valid-state',
            'code' => 'test-authorization-code',
        ]))
        ->assertRedirect(route('settings.index'))
        ->assertSessionHasErrors('gmail');

    expect(CommunicationAccount::query()->count())->toBe(0);
});
