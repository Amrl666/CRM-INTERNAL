<?php

namespace App\Domain\Communications\DTOs;

use App\Domain\Communications\Models\CommunicationAccount;

final class GmailSyncDispatchResult
{
    public const STATUS_DISPATCHED = 'dispatched';

    public const STATUS_ALREADY_RUNNING = 'already_running';

    public const STATUS_THROTTLED = 'throttled';

    public const STATUS_UNAVAILABLE = 'unavailable';

    public const STATUS_FAILED = 'failed';

    private function __construct(
        public readonly string $status,
        public readonly string $message,
        public readonly ?string $accountId = null,
    ) {}

    public static function dispatched(CommunicationAccount $account): self
    {
        return new self(
            self::STATUS_DISPATCHED,
            'Sinkronisasi Gmail dimulai.',
            $account->id,
        );
    }

    public static function alreadyRunning(): self
    {
        return new self(
            self::STATUS_ALREADY_RUNNING,
            'Sinkronisasi Gmail sedang berjalan atau sudah menunggu di antrean.',
        );
    }

    public static function throttled(): self
    {
        return new self(
            self::STATUS_THROTTLED,
            'Sinkronisasi otomatis berikutnya belum jatuh tempo.',
        );
    }

    public static function unavailable(): self
    {
        return new self(
            self::STATUS_UNAVAILABLE,
            'Akun Gmail utama belum terhubung.',
        );
    }

    public static function failed(): self
    {
        return new self(
            self::STATUS_FAILED,
            'Sinkronisasi Gmail belum dapat dimulai. Silakan coba lagi.',
        );
    }

    public function isDispatched(): bool
    {
        return $this->status === self::STATUS_DISPATCHED;
    }

    public function isAlreadyRunning(): bool
    {
        return $this->status === self::STATUS_ALREADY_RUNNING;
    }
}
