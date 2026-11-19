<?php

namespace App\Domain\Campaigns\Actions;

use App\Domain\Audit\Actions\RecordAuditAction;
use App\Domain\Campaigns\Enums\CampaignStatus;
use App\Domain\Campaigns\Enums\DeliveryStatus;
use App\Domain\Campaigns\Models\Campaign;
use Illuminate\Support\Facades\DB;

final class CancelProposalCampaignAction
{
    public function __construct(
        private readonly RecordAuditAction $audit,
    ) {}

    public function handle(
        Campaign $campaign,
        int $userId,
        ?string $reason = null,
    ): Campaign {
        return DB::transaction(function () use (
            $campaign,
            $userId,
            $reason,
        ): Campaign {
            $lockedCampaign = Campaign::query()
                ->lockForUpdate()
                ->findOrFail($campaign->id);

            if (in_array($lockedCampaign->status, [
                CampaignStatus::Completed,
                CampaignStatus::Cancelled,
            ], true)) {
                throw new \InvalidArgumentException(
                    'Campaign sudah selesai atau dibatalkan.'
                );
            }

            $now = now();

            // Lock recipient yang belum terminal dan belum dalam proses kirim.
            $recipients = $lockedCampaign->recipients()
                ->whereNotIn(
                    'delivery_status',
                    DeliveryStatus::terminal(),
                )
                ->where(
                    'delivery_status',
                    '!=',
                    DeliveryStatus::Processing->value,
                )
                ->lockForUpdate()
                ->get();

            foreach ($recipients as $recipient) {
                $recipient->update([
                    'delivery_status' => DeliveryStatus::Cancelled,
                    'cancelled_at' => $now,
                    'skip_reason' => $reason ?: 'Campaign dibatalkan.',
                    'updated_at' => $now,
                ]);
            }

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
                    'cancelled_recipients' => $recipients->count(),
                    'in_flight_recipients' => $lockedCampaign->recipients()
                        ->where(
                            'delivery_status',
                            DeliveryStatus::Processing->value,
                        )
                        ->count(),
                ],
            );

            return $lockedCampaign->refresh();
        });
    }
}
