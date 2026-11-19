
<?php

use App\Domain\Communications\DTOs\EmailMessageData;
use App\Domain\Communications\Models\CommunicationAccount;
use App\Domain\Communications\Providers\GmailProvider;
use App\Domain\Communications\Services\GmailAccountAuthenticator;
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
        'communications.email.gmail.redirect_uri' => 'https://example.test/callback',
        'communications.email.gmail.primary_email' => 'hello@example.test',
    ]);
});

function gmailProviderTestAccount(): CommunicationAccount
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

function gmailProviderTestInstance(array $responses): GmailProvider
{
    $client = new GoogleClient;

    $client->setHttpClient(new HttpClient([
        'handler' => HandlerStack::create(
            new MockHandler($responses),
        ),
    ]));

    return new GmailProvider(
        new GmailAccountAuthenticator($client),
    );
}

function gmailProviderTestResponse(int $status, array $body): Response
{
    return new Response(
        $status,
        ['Content-Type' => 'application/json'],
        json_encode($body, JSON_THROW_ON_ERROR),
    );
}

function gmailProviderTestMessage(): EmailMessageData
{
    return new EmailMessageData(
        to: 'recipient@example.test',
        subject: 'Test email',
        body: 'Test email body',
        replyTo: 'reply@example.test',
    );
}

it('creates a Gmail draft successfully', function () {
    gmailProviderTestAccount();

    $provider = gmailProviderTestInstance([
        gmailProviderTestResponse(200, [
            'id' => 'draft-123',
            'message' => ['id' => 'message-123'],
        ]),
    ]);

    $result = $provider->createDraft(gmailProviderTestMessage());

    expect($result->success)->toBeTrue()
        ->and($result->status)->toBe('draft')
        ->and($result->providerMessageId)->toBe('draft-123');
});

it('returns failed when Gmail rate limits draft creation', function () {
    gmailProviderTestAccount();

    $provider = gmailProviderTestInstance(
        array_fill(0, 6, gmailProviderTestResponse(429, [
            'error' => [
                'code' => 429,
                'message' => 'Rate Limit Exceeded',
            ],
        ])),
    );

    $result = $provider->createDraft(gmailProviderTestMessage());

    expect($result->success)->toBeFalse()
        ->and($result->status)->toBe('failed')
        ->and($result->reason)->toBe('gmail_rate_limited');
});

it('returns unknown when Gmail draft creation has a server error', function () {
    gmailProviderTestAccount();

    $provider = gmailProviderTestInstance(
        array_fill(0, 6, gmailProviderTestResponse(503, [
            'error' => [
                'code' => 503,
                'message' => 'Backend Error',
            ],
        ])),
    );

    $result = $provider->createDraft(gmailProviderTestMessage());

    expect($result->success)->toBeFalse()
        ->and($result->status)->toBe('unknown')
        ->and($result->reason)->toBe('gmail_draft_outcome_unknown');
});

it('sends an approved Gmail draft successfully', function () {
    gmailProviderTestAccount();

    $provider = gmailProviderTestInstance([
        gmailProviderTestResponse(200, [
            'id' => 'sent-message-123',
            'threadId' => 'thread-123',
        ]),
    ]);

    $result = $provider->sendApprovedDraft('draft-123');

    expect($result->success)->toBeTrue()
        ->and($result->status)->toBe('sent')
        ->and($result->providerMessageId)->toBe('sent-message-123');
});

it('returns unknown when approved draft sending has a server error', function () {
    gmailProviderTestAccount();

    $provider = gmailProviderTestInstance(
        array_fill(0, 6, gmailProviderTestResponse(503, [
            'error' => [
                'code' => 503,
                'message' => 'Backend Error',
            ],
        ])),
    );

    $result = $provider->sendApprovedDraft('draft-123');

    expect($result->success)->toBeFalse()
        ->and($result->status)->toBe('unknown')
        ->and($result->reason)->toBe('gmail_send_outcome_unknown');
});

it('returns unknown when approved draft response has no message ID', function () {
    gmailProviderTestAccount();

    $provider = gmailProviderTestInstance([
        gmailProviderTestResponse(200, [
            'threadId' => 'thread-123',
        ]),
    ]);

    $result = $provider->sendApprovedDraft('draft-123');

    expect($result->success)->toBeFalse()
        ->and($result->status)->toBe('unknown')
        ->and($result->reason)->toBe('gmail_send_result_missing_id');
});

it('disconnects the Gmail account when draft creation receives unauthorized', function () {
    $account = gmailProviderTestAccount();

    $provider = gmailProviderTestInstance(
        array_fill(0, 6, gmailProviderTestResponse(401, [
            'error' => ['code' => 401, 'message' => 'Invalid Credentials'],
        ])),
    );

    $result = $provider->createDraft(gmailProviderTestMessage());

    expect($result->success)->toBeFalse()
        ->and($result->status)->toBe('failed')
        ->and($result->reason)->toBe('gmail_authorization_failed')
        ->and($account->fresh()->connection_status)->toBe('disconnected');
});

it('disconnects the Gmail account when approved draft sending receives unauthorized', function () {
    $account = gmailProviderTestAccount();

    $provider = gmailProviderTestInstance(
        array_fill(0, 6, gmailProviderTestResponse(401, [
            'error' => ['code' => 401, 'message' => 'Invalid Credentials'],
        ])),
    );

    $result = $provider->sendApprovedDraft('draft-123');

    expect($result->success)->toBeFalse()
        ->and($result->status)->toBe('failed')
        ->and($result->reason)->toBe('gmail_authorization_failed')
        ->and($account->fresh()->connection_status)->toBe('disconnected');
});
