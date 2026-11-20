<?php

namespace App\Domain\Campaigns\Services;

use App\Domain\Campaigns\Enums\DeliveryStatus;
use App\Domain\Consent\Enums\Channel;
use Illuminate\Support\Facades\DB;

/**
 * Source of truth for duplicate outreach prevention.
 *
 * Successful, accepted, delivered and UNKNOWN deliveries are hard-excluded.
 * FAILED / SKIPPED / CANCELLED are re-evaluable in a new campaign.
 */
final class PriorOutreachGuard
{
    private const BLOCKING = [
        DeliveryStatus::Sent,
        DeliveryStatus::Accepted,
        DeliveryStatus::Delivered,
        DeliveryStatus::Unknown,
    ];

    /** @return string[] */
    public static function blockingStatuses(): array
    {
        return array_map(static fn (DeliveryStatus $status): string => $status->value, self::BLOCKING);
    }

    /**
     * @param  string[]  $contactIds
     * @return array<string, string> contact_id => reason
     */
    public function blockedContacts(
        array $contactIds,
        Channel $channel,
        ?string $exceptCampaignId = null,
    ): array {
        if ($contactIds === []) {
            return [];
        }

        $query = DB::table('campaign_recipient_channels as crc')
            ->select('crc.contact_id', 'crc.delivery_status')
            ->whereIn('crc.contact_id', $contactIds)
            ->where('crc.channel', $channel->value)
            ->whereIn('crc.delivery_status', self::blockingStatuses());

        if ($exceptCampaignId !== null) {
            $query->where('crc.campaign_id', '!=', $exceptCampaignId);
        }

        $reasons = [];
        foreach ($query->get() as $row) {
            $status = DeliveryStatus::tryFrom((string) $row->delivery_status);
            $reasons[(string) $row->contact_id] = $status === DeliveryStatus::Unknown
                ? 'Sudah memiliki pengiriman dengan status tidak diketahui pada channel ini; tidak dikirim ulang otomatis.'
                : 'Sudah pernah menerima outreach pada channel ini.';
        }

        return $reasons;
    }

    public function hasPriorDelivery(string $contactId, Channel $channel, ?string $exceptCampaignId = null): bool
    {
        return $this->blockedContacts([$contactId], $channel, $exceptCampaignId) !== [];
    }
}
