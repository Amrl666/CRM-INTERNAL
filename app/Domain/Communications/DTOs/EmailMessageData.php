<?php

namespace App\Domain\Communications\DTOs;

final class EmailMessageData
{
    public function __construct(
        public readonly string $to,
        public readonly string $subject,
        public readonly string $body,
        public readonly ?string $replyTo = null,
    ) {}
}
