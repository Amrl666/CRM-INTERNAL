<?php

namespace App\Domain\Communications\DTOs;

final class ProposalWhatsAppData
{
    public function __construct(
        public readonly string $to,
        public readonly string $body,
        public readonly string $attachmentPath,
        public readonly string $attachmentFilename,
        public readonly string $attachmentMime = 'application/pdf',
    ) {}
}
