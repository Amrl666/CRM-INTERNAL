<?php

namespace App\Domain\Campaigns\Actions;

use App\Domain\Audit\Actions\RecordAuditAction;
use App\Domain\Campaigns\Enums\CampaignStatus;
use App\Domain\Campaigns\Enums\DeliveryStatus;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Models\CampaignRecipient;
use App\Domain\Campaigns\Models\CampaignRecipientChannel;
use App\Domain\Campaigns\Services\RecipientStatusRollup;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class CancelProposalCampaignAction
{
    public function __construct(
        private readonly RecipientStatusRollup $rollup,
        private readonly RecordAuditAction $audit,
    ) {}

    public function handle(
        Campaign $campaign,
        int $userId,
        ?string $reason = null,
    ): Campaign {
        return DB::transaction(function () use ($campaign, $userId, $reason): Campaign {
            $lockedCampaign = Campaign::query()->lockForUpdate()->findOrFail($campaign->id);

            if (in_array($lockedCampaign->status, [CampaignStatus::Completed, CampaignStatus::Cancelled], true)) {
                throw new InvalidArgumentException('Campaign sudah selesai atau dibatalkan.');
            }

            $now = now();
            $message = $reason ?: 'Campaign dibatalkan.';

            $channels = CampaignRecipientChannel::query()
                ->where('campaign_id', $lockedCampaign->id)
                ->whereNotIn('delivery_status', DeliveryStatus::terminal())
                ->where('delivery_status', '!=', DeliveryStatus::Processing->value)
                ->lockForUpdate()
                ->get();

            $recipientIds = [];
            foreach ($channels as $channel) {
                $channel->update([
                    'delivery_status' => DeliveryStatus::Cancelled,
                    'cancelled_at' => $now,
                    'skip_reason' => $message,
                    'updated_at' => $now,
                ]);
                $recipientIds[$channel->campaign_recipient_id] = true;
            }

            foreach (array_keys($recipientIds) as $recipientId) {
                $recipient = CampaignRecipient::find($recipientId);
                if ($recipient !== null) {
                    $this->rollup->refresh($recipient);
                }
            }

            // Legacy campaigns without channel rows.
            $lockedCampaign->recipients()
                ->whereNotIn('delivery_status', DeliveryStatus::terminal())
                ->where('delivery_status', '!=', DeliveryStatus::Processing->value)
                ->update([
                    'delivery_status' => DeliveryStatus::Cancelled->value,
                    'cancelled_at' => $now,
                    'skip_reason' => $message,
                    'updated_at' => $now,
                ]);

            $lockedCampaign->update([
                'status' => CampaignStatus::Cancelled,
                'cancelled_at' => $now,
                'last_error' => $reason,
            ]);

            $this->audit->handle(
                user: $userId,
                action: 'proposal_campaign.cancelled',
                subject: $lockedCampaign,
                after: [
                    'reason' => $reason,
                    'cancelled_channels' => $channels->count(),
                    'in_flight_channels' => CampaignRecipientChannel::query()
                        ->where('campaign_id', $lockedCampaign->id)
                        ->where('delivery_status', DeliveryStatus::Processing->value)
                        ->count(),
                ],
            );

            return $lockedCampaign->refresh();
        });
    }
}
