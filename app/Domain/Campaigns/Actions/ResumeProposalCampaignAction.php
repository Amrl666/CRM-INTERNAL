<?php

namespace App\Domain\Campaigns\Actions;

use App\Domain\Audit\Actions\RecordAuditAction;
use App\Domain\Campaigns\Enums\CampaignStatus;
use App\Domain\Campaigns\Enums\DeliveryStatus;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Services\BusinessSchedule;
use App\Jobs\SendProposalDeliveryJob;

final class ResumeProposalCampaignAction
{
    public function __construct(
        private readonly BusinessSchedule $schedule,
        private readonly RecordAuditAction $audit,
    ) {}

    public function handle(Campaign $campaign, int $userId): Campaign
    {
        if ($campaign->status !== CampaignStatus::Paused) {
            throw new \InvalidArgumentException('Campaign tidak sedang dijeda.');
        }

        if (! $campaign->isApprovedForCurrentRevision()) {
            throw new \InvalidArgumentException('Approval campaign tidak berlaku. Ajukan approval ulang.');
        }

        $recipients = $campaign->recipients()
            ->whereIn('delivery_status', [DeliveryStatus::Scheduled, DeliveryStatus::Pending, DeliveryStatus::Ready])
            ->orderBy('created_at')
            ->get();

        $slots = $this->schedule->plan($campaign, $recipients->pluck('id')->all());

        foreach ($recipients as $recipient) {
            $recipient->update([
                'scheduled_at' => $slots[$recipient->id] ?? null,
                'delivery_status' => DeliveryStatus::Scheduled,
            ]);
        }

        $campaign->update([
            'status' => CampaignStatus::Scheduled,
            'paused_at' => null,
        ]);

        $recipients->each(fn ($recipient) => SendProposalDeliveryJob::dispatch($recipient->id)
            ->delay($recipient->scheduled_at)
            ->afterCommit());

        $this->audit->handle(user: $userId, action: 'proposal_campaign.resumed', subject: $campaign, after: [
            'rescheduled' => $recipients->count(),
        ]);

        return $campaign;
    }
}
