<?php

namespace App\Domain\Communications\Services;

use RuntimeException;

final class GmailAuthenticationException extends RuntimeException
{
    public const REASON_TRANSIENT = 'transient';

    public const REASON_INVALID_GRANT = 'invalid_grant';

    public const REASON_MISSING_REFRESH_TOKEN = 'missing_refresh_token';

    public const REASON_UNAUTHORIZED = 'unauthorized';

    public const REASON_INSUFFICIENT_PERMISSIONS = 'insufficient_permissions';

    public const REASON_DISCONNECTED = 'disconnected';

    public const REASON_NOT_PRIMARY = 'not_primary';

    public const REASON_CONFIGURATION = 'configuration';

    public function __construct(
        string $message,
        public readonly bool $shouldDisconnect = false,
        public readonly string $reason = self::REASON_TRANSIENT,
    ) {
        parent::__construct($message);
    }

    public function requiresReconnect(): bool
    {
        return $this->shouldDisconnect;
    }
}
