<?php

namespace App\Domain\Campaigns\Actions;

use App\Domain\Audit\Actions\RecordAuditAction;
use App\Domain\Campaigns\Enums\CampaignStatus;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Services\AudiencePreview;
use App\Domain\Campaigns\Services\ProposalApprovalSnapshot;
use App\Domain\Campaigns\Support\AudienceFilter;
use App\Models\User;
use InvalidArgumentException;

final class ApproveProposalCampaignAction
{
    public function __construct(
        private readonly AudiencePreview $preview,
        private readonly ProposalApprovalSnapshot $snapshot,
        private readonly RecordAuditAction $audit,
    ) {}

    public function handle(Campaign $campaign, int $approvedBy): Campaign
    {
        if ($campaign->status !== CampaignStatus::Review) {
            throw new InvalidArgumentException('Campaign harus berstatus review sebelum disetujui.');
        }

        $approver = User::query()->findOrFail($approvedBy);

        if ($campaign->created_by === $approvedBy && ! $approver->hasRole('super_admin')) {
            throw new InvalidArgumentException('Pembuat campaign tidak boleh menyetujui campaign sendiri.');
        }

        $summary = $this->preview->estimate(
            $campaign,
            AudienceFilter::fromArray($campaign->audience_filters ?? []),
            fresh: true,
        );

        $snapshot = $this->snapshot->build($campaign, $summary);

        $campaign->update([
            'status' => CampaignStatus::Approved,
            'approved_by' => $approvedBy,
            'approved_at' => now(),
            'approved_revision' => $campaign->revision,
            'approval_snapshot' => $snapshot,
        ]);

        $this->audit->handle(user: $approvedBy, action: 'proposal_campaign.approved', subject: $campaign, after: [
            'revision' => $campaign->revision,
            'estimated_recipients' => array_sum(array_map(
                static fn (array $counts): int => $counts['eligible'],
                $summary['channels'],
            )),
            'channel' => $campaign->channel?->value,
        ]);

        return $campaign;
    }
}
