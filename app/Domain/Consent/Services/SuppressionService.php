<?php

namespace App\Domain\Consent\Services;

use App\Domain\Consent\Enums\Channel;
use App\Domain\Consent\Models\SuppressionEntry;
use App\Support\PhoneNormalizer;

final class SuppressionService
{
    public function suppress(Channel $channel, string $address, ?string $reason = null, ?int $createdBy = null): SuppressionEntry
    {
        return SuppressionEntry::firstOrCreate(
            ['channel' => $channel, 'normalized_address' => $this->normalize($channel, $address)],
            ['reason' => $reason, 'created_by' => $createdBy],
        );
    }

    public function isSuppressed(Channel $channel, ?string $address): bool
    {
        if ($address === null || trim($address) === '') {
            return false;
        }

        return SuppressionEntry::query()
            ->where('channel', $channel)
            ->where('normalized_address', $this->normalize($channel, $address))
            ->exists();
    }

    public function normalize(Channel $channel, string $address): string
    {
        return match ($channel) {
            Channel::Email => trim(mb_strtolower($address)),
            Channel::WhatsApp => PhoneNormalizer::toE164($address) ?? trim($address),
        };
    }
}
