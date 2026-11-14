<?php

namespace App\Domain\Campaigns\Actions;

use App\Domain\Audit\Actions\RecordAuditAction;
use App\Domain\Campaigns\Enums\CampaignStatus;
use App\Domain\Campaigns\Enums\DeliveryStatus;
use App\Domain\Campaigns\Enums\EligibilityStatus;
use App\Domain\Campaigns\Enums\PdfStatus;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Models\CampaignRecipient;
use App\Domain\Campaigns\Services\BusinessSchedule;
use App\Jobs\SendProposalDeliveryJob;

final class ScheduleProposalCampaignAction
{
    public function __construct(
        private readonly BusinessSchedule $schedule,
        private readonly RecordAuditAction $audit,
    ) {}

    public function handle(Campaign $campaign, ?int $userId = null): Campaign
    {
        if ($campaign->status !== CampaignStatus::Approved) {
            throw new \InvalidArgumentException('Campaign harus disetujui sebelum dijadwalkan.');
        }

        if (! $campaign->isApprovedForCurrentRevision()) {
            throw new \InvalidArgumentException('Approval campaign sudah tidak berlaku untuk revisi ini.');
        }

        $recipients = $campaign->recipients()
            ->where('eligibility_status', EligibilityStatus::Eligible)
            ->where('pdf_status', PdfStatus::Ready)
            ->whereNotIn('delivery_status', [
                DeliveryStatus::Sent, DeliveryStatus::Accepted, DeliveryStatus::Delivered,
                DeliveryStatus::Failed, DeliveryStatus::Skipped, DeliveryStatus::Cancelled,
            ])
            ->orderBy('created_at')
            ->get();

        if ($recipients->isEmpty()) {
            throw new \InvalidArgumentException('Tidak ada penerima eligible dengan PDF siap untuk dijadwalkan.');
        }

        $slots = $this->schedule->plan($campaign, $recipients->pluck('id')->all());

        /** @var CampaignRecipient $recipient */
        foreach ($recipients as $recipient) {
            $scheduledAt = $slots[$recipient->id] ?? null;

            if ($scheduledAt === null) {
                continue;
            }

            $recipient->update([
                'scheduled_at' => $scheduledAt,
                'delivery_status' => DeliveryStatus::Scheduled,
                'skip_reason' => null,
                'last_error' => null,
            ]);
        }

        $campaign->update([
            'status' => CampaignStatus::Scheduled,
            'last_error' => null,
        ]);

        $campaign->recipients()
            ->where('delivery_status', DeliveryStatus::Scheduled)
            ->each(fn (CampaignRecipient $recipient) => SendProposalDeliveryJob::dispatch($recipient->id)
                ->delay($recipient->scheduled_at)
                ->afterCommit());

        $this->audit->handle(user: $userId, action: 'proposal_campaign.scheduled', subject: $campaign, after: [
            'scheduled' => $recipients->count(),
            'first_delivery_at' => $campaign->recipients()->min('scheduled_at'),
        ]);

        return $campaign;
    }
}
