<?php

use App\Domain\Communications\Services\GmailMessageMapper;
use Google\Service\Gmail\Message;
use Google\Service\Gmail\MessagePart;
use Google\Service\Gmail\MessagePartBody;
use Google\Service\Gmail\MessagePartHeader;

function gmailMapperEncode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function gmailMapperHeader(string $name, string $value): MessagePartHeader
{
    $header = new MessagePartHeader;
    $header->setName($name);
    $header->setValue($value);

    return $header;
}

function gmailMapperPart(
    string $mimeType,
    string $body,
    array $parts = [],
): MessagePart {
    $part = new MessagePart;
    $part->setMimeType($mimeType);

    if ($body !== '') {
        $partBody = new MessagePartBody;
        $partBody->setData(gmailMapperEncode($body));
        $part->setBody($partBody);
    }

    if ($parts !== []) {
        $part->setParts($parts);
    }

    return $part;
}

it('maps Gmail headers, recipients, labels and message metadata', function () {
    $payload = gmailMapperPart(
        'text/plain',
        'Hello from Gmail',
    );

    $payload->setHeaders([
        gmailMapperHeader('From', 'Enigma <sender@example.com>'),
        gmailMapperHeader('To', 'Recipient <recipient@example.com>'),
        gmailMapperHeader('Cc', 'CC User <cc@example.com>'),
        gmailMapperHeader('Bcc', 'BCC User <bcc@example.com>'),
        gmailMapperHeader('Subject', 'Test Gmail Inbox'),
    ]);

    $message = new Message;
    $message->setId('gmail-message-001');
    $message->setThreadId('gmail-thread-001');
    $message->setInternalDate('1731931200000');
    $message->setLabelIds(['INBOX', 'UNREAD', 'STARRED']);
    $message->setSnippet('Hello from Gmail');
    $message->setPayload($payload);

    $result = (new GmailMessageMapper)->map($message);

    expect($result->providerMessageId)->toBe('gmail-message-001')
        ->and($result->threadId)->toBe('gmail-thread-001')
        ->and($result->subject)->toBe('Test Gmail Inbox')
        ->and($result->senderEmail)->toBe('sender@example.com')
        ->and($result->senderName)->toBe('Enigma')
        ->and($result->toRecipients)->toBe([
            ['email' => 'recipient@example.com', 'name' => 'Recipient'],
        ])
        ->and($result->ccRecipients)->toBe([
            ['email' => 'cc@example.com', 'name' => 'CC User'],
        ])
        ->and($result->bccRecipients)->toBe([
            ['email' => 'bcc@example.com', 'name' => 'BCC User'],
        ])
        ->and($result->body)->toBe('Hello from Gmail')
        ->and($result->labelIds)->toBe(['INBOX', 'UNREAD', 'STARRED'])
        ->and($result->isRead)->toBeFalse()
        ->and($result->isStarred)->toBeTrue()
        ->and($result->direction)->toBe('inbound')
        ->and($result->receivedAt?->getTimestamp())->toBe(1731931200);
});

it('maps sent messages as outbound and read when UNREAD label is absent', function () {
    $payload = gmailMapperPart('text/plain', 'Sent message');

    $message = new Message;
    $message->setId('gmail-sent-001');
    $message->setLabelIds(['SENT']);
    $message->setPayload($payload);

    $result = (new GmailMessageMapper)->map($message);

    expect($result->direction)->toBe('outbound')
        ->and($result->isRead)->toBeTrue()
        ->and($result->isStarred)->toBeFalse()
        ->and($result->body)->toBe('Sent message');
});

it('extracts the first available body from multipart messages', function () {
    $plainPart = gmailMapperPart(
        'text/plain',
        'Plain text body',
    );

    $htmlPart = gmailMapperPart(
        'text/html',
        '<p>HTML body</p>',
    );

    $payload = new MessagePart;
    $payload->setMimeType('multipart/alternative');
    $payload->setParts([$plainPart, $htmlPart]);

    $message = new Message;
    $message->setId('gmail-multipart-001');
    $message->setPayload($payload);
    $message->setLabelIds(['INBOX']);

    $result = (new GmailMessageMapper)->map($message);

    expect($result->body)->toBe('Plain text body');
});

it('handles missing optional Gmail metadata safely', function () {
    $message = new Message;
    $message->setId('gmail-minimal-001');

    $result = (new GmailMessageMapper)->map($message);

    expect($result->providerMessageId)->toBe('gmail-minimal-001')
        ->and($result->threadId)->toBeNull()
        ->and($result->subject)->toBeNull()
        ->and($result->senderEmail)->toBeNull()
        ->and($result->senderName)->toBeNull()
        ->and($result->toRecipients)->toBe([])
        ->and($result->ccRecipients)->toBe([])
        ->and($result->bccRecipients)->toBe([])
        ->and($result->body)->toBeNull()
        ->and($result->labelIds)->toBe([])
        ->and($result->receivedAt)->toBeNull()
        ->and($result->isRead)->toBeTrue()
        ->and($result->isStarred)->toBeFalse()
        ->and($result->direction)->toBe('inbound');
});
