<?php

namespace App\Domain\Campaigns\Enums;

enum EligibilityStatus: string
{
    case Pending = 'pending';
    case Eligible = 'eligible';
    case Ineligible = 'ineligible';
    case Checked = 'checked';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Belum diperiksa',
            self::Eligible => 'Eligible',
            self::Ineligible => 'Tidak eligible',
            self::Checked => 'Diperiksa',
        };
    }
}
