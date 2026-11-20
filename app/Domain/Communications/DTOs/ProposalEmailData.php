<?php

namespace App\Domain\Communications\DTOs;

final class ProposalEmailData
{
    public function __construct(
        public readonly string $to,
        public readonly string $subject,
        public readonly string $htmlBody,
        public readonly string $textBody,
        public readonly string $attachmentPath,
        public readonly string $attachmentFilename,
        public readonly string $attachmentMime = 'application/pdf',
        public readonly ?string $senderName = null,
        public readonly ?string $senderPosition = null,
    ) {}
}
