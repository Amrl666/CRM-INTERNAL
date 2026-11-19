<?php

use App\Domain\Communications\Models\CommunicationAccount;
use App\Domain\Communications\Models\CommunicationMessage;
use App\Domain\Communications\Services\GmailAccountAuthenticator;
use App\Domain\Communications\Services\GmailApiService;
use App\Domain\Communications\Services\GmailMessageMapper;
use App\Domain\Communications\Services\GmailMessageSyncService;
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

function gmailSyncTestAccount(string $email = 'hello@example.test'): CommunicationAccount
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

function gmailSyncTestResponse(array $body): Response
{
    return new Response(
        200,
        ['Content-Type' => 'application/json'],
        json_encode($body, JSON_THROW_ON_ERROR),
    );
}

function gmailSyncTestService(array $responses): GmailMessageSyncService
{
    $client = new GoogleClient;

    $client->setHttpClient(new HttpClient([
        'handler' => HandlerStack::create(new MockHandler($responses)),
    ]));

    $authenticator = new GmailAccountAuthenticator($client);

    return new GmailMessageSyncService(
        new GmailApiService($authenticator),
        new GmailMessageMapper,
        $authenticator,
    );
}

function gmailSyncTestMessage(
    string $id,
    array $labels = ['INBOX'],
    string $body = 'Hello from Gmail',
): array {
    $encodedBody = rtrim(strtr(base64_encode($body), '+/', '-_'), '=');

    return [
        'id' => $id,
        'threadId' => 'thread-'.$id,
        'labelIds' => $labels,
        'internalDate' => '1731931200000',
        'snippet' => $body,
        'payload' => [
            'mimeType' => 'text/plain',
            'headers' => [
                ['name' => 'From', 'value' => 'Sender <sender@example.com>'],
                ['name' => 'To', 'value' => 'Recipient <hello@example.test>'],
                ['name' => 'Subject', 'value' => 'Gmail Sync Test'],
            ],
            'body' => [
                'size' => strlen($body),
                'data' => $encodedBody,
            ],
        ],
    ];
}

it('stores inbound Gmail messages and does not create duplicates on repeated sync', function () {
    $account = gmailSyncTestAccount();

    $service = gmailSyncTestService([
        gmailSyncTestResponse([
            'messages' => [['id' => 'gmail-in-001', 'threadId' => 'thread-gmail-in-001']],
            'resultSizeEstimate' => 1,
        ]),
        gmailSyncTestResponse(gmailSyncTestMessage('gmail-in-001', ['INBOX', 'UNREAD'])),
        gmailSyncTestResponse([
            'messages' => [['id' => 'gmail-in-001', 'threadId' => 'thread-gmail-in-001']],
            'resultSizeEstimate' => 1,
        ]),
        gmailSyncTestResponse(gmailSyncTestMessage('gmail-in-001', ['INBOX', 'STARRED'])),
    ]);

    $first = $service->syncPage($account);
    $second = $service->syncPage($account);

    expect($first['processed'])->toBe(1)
        ->and($second['processed'])->toBe(1)
        ->and($second['next_page_token'])->toBeNull()
        ->and(CommunicationMessage::query()
            ->where('account_id', $account->id)
            ->where('provider_message_id', 'gmail-in-001')
            ->count())->toBe(1);

    $message = CommunicationMessage::query()
        ->where('account_id', $account->id)
        ->where('provider_message_id', 'gmail-in-001')
        ->firstOrFail();

    expect($message->channel)->toBe('email')
        ->and($message->direction)->toBe('inbound')
        ->and($message->status)->toBe('received')
        ->and($message->body)->toBe('Hello from Gmail')
        ->and($message->is_read)->toBeTrue()
        ->and($message->is_starred)->toBeTrue()
        ->and($message->received_at)->not->toBeNull();
});

it('updates Gmail metadata without overwriting existing CRM message content or status', function () {
    $account = gmailSyncTestAccount();

    $existing = CommunicationMessage::create([
        'account_id' => $account->id,
        'channel' => 'email',
        'direction' => 'outbound',
        'subject' => 'CRM original subject',
        'body' => 'CRM original body',
        'provider' => 'gmail',
        'provider_message_id' => 'gmail-out-001',
        'thread_id' => 'thread-gmail-out-001',
        'status' => 'sent',
    ]);

    $service = gmailSyncTestService([
        gmailSyncTestResponse([
            'messages' => [['id' => 'gmail-out-001', 'threadId' => 'thread-gmail-out-001']],
            'resultSizeEstimate' => 1,
        ]),
        gmailSyncTestResponse(gmailSyncTestMessage(
            'gmail-out-001',
            ['SENT', 'STARRED'],
            'Message body from Gmail',
        )),
    ]);

    $service->syncPage($account);

    $existing->refresh();

    expect($existing->body)->toBe('CRM original body')
        ->and($existing->subject)->toBe('CRM original subject')
        ->and($existing->status)->toBe('sent')
        ->and($existing->direction)->toBe('outbound')
        ->and($existing->is_starred)->toBeTrue()
        ->and($existing->received_at)->not->toBeNull()
        ->and($existing->sent_at)->not->toBeNull()
        ->and(CommunicationMessage::query()
            ->where('account_id', $account->id)
            ->where('provider_message_id', 'gmail-out-001')
            ->count())->toBe(1);
});

it('rejects synchronization for a secondary Gmail account', function () {
    gmailSyncTestAccount();

    $secondary = gmailSyncTestAccount('secondary@example.test');

    $service = gmailSyncTestService([]);

    expect(fn () => $service->syncPage($secondary))
        ->toThrow(RuntimeException::class, 'Sinkronisasi hanya diizinkan untuk akun Gmail utama.');
});
