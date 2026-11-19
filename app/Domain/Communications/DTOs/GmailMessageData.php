<?php

namespace App\Domain\Communications\DTOs;

final class GmailMessageData
{
    public function __construct(
        public readonly string $providerMessageId,
        public readonly ?string $threadId,
        public readonly ?string $subject,
        public readonly ?string $senderEmail,
        public readonly ?string $senderName,
        public readonly array $toRecipients,
        public readonly array $ccRecipients,
        public readonly array $bccRecipients,
        public readonly ?string $body,
        public readonly array $labelIds,
        public readonly ?\DateTimeImmutable $receivedAt,
        public readonly bool $isRead,
        public readonly bool $isStarred,
        public readonly string $direction,
        public readonly ?string $snippet = null,
    ) {}
}
