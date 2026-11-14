<?php

namespace App\Jobs;

use App\Domain\Campaigns\Actions\SendProposalDeliveryAction;
use App\Domain\Campaigns\Enums\DeliveryStatus;
use App\Domain\Campaigns\Models\CampaignRecipient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

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

    public function handle(SendProposalDeliveryAction $action): void
    {
        $recipient = CampaignRecipient::find($this->recipientId);

        if ($recipient === null) {
            return;
        }

        if ($recipient->delivery_status === DeliveryStatus::Cancelled) {
            return;
        }

        if ($recipient->scheduled_at !== null && $recipient->scheduled_at->isFuture()) {
            return;
        }

        $result = $action->handle($recipient);

        if (! $result->success && in_array($result->reason, ['gmail_rate_limited', 'evolution_rate_limited'], true) && $this->attempts() < $this->tries) {
            $recipient->refresh();
            if ($recipient->delivery_status === DeliveryStatus::Scheduled) {
                $this->release($this->backoff[min($this->attempts() - 1, count($this->backoff) - 1)]);
            }
        }
    }

    public function failed(?\Throwable $exception): void
    {
        $recipient = CampaignRecipient::find($this->recipientId);

        if ($recipient === null || $recipient->delivery_status === DeliveryStatus::Cancelled) {
            return;
        }

        $recipient->update([
            'delivery_status' => DeliveryStatus::Failed,
            'processed_at' => now(),
            'last_error' => $exception?->getMessage() ?? 'Job pengiriman gagal.',
        ]);
    }
}
