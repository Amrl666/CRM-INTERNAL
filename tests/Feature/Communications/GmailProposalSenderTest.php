<?php

use App\Domain\Communications\DTOs\ProposalEmailData;
use App\Domain\Communications\Models\CommunicationAccount;
use App\Domain\Communications\Providers\GmailProposalSender;
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

function createGmailProposalTestAccount(): CommunicationAccount
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

function createGmailProposalTestSender(array $responses): GmailProposalSender
{
    $client = new GoogleClient;

    $handler = HandlerStack::create(
        new MockHandler($responses),
    );

    $client->setHttpClient(new HttpClient([
        'handler' => $handler,
    ]));

    return new GmailProposalSender(
        new GmailAccountAuthenticator($client),
    );
}

function createGmailProposalTestData(string $attachmentPath): ProposalEmailData
{
    return new ProposalEmailData(
        to: 'recipient@example.test',
        subject: 'Test proposal',
        htmlBody: '<p>Proposal</p>',
        textBody: 'Proposal',
        attachmentPath: $attachmentPath,
        attachmentFilename: 'proposal.pdf',
    );
}

function createGmailApiResponse(int $status, array $body): Response
{
    return new Response(
        $status,
        ['Content-Type' => 'application/json'],
        json_encode($body, JSON_THROW_ON_ERROR),
    );
}

it('returns accepted when Gmail responds with a message ID', function () {
    $account = createGmailProposalTestAccount();

    $attachment = tempnam(sys_get_temp_dir(), 'gmail-proposal-');
    file_put_contents($attachment, '%PDF-1.4 test attachment');

    try {
        $sender = createGmailProposalTestSender([
            createGmailApiResponse(200, [
                'id' => 'gmail-message-123',
                'threadId' => 'gmail-thread-123',
            ]),
        ]);

        $result = $sender->sendProposal(
            createGmailProposalTestData($attachment),
        );

        expect($result->success)->toBeTrue()
            ->and($result->status)->toBe('accepted')
            ->and($result->providerMessageId)->toBe('gmail-message-123')
            ->and($account->fresh()->connection_status)->toBe('connected');
    } finally {
        @unlink($attachment);
    }
});

it('returns failed when Gmail responds with rate limit', function () {
    createGmailProposalTestAccount();

    $attachment = tempnam(sys_get_temp_dir(), 'gmail-proposal-');
    file_put_contents($attachment, '%PDF-1.4 test attachment');

    try {
        $sender = createGmailProposalTestSender(
            array_fill(0, 6, createGmailApiResponse(429, [
                'error' => [
                    'code' => 429,
                    'message' => 'Rate Limit Exceeded',
                    'errors' => [
                        ['reason' => 'rateLimitExceeded'],
                    ],
                ],
            ])),
        );

        $result = $sender->sendProposal(
            createGmailProposalTestData($attachment),
        );

        expect($result->success)->toBeFalse()
            ->and($result->status)->toBe('failed')
            ->and($result->reason)->toBe('gmail_rate_limited');
    } finally {
        @unlink($attachment);
    }
});

it('returns unknown when Gmail responds with server error', function () {
    createGmailProposalTestAccount();

    $attachment = tempnam(sys_get_temp_dir(), 'gmail-proposal-');
    file_put_contents($attachment, '%PDF-1.4 test attachment');

    try {
        $sender = createGmailProposalTestSender(
            array_fill(0, 6, createGmailApiResponse(503, [
                'error' => [
                    'code' => 503,
                    'message' => 'Backend Error',
                ],
            ])),
        );

        $result = $sender->sendProposal(
            createGmailProposalTestData($attachment),
        );

        expect($result->success)->toBeFalse()
            ->and($result->status)->toBe('unknown')
            ->and($result->reason)->toBe('gmail_transient_error');
    } finally {
        @unlink($attachment);
    }
});

it('returns unknown when Gmail response does not contain a message ID', function () {
    createGmailProposalTestAccount();

    $attachment = tempnam(sys_get_temp_dir(), 'gmail-proposal-');
    file_put_contents($attachment, '%PDF-1.4 test attachment');

    try {
        $sender = createGmailProposalTestSender([
            createGmailApiResponse(200, [
                'threadId' => 'gmail-thread-123',
            ]),
        ]);

        $result = $sender->sendProposal(
            createGmailProposalTestData($attachment),
        );

        expect($result->success)->toBeFalse()
            ->and($result->status)->toBe('unknown')
            ->and($result->reason)->toBe('gmail_send_result_missing_id');
    } finally {
        @unlink($attachment);
    }
});

it('returns failed when proposal attachment cannot be read', function () {
    createGmailProposalTestAccount();

    $sender = new GmailProposalSender(
        new GmailAccountAuthenticator(new GoogleClient),
    );

    $result = $sender->sendProposal(
        createGmailProposalTestData(
            '/tmp/prospectra-file-that-does-not-exist.pdf',
        ),
    );

    expect($result->success)->toBeFalse()
        ->and($result->status)->toBe('failed')
        ->and($result->reason)->toBe('gmail_message_preparation_failed');
});

it('disconnects the Gmail account when proposal sending receives unauthorized', function () {
    $account = createGmailProposalTestAccount();

    $attachment = tempnam(sys_get_temp_dir(), 'gmail-proposal-');
    file_put_contents($attachment, '%PDF-1.4 test attachment');

    try {
        $sender = createGmailProposalTestSender(
            array_fill(0, 6, createGmailApiResponse(401, [
                'error' => [
                    'code' => 401,
                    'message' => 'Invalid Credentials',
                ],
            ])),
        );

        $result = $sender->sendProposal(createGmailProposalTestData($attachment));

        expect($result->success)->toBeFalse()
            ->and($result->status)->toBe('failed')
            ->and($result->reason)->toBe('gmail_authorization_failed')
            ->and($account->fresh()->connection_status)->toBe('disconnected');
    } finally {
        @unlink($attachment);
    }
});
