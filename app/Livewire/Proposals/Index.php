<?php

namespace App\Livewire\Proposals;

use App\Domain\Campaigns\Enums\CampaignStatus;
use App\Domain\Campaigns\Enums\DeliveryStatus;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Models\CampaignRecipient;
use App\Domain\Campaigns\Models\CampaignRecipientChannel;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.sidebar', ['heading' => 'Outreach'])]
class Index extends Component
{
    use WithPagination;

    public function mount(): void
    {
        $this->authorize('viewAny', Campaign::class);
    }

    public function render()
    {
        $hasChannelRows = CampaignRecipientChannel::exists();

        if ($hasChannelRows) {
            $stats = [
                'preparing' => Campaign::query()->where('type', 'proposal')
                    ->where('status', CampaignStatus::Preparing->value)->count(),
                'scheduled' => CampaignRecipientChannel::query()->where('delivery_status', DeliveryStatus::Scheduled->value)->count(),
                'processing' => CampaignRecipientChannel::query()->where('delivery_status', DeliveryStatus::Processing->value)->count(),
                'sent' => CampaignRecipientChannel::query()->whereIn('delivery_status', [
                    DeliveryStatus::Sent->value, DeliveryStatus::Accepted->value, DeliveryStatus::Delivered->value,
                ])->count(),
                'failed' => CampaignRecipientChannel::query()->where('delivery_status', DeliveryStatus::Failed->value)->count(),
                'unknown' => CampaignRecipientChannel::query()->where('delivery_status', DeliveryStatus::Unknown->value)->count(),
                'ineligible' => CampaignRecipientChannel::query()->where('delivery_status', DeliveryStatus::Skipped->value)->count(),
            ];
        } else {
            $stats = [
                'preparing' => 0,
                'scheduled' => CampaignRecipient::query()->where('delivery_status', DeliveryStatus::Scheduled->value)->count(),
                'processing' => CampaignRecipient::query()->where('delivery_status', DeliveryStatus::Processing->value)->count(),
                'sent' => CampaignRecipient::query()->whereIn('delivery_status', [
                    DeliveryStatus::Sent->value, DeliveryStatus::Accepted->value, DeliveryStatus::Delivered->value,
                ])->count(),
                'failed' => CampaignRecipient::query()->where('delivery_status', DeliveryStatus::Failed->value)->count(),
                'unknown' => CampaignRecipient::query()->where('delivery_status', DeliveryStatus::Unknown->value)->count(),
                'ineligible' => CampaignRecipient::query()->where('eligibility_status', 'INELIGIBLE')->count(),
            ];
        }

        return view('livewire.proposals.index', [
            'campaigns' => Campaign::query()
                ->where('type', 'proposal')
                ->with(['proposalTemplate'])
                ->withCount('recipients')
                ->latest()
                ->paginate(15),
            'monitoring' => $stats,
        ]);
    }
}
