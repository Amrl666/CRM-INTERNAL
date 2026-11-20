<?php

namespace App\Jobs;

use App\Domain\Campaigns\Enums\CampaignStatus;
use App\Domain\Campaigns\Enums\DeliveryStatus;
use App\Domain\Campaigns\Enums\PdfStatus;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Models\CampaignRecipientChannel;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ReconcileProposalDeliveriesJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 60;

    public function __construct()
    {
        $this->onQueue('communications');
    }

    public function handle(): void
    {
        CampaignRecipientChannel::query()
            ->where('delivery_status', DeliveryStatus::Processing)
            ->where('updated_at', '<', now()->subMinutes(10))
            ->update([
                'delivery_status' => DeliveryStatus::Unknown->value,
                'processed_at' => now(),
                'last_error' => 'Status tidak dapat dipastikan setelah timeout; tidak dikirim ulang otomatis.',
                'updated_at' => now(),
            ]);

        Campaign::query()
            ->whereIn('status', [
                CampaignStatus::Running,
                CampaignStatus::Scheduled,
                CampaignStatus::Preparing,
            ])
            ->each(function (Campaign $campaign): void {
                if ($campaign->status === CampaignStatus::Preparing) {
                    if (
                        $campaign->audience_resolved_at !== null
                        && $campaign->recipients()
                            ->whereIn('pdf_status', [PdfStatus::Pending, PdfStatus::Generating])
                            ->doesntExist()
                    ) {
                        FinalizeProposalCampaignPreparationJob::dispatch($campaign->id)
                            ->onQueue('campaigns');
                    }

                    return;
                }

                $hasChannelRows = $campaign->recipientChannels()->exists();

                $remaining = $hasChannelRows
                    ? $campaign->recipientChannels()
                        ->whereNotIn('delivery_status', DeliveryStatus::terminal())
                        ->count()
                    : $campaign->recipients()
                        ->whereNotIn('delivery_status', DeliveryStatus::terminal())
                        ->count();

                if ($remaining === 0) {
                    $campaign->update([
                        'status' => CampaignStatus::Completed,
                        'completed_at' => now(),
                    ]);
                }
            });
    }
}
