<?php

namespace App\Domain\Campaigns\Enums;

enum PdfStatus: string
{
    case Pending = 'PENDING';
    case Generating = 'GENERATING';
    case Ready = 'READY';
    case Failed = 'FAILED';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu',
            self::Generating => 'Membuat PDF',
            self::Ready => 'Siap',
            self::Failed => 'Gagal',
        };
    }
}
