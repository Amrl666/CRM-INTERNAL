<?php

namespace App\Domain\Communications\Services;

use RuntimeException;

/**
 * A Gmail API failure that is not an authentication/reconnect problem.
 *
 * Retryable failures (rate limits and server errors) are deliberately kept as
 * Google\Service\Exception so the queue retry/backoff policy handles them.
 */
final class GmailApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly bool $retryable = false,
        public readonly ?int $httpStatus = null,
    ) {
        parent::__construct($message, $httpStatus ?? 0);
    }
}
