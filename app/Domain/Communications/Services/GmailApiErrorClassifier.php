<?php

namespace App\Domain\Communications\Services;

use Google\Service\Exception as GoogleServiceException;

/**
 * Classify Google API errors without treating every 403 as a broken grant.
 */
final class GmailApiErrorClassifier
{
    /**
     * Google reasons that mean the granted OAuth scopes are no longer enough.
     *
     * @var array<int, string>
     */
    private const RECONNECT_REASONS = [
        'insufficientPermissions',
        'insufficientFilePermissions',
        'ACCESS_TOKEN_SCOPE_INSUFFICIENT',
    ];

    /**
     * Google reasons that are rate limits even when returned as HTTP 403.
     *
     * @var array<int, string>
     */
    private const RATE_LIMIT_REASONS = [
        'rateLimitExceeded',
        'userRateLimitExceeded',
        'quotaExceeded',
        'dailyLimitExceeded',
        'limitExceeded',
    ];

    public function requiresReconnect(GoogleServiceException $exception): bool
    {
        return (int) $exception->getCode() === 403
            && $this->hasReason($exception, self::RECONNECT_REASONS);
    }

    public function isRateLimit(GoogleServiceException $exception): bool
    {
        $code = (int) $exception->getCode();

        return $code === 429
            || ($code === 403 && $this->hasReason($exception, self::RATE_LIMIT_REASONS));
    }

    public function isRetryable(GoogleServiceException $exception): bool
    {
        $code = (int) $exception->getCode();

        return $this->isRateLimit($exception)
            || $code === 0
            || $code === 408
            || $code >= 500;
    }

    /**
     * @param  array<int, string>  $reasons
     */
    private function hasReason(
        GoogleServiceException $exception,
        array $reasons,
    ): bool {
        $errorReasons = collect($exception->getErrors() ?? [])
            ->pluck('reason')
            ->filter(fn ($reason): bool => is_string($reason))
            ->all();

        return array_intersect($errorReasons, $reasons) !== [];
    }
}
