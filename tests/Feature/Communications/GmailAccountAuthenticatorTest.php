<?php

use App\Domain\Communications\Models\CommunicationAccount;
use App\Domain\Communications\Services\GmailAccountAuthenticator;
use App\Domain\Communications\Services\GmailAuthenticationException;
use Google\Client as GoogleClient;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'communications.email.gmail.client_id' => 'test-client-id',
        'communications.email.gmail.client_secret' => 'test-client-secret',
        'communications.email.gmail.redirect_uri' => 'https://example.test/callback',
        'communications.email.gmail.primary_email' => 'hello@example.test',
    ]);
});

function gmailTestAccount(array $attributes = []): CommunicationAccount
{
    return CommunicationAccount::create(array_merge([
        'provider' => 'gmail',
        'account_email' => 'hello@example.test',
        'display_name' => 'Test Gmail',
        'access_token' => 'old-access-token',
        'refresh_token' => 'existing-refresh-token',
        'token_expires_at' => now()->addHour(),
        'connection_status' => 'connected',
    ], $attributes));
}

function gmailMockClient(): GoogleClient
{
    $client = Mockery::mock(GoogleClient::class);

    $client->shouldReceive('setClientId')->once();
    $client->shouldReceive('setClientSecret')->once();
    $client->shouldReceive('setRedirectUri')->once();
    $client->shouldReceive('setAccessToken')->once();

    return $client;
}

it('uses a valid access token without refreshing it', function () {
    $account = gmailTestAccount();
    $client = gmailMockClient();

    $client->shouldNotReceive('fetchAccessTokenWithRefreshToken');

    $authenticator = new GmailAccountAuthenticator($client);

    expect($authenticator->clientFor($account))->toBe($client)
        ->and($account->fresh()->connection_status)->toBe('connected');
});

it('refreshes an expired access token and preserves the existing refresh token', function () {
    $account = gmailTestAccount([
        'token_expires_at' => now()->subMinute(),
    ]);

    $client = gmailMockClient();

    $client->shouldReceive('setAccessToken')->once();

    $client->shouldReceive('fetchAccessTokenWithRefreshToken')
        ->once()
        ->with('existing-refresh-token')
        ->andReturn([
            'access_token' => 'new-access-token',
            'expires_in' => 3600,
        ]);

    $authenticator = new GmailAccountAuthenticator($client);
    $authenticator->clientFor($account);

    $account->refresh();

    expect($account->access_token)->toBe('new-access-token')
        ->and($account->refresh_token)->toBe('existing-refresh-token')
        ->and($account->connection_status)->toBe('connected')
        ->and($account->token_expires_at->isFuture())->toBeTrue();
});

it('disconnects an account when Google returns invalid_grant', function () {
    $account = gmailTestAccount([
        'token_expires_at' => now()->subMinute(),
    ]);

    $client = gmailMockClient();

    $client->shouldReceive('fetchAccessTokenWithRefreshToken')
        ->once()
        ->andReturn([
            'error' => 'invalid_grant',
        ]);

    $authenticator = new GmailAccountAuthenticator($client);

    try {
        $authenticator->clientFor($account);
        $this->fail('Expected GmailAuthenticationException was not thrown.');
    } catch (GmailAuthenticationException $e) {
        expect($e->shouldDisconnect)->toBeTrue();
    }

    expect($account->fresh()->connection_status)->toBe('disconnected');
});

it('keeps the account connected when token refresh fails temporarily', function () {
    $account = gmailTestAccount([
        'token_expires_at' => now()->subMinute(),
    ]);

    $client = gmailMockClient();

    $client->shouldReceive('fetchAccessTokenWithRefreshToken')
        ->once()
        ->andThrow(new RuntimeException('Temporary network failure'));

    $authenticator = new GmailAccountAuthenticator($client);

    try {
        $authenticator->clientFor($account);
        $this->fail('Expected GmailAuthenticationException was not thrown.');
    } catch (GmailAuthenticationException $e) {
        expect($e->shouldDisconnect)->toBeFalse();
    }

    expect($account->fresh()->connection_status)->toBe('connected');
});

it('selects only the configured primary Gmail account', function () {
    $secondary = gmailTestAccount([
        'account_email' => 'dimasauliaaa@gmail.com',
        'display_name' => 'Dimas Aulia',
    ]);

    $primary = gmailTestAccount([
        'account_email' => 'hello@example.test',
        'display_name' => 'Primary Gmail',
    ]);

    $authenticator = new GmailAccountAuthenticator(new GoogleClient);

    expect($authenticator->connectedAccount()?->id)->toBe($primary->id)
        ->and($secondary->fresh()->connection_status)->toBe('connected');
});

it('does not select any Gmail account when the primary account is disconnected', function () {
    gmailTestAccount([
        'account_email' => 'dimasauliaaa@gmail.com',
    ]);

    config(['communications.email.gmail.primary_email' => 'hello@example.test']);

    $authenticator = new GmailAccountAuthenticator(new GoogleClient);

    expect($authenticator->connectedAccount())->toBeNull();
});
