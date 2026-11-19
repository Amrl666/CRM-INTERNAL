<?php

namespace App\Domain\Campaigns\Actions;

use App\Domain\Audit\Actions\RecordAuditAction;
use App\Domain\Campaigns\Enums\CampaignStatus;
use App\Domain\Campaigns\Enums\DeliveryStatus;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Services\BusinessSchedule;
use App\Jobs\SendProposalDeliveryJob;
use Illuminate\Support\Facades\DB;

final class ResumeProposalCampaignAction
{
    public function __construct(
        private readonly BusinessSchedule $schedule,
        private readonly RecordAuditAction $audit,
    ) {}

    public function handle(Campaign $campaign, int $userId): Campaign
    {
        return DB::transaction(function () use ($campaign, $userId): Campaign {
            $lockedCampaign = Campaign::query()
                ->lockForUpdate()
                ->findOrFail($campaign->id);

            if ($lockedCampaign->status !== CampaignStatus::Paused) {
                throw new \InvalidArgumentException(
                    'Campaign tidak sedang dijeda.'
                );
            }

            if (! $lockedCampaign->isApprovedForCurrentRevision()) {
                throw new \InvalidArgumentException(
                    'Approval campaign tidak berlaku. Ajukan approval ulang.'
                );
            }

            $recipients = $lockedCampaign->recipients()
                ->whereIn('delivery_status', [
                    DeliveryStatus::Scheduled,
                    DeliveryStatus::Pending,
                    DeliveryStatus::Ready,
                ])
                ->orderBy('created_at')
                ->lockForUpdate()
                ->get();

            $slots = $this->schedule->plan(
                $lockedCampaign,
                $recipients->pluck('id')->all(),
            );

            $scheduledRecipients = collect();

            foreach ($recipients as $recipient) {
                $scheduledAt = $slots[$recipient->id] ?? null;

                if ($scheduledAt === null) {
                    continue;
                }

                $recipient->update([
                    'scheduled_at' => $scheduledAt,
                    'delivery_status' => DeliveryStatus::Scheduled,
                ]);

                $scheduledRecipients->push($recipient);
            }

            $lockedCampaign->update([
                'status' => CampaignStatus::Scheduled,
                'paused_at' => null,
            ]);

            foreach ($scheduledRecipients as $recipient) {
                SendProposalDeliveryJob::dispatch($recipient->id)
                    ->delay($recipient->scheduled_at)
                    ->afterCommit();
            }

            $this->audit->handle(
                user: $userId,
                action: 'proposal_campaign.resumed',
                subject: $lockedCampaign,
                after: [
                    'rescheduled' => $scheduledRecipients->count(),
                ],
            );

            return $lockedCampaign->refresh();
        });
    }
}
