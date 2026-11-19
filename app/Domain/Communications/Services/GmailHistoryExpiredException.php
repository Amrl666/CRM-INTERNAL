<?php

namespace App\Domain\Communications\Services;

use RuntimeException;
use Throwable;

/**
 * Gmail returned HTTP 404 for startHistoryId, meaning the incremental cursor
 * is no longer available and a safe full resync is required.
 */
final class GmailHistoryExpiredException extends RuntimeException
{
    public function __construct(
        string $message = 'Riwayat Gmail kedaluwarsa. Sinkronisasi penuh diperlukan.',
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
