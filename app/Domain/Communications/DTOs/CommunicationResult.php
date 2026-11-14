<?php

namespace App\Domain\Communications\DTOs;

final class CommunicationResult
{
    private function __construct(
        public readonly bool $success,
        public readonly ?string $providerMessageId = null,
        public readonly ?string $status = null,
        public readonly ?string $reason = null,
    ) {}

    public static function ok(string $providerMessageId, string $status = 'accepted'): self
    {
        return new self(true, $providerMessageId, $status);
    }

    public static function fail(string $reason, string $status = 'failed'): self
    {
        return new self(false, null, $status, $reason);
    }

    public static function unknown(string $reason): self
    {
        return new self(false, null, 'unknown', $reason);
    }
}
