<?php

namespace App\Domain\Campaigns\Actions;

use App\Domain\Audit\Actions\RecordAuditAction;
use App\Domain\Campaigns\Enums\CampaignStatus;
use App\Domain\Campaigns\Enums\DeliveryStatus;
use App\Domain\Campaigns\Enums\EligibilityStatus;
use App\Domain\Campaigns\Enums\PdfStatus;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Services\BusinessSchedule;
use App\Jobs\SendProposalDeliveryJob;
use Illuminate\Support\Facades\DB;

final class ScheduleProposalCampaignAction
{
    public function __construct(
        private readonly BusinessSchedule $schedule,
        private readonly RecordAuditAction $audit,
    ) {}

    public function handle(Campaign $campaign, ?int $userId = null): Campaign
    {
        return DB::transaction(function () use ($campaign, $userId): Campaign {
            $lockedCampaign = Campaign::query()
                ->lockForUpdate()
                ->findOrFail($campaign->id);

            if ($lockedCampaign->status !== CampaignStatus::Approved) {
                throw new \InvalidArgumentException(
                    'Campaign harus disetujui sebelum dijadwalkan.'
                );
            }

            if (! $lockedCampaign->isApprovedForCurrentRevision()) {
                throw new \InvalidArgumentException(
                    'Approval campaign sudah tidak berlaku untuk revisi ini.'
                );
            }

            $recipients = $lockedCampaign->recipients()
                ->where('eligibility_status', EligibilityStatus::Eligible)
                ->where('pdf_status', PdfStatus::Ready)
                ->whereNotIn('delivery_status', [
                    ...DeliveryStatus::terminal(),
                    DeliveryStatus::Processing,
                ])
                ->orderBy('created_at')
                ->lockForUpdate()
                ->get();

            if ($recipients->isEmpty()) {
                throw new \InvalidArgumentException(
                    'Tidak ada penerima eligible dengan PDF siap untuk dijadwalkan.'
                );
            }

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
                    'skip_reason' => null,
                    'last_error' => null,
                ]);

                $scheduledRecipients->push($recipient);
            }

            $lockedCampaign->update([
                'status' => CampaignStatus::Scheduled,
                'last_error' => null,
            ]);

            foreach ($scheduledRecipients as $recipient) {
                SendProposalDeliveryJob::dispatch($recipient->id)
                    ->delay($recipient->scheduled_at)
                    ->afterCommit();
            }

            $this->audit->handle(
                user: $userId,
                action: 'proposal_campaign.scheduled',
                subject: $lockedCampaign,
                after: [
                    'scheduled' => $scheduledRecipients->count(),
                    'first_delivery_at' => $scheduledRecipients
                        ->min('scheduled_at'),
                ],
            );

            return $lockedCampaign->refresh();
        });
    }
}
