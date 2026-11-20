<?php

namespace App\Domain\Campaigns\Services;

use App\Domain\Campaigns\Enums\DeliveryStatus;
use App\Domain\Campaigns\Models\CampaignRecipient;

/**
 * Mirrors per-channel state into the legacy recipient-level status field.
 * Channel rows remain the source of truth for proposal campaigns.
 */
final class RecipientStatusRollup
{
    public function refresh(CampaignRecipient $recipient): CampaignRecipient
    {
        $recipient->loadMissing('channels');
        $channels = $recipient->channels;

        if ($channels->isEmpty()) {
            return $recipient;
        }

        $statuses = $channels->pluck('delivery_status')->filter()->values();

        if ($statuses->contains(DeliveryStatus::Processing)) {
            $status = DeliveryStatus::Processing;
        } elseif ($statuses->contains(DeliveryStatus::Scheduled)) {
            $status = DeliveryStatus::Scheduled;
        } elseif ($statuses->contains(DeliveryStatus::Pending)) {
            $status = DeliveryStatus::Pending;
        } else {
            $status = $this->terminalStatus($statuses->all());
        }

        $recipient->update([
            'delivery_status' => $status,
            'processed_at' => $status->isTerminal() ? now() : null,
        ]);

        return $recipient->refresh();
    }

    /** @param array<int, DeliveryStatus> $statuses */
    private function terminalStatus(array $statuses): DeliveryStatus
    {
        $success = array_values(array_filter(
            $statuses,
            static fn (DeliveryStatus $s): bool => in_array($s, [
                DeliveryStatus::Sent, DeliveryStatus::Accepted, DeliveryStatus::Delivered,
            ], true),
        ));

        $hasFailure = count($success) !== count($statuses);

        if ($success !== [] && $hasFailure) {
            return DeliveryStatus::Partial;
        }

        if ($success !== []) {
            return in_array(DeliveryStatus::Delivered, $statuses, true)
                ? DeliveryStatus::Delivered
                : DeliveryStatus::Sent;
        }

        if (in_array(DeliveryStatus::Unknown, $statuses, true)) {
            return DeliveryStatus::Unknown;
        }

        if ($statuses !== [] && count(array_unique(array_map(static fn (DeliveryStatus $s): string => $s->value, $statuses))) === 1 && $statuses[0] === DeliveryStatus::Skipped) {
            return DeliveryStatus::Skipped;
        }

        if ($statuses !== [] && count(array_unique(array_map(static fn (DeliveryStatus $s): string => $s->value, $statuses))) === 1 && $statuses[0] === DeliveryStatus::Cancelled) {
            return DeliveryStatus::Cancelled;
        }

        return DeliveryStatus::Failed;
    }
}
