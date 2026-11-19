<?php

use App\Domain\Communications\Models\CommunicationAccount;
use App\Domain\Communications\Services\GmailAccountAuthenticator;
use App\Domain\Communications\Services\GmailApiService;
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

function gmailApiTestAccount(): CommunicationAccount
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

/**
 * Build the real authenticator with a mocked HTTP transport.
 */
function gmailApiTestService(array $responses): GmailApiService
{
    $client = new GoogleClient;

    $client->setHttpClient(new HttpClient([
        'handler' => HandlerStack::create(
            new MockHandler($responses),
        ),
    ]));

    return new GmailApiService(
        new GmailAccountAuthenticator($client),
    );
}

function gmailApiTestResponse(int $status, array $body): Response
{
    return new Response(
        $status,
        ['Content-Type' => 'application/json'],
        json_encode($body, JSON_THROW_ON_ERROR),
    );
}

it('lists Gmail messages with labels, pagination and search query', function () {
    gmailApiTestAccount();

    $service = gmailApiTestService([
        gmailApiTestResponse(200, [
            'messages' => [
                ['id' => 'message-001', 'threadId' => 'thread-001'],
            ],
            'nextPageToken' => 'next-page',
            'resultSizeEstimate' => 120,
        ]),
    ]);

    $result = $service->listMessages(
        ['INBOX', 'UNREAD', 'INBOX'],
        'next-page',
        25,
        'from:sender@example.com',
    );

    expect($result->getNextPageToken())->toBe('next-page')
        ->and($result->getResultSizeEstimate())->toBe(120)
        ->and($result->getMessages()[0]->getId())->toBe('message-001');
});

it('gets a Gmail message using full format', function () {
    gmailApiTestAccount();

    $service = gmailApiTestService([
        gmailApiTestResponse(200, [
            'id' => 'message-123',
            'threadId' => 'thread-123',
            'labelIds' => ['INBOX'],
            'snippet' => 'Test message',
        ]),
    ]);

    $message = $service->getMessage('message-123');

    expect($message->getId())->toBe('message-123')
        ->and($message->getThreadId())->toBe('thread-123')
        ->and($message->getLabelIds())->toBe(['INBOX']);
});

it('gets a Gmail thread using full format', function () {
    gmailApiTestAccount();

    $service = gmailApiTestService([
        gmailApiTestResponse(200, [
            'id' => 'thread-123',
            'messages' => [
                ['id' => 'message-001', 'threadId' => 'thread-123'],
                ['id' => 'message-002', 'threadId' => 'thread-123'],
            ],
        ]),
    ]);

    $thread = $service->getThread('thread-123');

    expect($thread->getId())->toBe('thread-123')
        ->and($thread->getMessages())->toHaveCount(2)
        ->and($thread->getMessages()[0]->getId())->toBe('message-001');
});

it('rejects invalid pagination limits', function () {
    $service = gmailApiTestService([]);

    expect(fn () => $service->listMessages(maxResults: 0))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => $service->listMessages(maxResults: 101))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects empty Gmail message and thread IDs', function () {
    $service = gmailApiTestService([]);

    expect(fn () => $service->getMessage(' '))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => $service->getThread(''))
        ->toThrow(InvalidArgumentException::class);
});
