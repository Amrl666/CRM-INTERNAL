<?php

use App\Domain\Communications\Models\CommunicationAccount;
use App\Domain\Communications\Services\GmailOAuthService;
use Google\Client as GoogleClient;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'communications.email.gmail.client_id' => 'test-client-id',
        'communications.email.gmail.client_secret' => 'test-client-secret',
        'communications.email.gmail.redirect_uri' => 'https://example.test/settings/gmail/callback',
        'communications.email.gmail.primary_email' => 'hello@example.test',
    ]);
});

function gmailOAuthTestService(array $responses): GmailOAuthService
{
    $client = new GoogleClient;

    $client->setHttpClient(new HttpClient([
        'handler' => HandlerStack::create(
            new MockHandler($responses),
        ),
    ]));

    return new GmailOAuthService($client);
}

function gmailOAuthTestResponse(int $status, array $body): Response
{
    return new Response(
        $status,
        ['Content-Type' => 'application/json'],
        json_encode($body, JSON_THROW_ON_ERROR),
    );
}

function gmailOAuthTestScope(string $extra = ''): string
{
    return trim(
        'https://www.googleapis.com/auth/gmail.readonly https://www.googleapis.com/auth/gmail.send openid email profile '.$extra,
    );
}

it('creates a connected account from a valid OAuth callback', function () {
    $service = gmailOAuthTestService([
        gmailOAuthTestResponse(200, [
            'access_token' => 'new-access-token',
            'refresh_token' => 'new-refresh-token',
            'expires_in' => 3600,
            'scope' => gmailOAuthTestScope(),
            'token_type' => 'Bearer',
        ]),
        gmailOAuthTestResponse(200, [
            'email' => 'hello@example.test',
            'name' => 'Prospectra Gmail',
            'verified_email' => true,
        ]),
    ]);

    $account = $service->handleCallback('test-authorization-code');

    expect($account)->toBeInstanceOf(CommunicationAccount::class)
        ->and($account->account_email)->toBe('hello@example.test')
        ->and($account->display_name)->toBe('Prospectra Gmail')
        ->and($account->connection_status)->toBe('connected')
        ->and($account->access_token)->toBe('new-access-token')
        ->and($account->refresh_token)->toBe('new-refresh-token')
        ->and($account->token_expires_at)->not->toBeNull()
        ->and(CommunicationAccount::query()->count())->toBe(1);
});

it('preserves the existing refresh token when Google does not return a new one', function () {
    $existing = CommunicationAccount::create([
        'provider' => 'gmail',
        'account_email' => 'hello@example.test',
        'display_name' => 'Old Name',
        'access_token' => 'old-access-token',
        'refresh_token' => 'existing-refresh-token',
        'token_expires_at' => now()->addHour(),
        'connection_status' => 'disconnected',
    ]);

    $service = gmailOAuthTestService([
        gmailOAuthTestResponse(200, [
            'access_token' => 'new-access-token',
            'expires_in' => 3600,
            'scope' => gmailOAuthTestScope(),
            'token_type' => 'Bearer',
        ]),
        gmailOAuthTestResponse(200, [
            'email' => 'hello@example.test',
            'name' => 'Updated Gmail',
            'verified_email' => true,
        ]),
    ]);

    $account = $service->handleCallback('test-authorization-code');

    expect($account->id)->toBe($existing->id)
        ->and($account->refresh_token)->toBe('existing-refresh-token')
        ->and($account->access_token)->toBe('new-access-token')
        ->and($account->connection_status)->toBe('connected')
        ->and(CommunicationAccount::query()->count())->toBe(1);
});

it('rejects OAuth callback when Google does not return a refresh token for a new account', function () {
    $service = gmailOAuthTestService([
        gmailOAuthTestResponse(200, [
            'access_token' => 'new-access-token',
            'expires_in' => 3600,
            'scope' => gmailOAuthTestScope(),
        ]),
        gmailOAuthTestResponse(200, [
            'email' => 'hello@example.test',
            'name' => 'Prospectra Gmail',
        ]),
    ]);

    expect(fn () => $service->handleCallback('test-authorization-code'))
        ->toThrow(RuntimeException::class);

    expect(CommunicationAccount::query()->count())->toBe(0);
});

it('rejects an invalid authorization code response', function () {
    $service = gmailOAuthTestService([
        gmailOAuthTestResponse(400, [
            'error' => 'invalid_grant',
            'error_description' => 'Invalid authorization code',
        ]),
    ]);

    expect(fn () => $service->handleCallback('invalid-code'))
        ->toThrow(RuntimeException::class);

    expect(CommunicationAccount::query()->count())->toBe(0);
});

it('rejects OAuth callback when Google reports an unverified email address', function () {
    $service = gmailOAuthTestService([
        gmailOAuthTestResponse(200, [
            'access_token' => 'new-access-token',
            'refresh_token' => 'new-refresh-token',
            'expires_in' => 3600,
            'scope' => gmailOAuthTestScope(),
            'token_type' => 'Bearer',
        ]),
        gmailOAuthTestResponse(200, [
            'email' => 'unverified@example.test',
            'name' => 'Unverified Account',
            'verified_email' => false,
        ]),
    ]);

    expect(fn () => $service->handleCallback('test-authorization-code'))
        ->toThrow(RuntimeException::class);

    expect(CommunicationAccount::query()->count())->toBe(0);
});

it('rejects OAuth connection from a non-primary Gmail account without changing accounts', function () {
    $secondary = CommunicationAccount::create([
        'provider' => 'gmail',
        'account_email' => 'dimasauliaaa@gmail.com',
        'display_name' => 'Dimas Aulia',
        'access_token' => 'existing-access-token',
        'refresh_token' => 'existing-refresh-token',
        'token_expires_at' => now()->addHour(),
        'connection_status' => 'connected',
    ]);

    $service = gmailOAuthTestService([
        gmailOAuthTestResponse(200, [
            'access_token' => 'other-access-token',
            'refresh_token' => 'other-refresh-token',
            'expires_in' => 3600,
            'scope' => gmailOAuthTestScope(),
        ]),
        gmailOAuthTestResponse(200, [
            'email' => 'dimasauliaaa@gmail.com',
            'name' => 'Dimas Aulia',
            'verified_email' => true,
        ]),
    ]);

    expect(fn () => $service->handleCallback('other-account-code'))
        ->toThrow(RuntimeException::class);

    expect($secondary->fresh()->connection_status)->toBe('connected')
        ->and($secondary->fresh()->access_token)->toBe('existing-access-token')
        ->and(CommunicationAccount::query()->count())->toBe(1);
});

it('rejects OAuth callback when required Gmail scopes were not granted', function () {
    $service = gmailOAuthTestService([
        gmailOAuthTestResponse(200, [
            'access_token' => 'new-access-token',
            'refresh_token' => 'new-refresh-token',
            'expires_in' => 3600,
            'scope' => 'openid email profile',
        ]),
    ]);

    expect(fn () => $service->handleCallback('test-authorization-code'))
        ->toThrow(RuntimeException::class, 'Izin Gmail belum lengkap');

    expect(CommunicationAccount::query()->count())->toBe(0);
});

it('rejects OAuth callback when Gmail send scope was not granted', function () {
    $service = gmailOAuthTestService([
        gmailOAuthTestResponse(200, [
            'access_token' => 'new-access-token',
            'refresh_token' => 'new-refresh-token',
            'expires_in' => 3600,
            'scope' => 'https://www.googleapis.com/auth/gmail.readonly openid email profile',
        ]),
    ]);

    expect(fn () => $service->handleCallback('test-authorization-code'))
        ->toThrow(RuntimeException::class, 'Izin Gmail belum lengkap');

    expect(CommunicationAccount::query()->count())->toBe(0);
});

it('keeps a secondary Gmail account connected when primary scope is incomplete', function () {
    $secondary = CommunicationAccount::create([
        'provider' => 'gmail',
        'account_email' => 'dimasauliaaa@gmail.com',
        'display_name' => 'Dimas Aulia',
        'access_token' => 'secondary-access-token',
        'refresh_token' => 'secondary-refresh-token',
        'token_expires_at' => now()->addHour(),
        'connection_status' => 'connected',
    ]);

    $service = gmailOAuthTestService([
        gmailOAuthTestResponse(200, [
            'access_token' => 'primary-access-token',
            'refresh_token' => 'primary-refresh-token',
            'expires_in' => 3600,
            'scope' => 'openid email profile',
        ]),
    ]);

    expect(fn () => $service->handleCallback('primary-account-code'))
        ->toThrow(RuntimeException::class, 'Izin Gmail belum lengkap');

    expect($secondary->fresh()->connection_status)->toBe('connected')
        ->and($secondary->fresh()->access_token)->toBe('secondary-access-token')
        ->and(CommunicationAccount::query()->count())->toBe(1);
});

it('reconnects a disconnected account without resetting its history cursor', function () {
    $existing = CommunicationAccount::create([
        'provider' => 'gmail',
        'account_email' => 'hello@example.test',
        'display_name' => 'Primary Gmail',
        'access_token' => null,
        'refresh_token' => null,
        'token_expires_at' => null,
        'connection_status' => 'disconnected',
        'gmail_history_id' => 'history-77',
    ]);

    $service = gmailOAuthTestService([
        gmailOAuthTestResponse(200, [
            'access_token' => 'reconnect-access-token',
            'refresh_token' => 'reconnect-refresh-token',
            'expires_in' => 3600,
            'scope' => gmailOAuthTestScope(),
            'token_type' => 'Bearer',
        ]),
        gmailOAuthTestResponse(200, [
            'email' => 'hello@example.test',
            'name' => 'Primary Gmail',
            'verified_email' => true,
        ]),
    ]);

    $account = $service->handleCallback('reconnect-code');

    expect($account->id)->toBe($existing->id)
        ->and($account->connection_status)->toBe('connected')
        ->and($account->access_token)->toBe('reconnect-access-token')
        ->and($account->refresh_token)->toBe('reconnect-refresh-token')
        ->and($account->gmail_history_id)->toBe('history-77')
        ->and(CommunicationAccount::query()->count())->toBe(1);
});

it('preserves other connected Gmail accounts when the primary account connects', function () {
    $secondary = CommunicationAccount::create([
        'provider' => 'gmail',
        'account_email' => 'dimasauliaaa@gmail.com',
        'display_name' => 'Dimas Aulia',
        'access_token' => 'secondary-access-token',
        'refresh_token' => 'secondary-refresh-token',
        'token_expires_at' => now()->addHour(),
        'connection_status' => 'connected',
    ]);

    $service = gmailOAuthTestService([
        gmailOAuthTestResponse(200, [
            'access_token' => 'primary-access-token',
            'refresh_token' => 'primary-refresh-token',
            'expires_in' => 3600,
            'scope' => gmailOAuthTestScope(),
        ]),
        gmailOAuthTestResponse(200, [
            'email' => 'hello@example.test',
            'name' => 'Primary Gmail',
            'verified_email' => true,
        ]),
    ]);

    $primary = $service->handleCallback('primary-account-code');

    expect($primary->connection_status)->toBe('connected')
        ->and($primary->account_email)->toBe('hello@example.test')
        ->and($secondary->fresh()->connection_status)->toBe('connected')
        ->and($secondary->fresh()->access_token)->toBe('secondary-access-token')
        ->and(CommunicationAccount::query()->count())->toBe(2);
});
