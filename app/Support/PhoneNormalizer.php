<?php

namespace App\Support;

use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

final class PhoneNormalizer
{
    public static function toE164(?string $raw, string $defaultRegion = 'ID'): ?string
    {
        $raw = $raw !== null ? trim($raw) : null;

        if ($raw === null || $raw === '') {
            return null;
        }

        try {
            $util = PhoneNumberUtil::getInstance();
            $number = $util->parse($raw, $defaultRegion);

            if (! $util->isValidNumber($number)) {
                return null;
            }

            return $util->format($number, PhoneNumberFormat::E164);
        } catch (\Throwable) {
            return null;
        }
    }
}
