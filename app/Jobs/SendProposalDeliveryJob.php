<?php

namespace App\Jobs;

use App\Domain\Campaigns\Actions\SendProposalDeliveryAction;
use App\Domain\Campaigns\Enums\CampaignStatus;
use App\Domain\Campaigns\Enums\DeliveryStatus;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Models\CampaignRecipient;
use App\Domain\Campaigns\Services\BusinessSchedule;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class SendProposalDeliveryJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 90;

    /** @var int[] */
    public array $backoff = [60, 300];

    public function __construct(public readonly string $recipientId)
    {
        $this->onQueue('communications');
    }

    public function handle(
        SendProposalDeliveryAction $action,
        BusinessSchedule $schedule,
    ): void {
        $recipient = CampaignRecipient::with('campaign')
            ->find($this->recipientId);

        if ($recipient === null) {
            return;
        }

        if (in_array($recipient->delivery_status, [
            DeliveryStatus::Cancelled,
            DeliveryStatus::Sent,
            DeliveryStatus::Accepted,
            DeliveryStatus::Delivered,
            DeliveryStatus::Failed,
            DeliveryStatus::Skipped,
        ], true)) {
            return;
        }

        $campaign = $recipient->campaign;

        // Periksa status campaign SEBELUM memeriksa jadwal.
        if (! in_array($campaign->status, [
            CampaignStatus::Scheduled,
            CampaignStatus::Running,
        ], true)) {
            return;
        }

        if ($recipient->scheduled_at !== null
            && $recipient->scheduled_at->isFuture()) {
            self::dispatch($this->recipientId)
                ->onQueue('communications')
                ->delay($recipient->scheduled_at);

            return;
        }

        $result = $action->handle($recipient);

        $rescheduleReasons = [
            'rescheduled',
            'outside_business_window',
            'gmail_rate_limited',
            'evolution_rate_limited',
        ];

        if (! in_array($result->reason, $rescheduleReasons, true)) {
            return;
        }

        $recipient->refresh();

        if ($recipient->delivery_status !== DeliveryStatus::Scheduled) {
            return;
        }

        // Berikan jeda tambahan ketika provider membatasi pengiriman.
        $retryAt = now();

        if (in_array($result->reason, [
            'gmail_rate_limited',
            'evolution_rate_limited',
        ], true)) {
            $backoffIndex = min(
                max(((int) $recipient->attempts) - 1, 0),
                count($this->backoff) - 1,
            );

            $retryAt = $retryAt->addSeconds(
                $this->backoff[$backoffIndex],
            );
        }

        DB::transaction(function () use (
            $schedule,
            $retryAt,
        ): void {
            $campaign = Campaign::query()
                ->lockForUpdate()
                ->find($this->getCampaignId());

            if ($campaign === null || ! in_array($campaign->status, [
                CampaignStatus::Scheduled,
                CampaignStatus::Running,
            ], true)) {
                return;
            }

            $recipient = CampaignRecipient::query()
                ->lockForUpdate()
                ->find($this->recipientId);

            if ($recipient === null
                || $recipient->delivery_status !== DeliveryStatus::Scheduled) {
                return;
            }

            $slot = $schedule->plan(
                $campaign,
                [$recipient->id],
                $retryAt,
            )[$recipient->id] ?? null;

            if ($slot === null) {
                throw new \RuntimeException(
                    'Tidak berhasil menemukan jadwal pengiriman berikutnya.'
                );
            }

            $recipient->update([
                'scheduled_at' => $slot,
            ]);

            self::dispatch($recipient->id)
                ->onQueue('communications')
                ->delay($slot)
                ->afterCommit();
        });
    }

    private function getCampaignId(): ?string
    {
        return CampaignRecipient::query()
            ->whereKey($this->recipientId)
            ->value('campaign_id');
    }

    public function failed(?\Throwable $exception): void
    {
        DB::transaction(function () use ($exception): void {
            $recipient = CampaignRecipient::query()
                ->lockForUpdate()
                ->find($this->recipientId);

            if ($recipient === null || in_array($recipient->delivery_status, [
                DeliveryStatus::Cancelled,
                DeliveryStatus::Sent,
                DeliveryStatus::Accepted,
                DeliveryStatus::Delivered,
            ], true)) {
                return;
            }

            $recipient->update([
                'delivery_status' => DeliveryStatus::Failed,
                'processed_at' => now(),
                'last_error' => $exception?->getMessage()
                    ?? 'Job pengiriman gagal.',
            ]);
        });
    }
}
