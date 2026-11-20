<?php

namespace App\Jobs;

use App\Domain\Campaigns\Actions\SendProposalChannelDeliveryAction;
use App\Domain\Campaigns\Enums\CampaignStatus;
use App\Domain\Campaigns\Enums\DeliveryStatus;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Models\CampaignRecipientChannel;
use App\Domain\Campaigns\Services\BusinessSchedule;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class SendProposalChannelDeliveryJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 90;

    /** @var int[] */
    public array $backoff = [60, 300];

    public function __construct(public readonly string $channelRowId)
    {
        $this->onQueue('communications');
    }

    public function handle(
        SendProposalChannelDeliveryAction $action,
        BusinessSchedule $schedule,
    ): void {
        $channelRow = CampaignRecipientChannel::with('campaign')->find($this->channelRowId);

        if ($channelRow === null || $channelRow->delivery_status?->isTerminal()) {
            return;
        }

        $campaign = $channelRow->campaign;

        if (! in_array($campaign->status, [CampaignStatus::Scheduled, CampaignStatus::Running], true)) {
            return;
        }

        if ($channelRow->scheduled_at !== null && $channelRow->scheduled_at->isFuture()) {
            self::dispatch($this->channelRowId)
                ->onQueue('communications')
                ->delay($channelRow->scheduled_at);

            return;
        }

        $result = $action->handle($channelRow);

        if (! in_array($result->reason, [
            'rescheduled', 'outside_business_window',
            'gmail_rate_limited', 'brevo_rate_limited', 'evolution_rate_limited',
        ], true)) {
            return;
        }

        $channelRow->refresh();

        if ($channelRow->delivery_status !== DeliveryStatus::Scheduled) {
            return;
        }

        $retryAt = now();

        if (in_array($result->reason, ['gmail_rate_limited', 'brevo_rate_limited', 'evolution_rate_limited'], true)) {
            $backoffIndex = min(max($channelRow->attempts - 1, 0), count($this->backoff) - 1);
            $retryAt = $retryAt->addSeconds($this->backoff[$backoffIndex]);
        }

        DB::transaction(function () use ($schedule, $retryAt): void {
            $campaign = Campaign::query()
                ->lockForUpdate()
                ->find($this->getCampaignId());

            if ($campaign === null || ! in_array($campaign->status, [
                CampaignStatus::Scheduled, CampaignStatus::Running,
            ], true)) {
                return;
            }

            $channelRow = CampaignRecipientChannel::query()
                ->lockForUpdate()
                ->find($this->channelRowId);

            if ($channelRow === null || $channelRow->delivery_status !== DeliveryStatus::Scheduled) {
                return;
            }

            $slot = $schedule->planChannelRows($campaign, [$channelRow->id], $retryAt)[$channelRow->id] ?? null;

            if ($slot === null) {
                throw new \RuntimeException('Tidak berhasil menemukan jadwal pengiriman berikutnya.');
            }

            $channelRow->update(['scheduled_at' => $slot]);

            self::dispatch($channelRow->id)
                ->onQueue('communications')
                ->delay($slot)
                ->afterCommit();
        });
    }

    private function getCampaignId(): ?string
    {
        return CampaignRecipientChannel::query()
            ->whereKey($this->channelRowId)
            ->value('campaign_id');
    }

    public function failed(?\Throwable $exception): void
    {
        DB::transaction(function () use ($exception): void {
            $channelRow = CampaignRecipientChannel::query()
                ->lockForUpdate()
                ->find($this->channelRowId);

            if ($channelRow === null || in_array($channelRow->delivery_status, [
                DeliveryStatus::Cancelled, DeliveryStatus::Sent,
                DeliveryStatus::Accepted, DeliveryStatus::Delivered,
            ], true)) {
                return;
            }

            $channelRow->update([
                'delivery_status' => DeliveryStatus::Failed,
                'processed_at' => now(),
                'last_error' => $exception?->getMessage() ?? 'Job pengiriman gagal.',
            ]);
        });
    }
}
