<?php

namespace App\Domain\Campaigns\Actions;

use App\Domain\Audit\Actions\RecordAuditAction;
use App\Domain\Campaigns\Enums\CampaignStatus;
use App\Domain\Campaigns\Enums\DeliveryStatus;
use App\Domain\Campaigns\Enums\EligibilityStatus;
use App\Domain\Campaigns\Enums\PdfStatus;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Models\CampaignRecipientChannel;
use App\Domain\Campaigns\Services\BusinessSchedule;
use App\Jobs\SendProposalChannelDeliveryJob;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class FinalizeProposalCampaignPreparationAction
{
    public function __construct(
        private readonly BusinessSchedule $schedule,
        private readonly RecordAuditAction $audit,
    ) {}

    public function handle(Campaign $campaign, ?int $userId = null): Campaign
    {
        return DB::transaction(function () use ($campaign, $userId): Campaign {
            $locked = Campaign::query()->lockForUpdate()->findOrFail($campaign->id);

            if (! in_array($locked->status, [CampaignStatus::Preparing, CampaignStatus::Scheduled], true)) {
                return $locked;
            }

            if ($locked->status === CampaignStatus::Scheduled) {
                return $locked;
            }

            // Finalization must never run ahead of audience resolution. A
            // queued finalize job can overtake the chunked resolver, and a
            // direct call must not mark an unresolved campaign as failed.
            if ($locked->audience_resolved_at === null) {
                return $locked;
            }

            if (! $locked->isApprovedForCurrentRevision()) {
                throw new InvalidArgumentException('Approval campaign tidak berlaku saat finalisasi.');
            }

            $pendingPdf = $locked->recipients()
                ->whereIn('pdf_status', [PdfStatus::Pending, PdfStatus::Generating])
                ->count();

            if ($pendingPdf > 0) {
                return $locked;
            }

            $totalRecipients = $locked->recipients()->count();

            $channels = CampaignRecipientChannel::query()
                ->join('campaign_recipients as cr', 'cr.id', '=', 'campaign_recipient_channels.campaign_recipient_id')
                ->where('campaign_recipient_channels.campaign_id', $locked->id)
                ->where('campaign_recipient_channels.eligibility_status', EligibilityStatus::Eligible->value)
                ->where('campaign_recipient_channels.delivery_status', DeliveryStatus::Pending->value)
                ->where('cr.pdf_status', PdfStatus::Ready->value)
                ->orderBy('campaign_recipient_channels.created_at')
                ->select('campaign_recipient_channels.*')
                ->lockForUpdate()
                ->get();

            if ($channels->isEmpty()) {
                $locked->update([
                    'status' => CampaignStatus::Failed,
                    'last_error' => $totalRecipients === 0
                        ? 'Tidak ada penerima yang cocok dengan filter audience.'
                        : 'Tidak ada channel eligible dengan PDF siap.',
                    'completed_at' => now(),
                ]);

                $this->audit->handle(user: $userId, action: 'proposal_campaign.preparation_failed', subject: $locked, after: [
                    'recipients' => $totalRecipients,
                ]);

                return $locked->refresh();
            }

            $slots = $this->schedule->planChannelRows(
                $locked,
                $channels->pluck('id')->all(),
            );

            foreach ($channels as $channel) {
                $scheduledAt = $slots[$channel->id] ?? null;
                if ($scheduledAt === null) {
                    continue;
                }

                $channel->update([
                    'scheduled_at' => $scheduledAt,
                    'delivery_status' => DeliveryStatus::Scheduled,
                    'skip_reason' => null,
                    'last_error' => null,
                ]);

                $channel->recipient()->update([
                    'delivery_status' => DeliveryStatus::Scheduled,
                    'scheduled_at' => $scheduledAt,
                ]);

                SendProposalChannelDeliveryJob::dispatch($channel->id)
                    ->onQueue('communications')
                    ->delay($scheduledAt)
                    ->afterCommit();
            }

            $locked->update([
                'status' => CampaignStatus::Scheduled,
                'last_error' => null,
            ]);

            $this->audit->handle(user: $userId, action: 'proposal_campaign.scheduled', subject: $locked, after: [
                'scheduled_channels' => $channels->count(),
                'first_delivery_at' => $channels->min('scheduled_at'),
            ]);

            return $locked->refresh();
        });
    }
}
