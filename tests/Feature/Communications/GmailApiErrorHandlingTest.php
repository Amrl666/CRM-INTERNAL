<?php

use App\Domain\Communications\Models\CommunicationAccount;
use App\Domain\Communications\Services\GmailAccountAuthenticator;
use App\Domain\Communications\Services\GmailApiException;
use App\Domain\Communications\Services\GmailApiService;
use App\Domain\Communications\Services\GmailAuthenticationException;
use Google\Client as GoogleClient;
use Google\Service\Exception as GoogleServiceException;
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
        'communications.email.gmail.redirect_uri' => 'https://example.test/callback',
        'communications.email.gmail.primary_email' => 'hello@example.test',
    ]);
});

function gmailApiErrorAccount(): CommunicationAccount
{
    return CommunicationAccount::create([
        'provider' => 'gmail',
        'account_email' => 'hello@example.test',
        'display_name' => 'Test Gmail',
        'access_token' => 'stored-access-token',
        'refresh_token' => 'stored-refresh-token',
        'token_expires_at' => now()->addHour(),
        'connection_status' => 'connected',
    ]);
}

function gmailApiErrorResponse(int $status, array $body): Response
{
    return new Response(
        $status,
        ['Content-Type' => 'application/json'],
        json_encode($body, JSON_THROW_ON_ERROR),
    );
}

function gmailApiErrorService(array $responses): GmailApiService
{
    $client = new GoogleClient;

    $client->setHttpClient(new HttpClient([
        'handler' => HandlerStack::create(new MockHandler($responses)),
    ]));

    return new GmailApiService(
        new GmailAccountAuthenticator($client),
    );
}

function gmailApiErrorBody(int $status, string $message, string $reason): array
{
    return [
        'error' => [
            'code' => $status,
            'message' => $message,
            'errors' => [[
                'domain' => 'global',
                'reason' => $reason,
                'message' => $message,
            ]],
        ],
    ];
}

it('refreshes the token and retries once after HTTP 401', function () {
    $account = gmailApiErrorAccount();

    $service = gmailApiErrorService([
        gmailApiErrorResponse(401, gmailApiErrorBody(401, 'Invalid Credentials', 'authError')),
        gmailApiErrorResponse(200, [
            'access_token' => 'fresh-access-token',
            'expires_in' => 3600,
            'token_type' => 'Bearer',
        ]),
        gmailApiErrorResponse(200, [
            'messages' => [],
            'resultSizeEstimate' => 0,
        ]),
    ]);

    $result = $service->listMessages();

    expect($result->getMessages())->toBeEmpty()
        ->and($account->fresh()->access_token)->toBe('fresh-access-token')
        ->and($account->fresh()->connection_status)->toBe('connected');
});

it('requires reconnect after HTTP 401 when refresh fails with invalid_grant', function () {
    $account = gmailApiErrorAccount();

    $service = gmailApiErrorService([
        gmailApiErrorResponse(401, gmailApiErrorBody(401, 'Invalid Credentials', 'authError')),
        gmailApiErrorResponse(400, [
            'error' => 'invalid_grant',
            'error_description' => 'Token has been expired or revoked.',
        ]),
    ]);

    try {
        $service->listMessages();
        $this->fail('Expected GmailAuthenticationException was not thrown.');
    } catch (GmailAuthenticationException $e) {
        expect($e->shouldDisconnect)->toBeTrue()
            ->and($e->requiresReconnect())->toBeTrue()
            ->and($e->reason)->toBe(GmailAuthenticationException::REASON_INVALID_GRANT)
            ->and($e->getMessage())->toContain('Hubungkan ulang');
    }

    expect($account->fresh()->connection_status)->toBe('disconnected');
});

it('requires reconnect after a second HTTP 401 following a successful refresh', function () {
    $account = gmailApiErrorAccount();

    $service = gmailApiErrorService([
        gmailApiErrorResponse(401, gmailApiErrorBody(401, 'Invalid Credentials', 'authError')),
        gmailApiErrorResponse(200, [
            'access_token' => 'fresh-access-token',
            'expires_in' => 3600,
            'token_type' => 'Bearer',
        ]),
        gmailApiErrorResponse(401, gmailApiErrorBody(401, 'Invalid Credentials', 'authError')),
    ]);

    try {
        $service->listMessages();
        $this->fail('Expected GmailAuthenticationException was not thrown.');
    } catch (GmailAuthenticationException $e) {
        expect($e->shouldDisconnect)->toBeTrue()
            ->and($e->reason)->toBe(GmailAuthenticationException::REASON_UNAUTHORIZED);
    }

    expect($account->fresh()->connection_status)->toBe('disconnected');
});

it('requires reconnect for HTTP 403 insufficientPermissions', function () {
    $account = gmailApiErrorAccount();

    $service = gmailApiErrorService([
        gmailApiErrorResponse(403, gmailApiErrorBody(
            403,
            'Request had insufficient authentication scopes.',
            'insufficientPermissions',
        )),
    ]);

    try {
        $service->listMessages();
        $this->fail('Expected GmailAuthenticationException was not thrown.');
    } catch (GmailAuthenticationException $e) {
        expect($e->shouldDisconnect)->toBeTrue()
            ->and($e->reason)->toBe(GmailAuthenticationException::REASON_INSUFFICIENT_PERMISSIONS)
            ->and($e->getMessage())->toContain('gmail.readonly');
    }

    expect($account->fresh()->connection_status)->toBe('disconnected');
});

it('does not treat every HTTP 403 as a reconnect problem', function () {
    $account = gmailApiErrorAccount();

    $service = gmailApiErrorService([
        gmailApiErrorResponse(403, gmailApiErrorBody(
            403,
            'Access to this resource is forbidden by account policy.',
            'forbidden',
        )),
    ]);

    try {
        $service->listMessages();
        $this->fail('Expected GmailApiException was not thrown.');
    } catch (GmailApiException $e) {
        expect($e->retryable)->toBeFalse()
            ->and($e->httpStatus)->toBe(403);
    }

    expect($account->fresh()->connection_status)->toBe('connected');
});

it('retries an HTTP 403 rate limit instead of disconnecting the account', function () {
    $account = gmailApiErrorAccount();

    $service = gmailApiErrorService([
        gmailApiErrorResponse(403, gmailApiErrorBody(
            403,
            'User rate limit exceeded.',
            'userRateLimitExceeded',
        )),
    ]);

    try {
        $service->listMessages();
        $this->fail('Expected GoogleServiceException was not thrown.');
    } catch (GoogleServiceException $e) {
        expect($e->getCode())->toBe(403);
    }

    expect($account->fresh()->connection_status)->toBe('connected');
});

it('retries HTTP 429 with the queue backoff policy', function () {
    $account = gmailApiErrorAccount();

    $service = gmailApiErrorService([
        gmailApiErrorResponse(429, gmailApiErrorBody(
            429,
            'Rate Limit Exceeded',
            'rateLimitExceeded',
        )),
    ]);

    try {
        $service->listMessages();
        $this->fail('Expected GoogleServiceException was not thrown.');
    } catch (GoogleServiceException $e) {
        expect($e->getCode())->toBe(429);
    }

    expect($account->fresh()->connection_status)->toBe('connected');
});

it('retries HTTP 5xx with the queue backoff policy', function () {
    $account = gmailApiErrorAccount();

    $service = gmailApiErrorService([
        gmailApiErrorResponse(503, gmailApiErrorBody(
            503,
            'Backend Error',
            'backendError',
        )),
    ]);

    try {
        $service->listMessages();
        $this->fail('Expected GoogleServiceException was not thrown.');
    } catch (GoogleServiceException $e) {
        expect($e->getCode())->toBe(503);
    }

    expect($account->fresh()->connection_status)->toBe('connected');
});
