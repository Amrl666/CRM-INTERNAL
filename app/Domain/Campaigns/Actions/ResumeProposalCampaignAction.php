<?php

namespace App\Domain\Campaigns\Actions;

use App\Domain\Audit\Actions\RecordAuditAction;
use App\Domain\Campaigns\Enums\CampaignStatus;
use App\Domain\Campaigns\Enums\DeliveryStatus;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Models\CampaignRecipientChannel;
use App\Domain\Campaigns\Services\BusinessSchedule;
use App\Jobs\SendProposalChannelDeliveryJob;
use App\Jobs\SendProposalDeliveryJob;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class ResumeProposalCampaignAction
{
    public function __construct(
        private readonly BusinessSchedule $schedule,
        private readonly RecordAuditAction $audit,
    ) {}

    public function handle(Campaign $campaign, int $userId): Campaign
    {
        return DB::transaction(function () use ($campaign, $userId): Campaign {
            $lockedCampaign = Campaign::query()->lockForUpdate()->findOrFail($campaign->id);

            if ($lockedCampaign->status !== CampaignStatus::Paused) {
                throw new InvalidArgumentException('Campaign tidak sedang dijeda.');
            }

            if (! $lockedCampaign->isApprovedForCurrentRevision()) {
                throw new InvalidArgumentException('Approval campaign tidak berlaku. Ajukan approval ulang.');
            }

            $query = CampaignRecipientChannel::query()
                ->where('campaign_id', $lockedCampaign->id)
                ->whereIn('delivery_status', [
                    DeliveryStatus::Scheduled, DeliveryStatus::Pending, DeliveryStatus::Ready,
                ]);

            $channels = (clone $query)->orderBy('created_at')->lockForUpdate()->get();

            if ($channels->isEmpty()) {
                $channels = $lockedCampaign->recipients()
                    ->whereIn('delivery_status', [
                        DeliveryStatus::Scheduled, DeliveryStatus::Pending, DeliveryStatus::Ready,
                    ])
                    ->orderBy('created_at')->lockForUpdate()->get();
            }

            $slots = $channels instanceof Collection
                && $channels->first() instanceof CampaignRecipientChannel
                ? $this->schedule->planChannelRows($lockedCampaign, $channels->pluck('id')->all())
                : $this->schedule->plan($lockedCampaign, $channels->pluck('id')->all());

            foreach ($channels as $channel) {
                $scheduledAt = $slots[(string) $channel->id] ?? null;
                if ($scheduledAt === null) {
                    continue;
                }

                $channel->update([
                    'scheduled_at' => $scheduledAt,
                    'delivery_status' => DeliveryStatus::Scheduled,
                ]);

                if ($channel instanceof CampaignRecipientChannel) {
                    SendProposalChannelDeliveryJob::dispatch($channel->id)
                        ->onQueue('communications')
                        ->delay($scheduledAt)
                        ->afterCommit();
                } else {
                    SendProposalDeliveryJob::dispatch($channel->id)
                        ->onQueue('communications')
                        ->delay($scheduledAt)
                        ->afterCommit();
                }
            }

            $lockedCampaign->update([
                'status' => CampaignStatus::Scheduled,
                'paused_at' => null,
            ]);

            $this->audit->handle(
                user: $userId,
                action: 'proposal_campaign.resumed',
                subject: $lockedCampaign,
                after: ['rescheduled' => $channels->count()],
            );

            return $lockedCampaign->refresh();
        });
    }
}
