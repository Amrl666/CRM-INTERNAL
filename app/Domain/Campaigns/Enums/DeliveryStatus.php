<?php

namespace App\Domain\Campaigns\Enums;

enum DeliveryStatus: string
{
    case Pending = 'PENDING';
    case Generating = 'GENERATING';
    case Ready = 'READY';
    case Scheduled = 'SCHEDULED';
    case Processing = 'PROCESSING';
    case Accepted = 'ACCEPTED';
    case Sent = 'SENT';
    case Delivered = 'DELIVERED';
    case Unknown = 'UNKNOWN';
    case Failed = 'FAILED';
    case Skipped = 'SKIPPED';
    case Cancelled = 'CANCELLED';

    /** @return string[] */
    public static function terminal(): array
    {
        return [self::Sent->value, self::Accepted->value, self::Delivered->value, self::Failed->value, self::Skipped->value, self::Cancelled->value, self::Unknown->value];
    }

    public function isTerminal(): bool
    {
        return in_array($this->value, self::terminal(), true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu',
            self::Generating => 'Membuat PDF',
            self::Ready => 'Siap',
            self::Scheduled => 'Terjadwal',
            self::Processing => 'Diproses',
            self::Accepted => 'Diterima provider',
            self::Sent => 'Terkirim',
            self::Delivered => 'Terkonfirmasi diterima',
            self::Unknown => 'Tidak diketahui',
            self::Failed => 'Gagal',
            self::Skipped => 'Dilewati',
            self::Cancelled => 'Dibatalkan',
        };
    }
}
