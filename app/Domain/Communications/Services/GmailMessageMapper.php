<?php

namespace App\Domain\Communications\Services;

use App\Domain\Communications\DTOs\GmailMessageData;
use Google\Service\Gmail\Message;
use Google\Service\Gmail\MessagePart;

final class GmailMessageMapper
{
    public function map(Message $message): GmailMessageData
    {
        $payload = $message->getPayload();
        $headers = $this->extractHeaders($payload?->getHeaders() ?? []);

        $from = $this->parseAddress($headers['from'] ?? null);

        $labelIds = $message->getLabelIds() ?? [];

        $internalDate = $message->getInternalDate();

        $body = $payload
            ? $this->extractBody($payload)
            : null;

        return new GmailMessageData(
            providerMessageId: (string) $message->getId(),
            threadId: $message->getThreadId(),
            subject: $headers['subject'] ?? null,
            senderEmail: $from['email'],
            senderName: $from['name'],
            toRecipients: $this->parseAddresses($headers['to'] ?? null),
            ccRecipients: $this->parseAddresses($headers['cc'] ?? null),
            bccRecipients: $this->parseAddresses($headers['bcc'] ?? null),
            body: $body,
            labelIds: $labelIds,
            receivedAt: $internalDate !== null
                ? (new \DateTimeImmutable('@'.((int) $internalDate / 1000)))
                : null,
            isRead: ! in_array('UNREAD', $labelIds, true),
            isStarred: in_array('STARRED', $labelIds, true),
            direction: in_array('SENT', $labelIds, true)
                ? 'outbound'
                : 'inbound',
            snippet: $message->getSnippet(),
        );
    }

    private function extractHeaders(array $headers): array
    {
        $result = [];

        foreach ($headers as $header) {
            $name = strtolower((string) $header->getName());

            if ($name !== '') {
                $result[$name] = $header->getValue();
            }
        }

        return $result;
    }

    private function parseAddress(?string $value): array
    {
        if (blank($value)) {
            return ['email' => null, 'name' => null];
        }

        if (preg_match('/^(.*?)\s*<([^<>]+)>$/', trim($value), $matches)) {
            return [
                'email' => trim($matches[2], " \t\n\r\0\x0B<>"),
                'name' => trim(trim($matches[1]), '" '),
            ];
        }

        return [
            'email' => trim($value, " \t\n\r\0\x0B<>"),
            'name' => null,
        ];
    }

    private function parseAddresses(?string $value): array
    {
        if (blank($value)) {
            return [];
        }

        $addresses = [];
        $parts = preg_split('/,(?=(?:[^"]*"[^"]*")*[^"]*$)(?![^<]*>)/', $value);

        foreach ($parts ?: [] as $part) {
            $address = $this->parseAddress(trim($part));

            if (filled($address['email'])) {
                $addresses[] = $address;
            }
        }

        return $addresses;
    }

    private function extractBody(MessagePart $part): ?string
    {
        $mimeType = strtolower((string) $part->getMimeType());
        $data = $part->getBody()?->getData();

        if ($data !== null && in_array($mimeType, [
            'text/plain',
            'text/html',
        ], true)) {
            $decoded = $this->decodeBase64Url($data);

            if ($mimeType === 'text/plain') {
                return $decoded;
            }

            return strip_tags($decoded);
        }

        $parts = $part->getParts() ?? [];

        foreach ($parts as $child) {
            $body = $this->extractBody($child);

            if ($body !== null && trim($body) !== '') {
                return $body;
            }
        }

        return null;
    }

    private function decodeBase64Url(string $data): string
    {
        $normalized = strtr($data, '-_', '+/');
        $normalized .= str_repeat('=', (4 - strlen($normalized) % 4) % 4);

        $decoded = base64_decode($normalized, true);

        return $decoded === false ? '' : $decoded;
    }
}
