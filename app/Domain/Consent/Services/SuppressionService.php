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

    /**
     * Batch version of isSuppressed(). Returns the set of suppressed
     * normalized addresses for the given channel.
     *
     * @param  string[]  $addresses
     * @return array<string, true>
     */
    public function suppressedAddresses(Channel $channel, array $addresses): array
    {
        $normalized = [];
        foreach ($addresses as $address) {
            if ($address === null || trim((string) $address) === '') {
                continue;
            }
            $normalized[$this->normalize($channel, (string) $address)] = true;
        }

        if ($normalized === []) {
            return [];
        }

        $rows = SuppressionEntry::query()
            ->where('channel', $channel)
            ->whereIn('normalized_address', array_keys($normalized))
            ->pluck('normalized_address');

        $result = [];
        foreach ($rows as $row) {
            $result[(string) $row] = true;
        }

        return $result;
    }

    public function normalize(Channel $channel, string $address): string
    {
        return match ($channel) {
            Channel::Email => trim(mb_strtolower($address)),
            Channel::WhatsApp => PhoneNormalizer::toE164($address) ?? trim($address),
        };
    }
}
