<?php

namespace App\Domain\Campaigns\Actions;

use App\Domain\Audit\Actions\RecordAuditAction;
use App\Domain\Campaigns\Enums\CampaignStatus;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Models\ProposalDeliveryAttempt;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class ReviseProposalCampaignAction
{
    public function __construct(private readonly RecordAuditAction $audit) {}

    /** @param array<string, mixed> $changes */
    public function handle(Campaign $campaign, array $changes, int $userId): Campaign
    {
        if (in_array($campaign->status, [
            CampaignStatus::Preparing, CampaignStatus::Scheduled, CampaignStatus::Running,
            CampaignStatus::Paused, CampaignStatus::Completed, CampaignStatus::Cancelled,
        ], true)) {
            throw new InvalidArgumentException('Campaign yang sedang diproses atau selesai tidak dapat direvisi.');
        }

        $allowed = [
            'name', 'channel', 'subject_template', 'body_html_template', 'body_text_template',
            'whatsapp_template', 'message_template_id', 'whatsapp_message_template_id',
            'audience_filters', 'timezone', 'business_days', 'business_start', 'business_end',
            'excluded_dates', 'rate_limit_per_hour', 'min_spacing_seconds', 'scheduled_start_at',
        ];

        $changes = array_intersect_key($changes, array_flip($allowed));

        return DB::transaction(function () use ($campaign, $changes, $userId): Campaign {
            $campaign->update(array_merge($changes, [
                'revision' => $campaign->revision + 1,
                'status' => CampaignStatus::Draft,
                'approved_by' => null,
                'approved_at' => null,
                'approved_revision' => null,
                'approval_snapshot' => null,
                'audience_snapshot_at' => null,
                'audience_resolution_cursor' => null,
                'audience_resolved_at' => null,
                'started_at' => null,
                'completed_at' => null,
                'paused_at' => null,
            ]));

            $channelIds = $campaign->recipientChannels()->pluck('id');

            if ($channelIds->isNotEmpty()) {
                ProposalDeliveryAttempt::query()
                    ->whereIn('campaign_recipient_channel_id', $channelIds)
                    ->delete();
            }

            $campaign->recipientChannels()->delete();

            $campaign->recipients()
                ->whereIn('delivery_status', ['SCHEDULED', 'PENDING', 'READY'])
                ->update([
                    'delivery_status' => 'PENDING',
                    'scheduled_at' => null,
                    'updated_at' => now(),
                ]);

            $this->audit->handle(user: $userId, action: 'proposal_campaign.revised', subject: $campaign, after: [
                'revision' => $campaign->revision,
                'changes' => array_keys($changes),
                'approval_invalidated' => true,
            ]);

            return $campaign->refresh();
        });
    }
}
