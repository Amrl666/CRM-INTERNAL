<?php

namespace App\Domain\Campaigns\Actions;

use App\Domain\Audit\Actions\RecordAuditAction;
use App\Domain\Campaigns\Enums\CampaignStatus;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Services\ProposalApprovalSnapshot;

final class ApproveProposalCampaignAction
{
    public function __construct(
        private readonly ProposalApprovalSnapshot $snapshot,
        private readonly RecordAuditAction $audit,
    ) {}

    public function handle(Campaign $campaign, int $approvedBy): Campaign
    {
        if ($campaign->status !== CampaignStatus::Review) {
            throw new \InvalidArgumentException('Campaign harus berstatus review sebelum disetujui.');
        }

        if ($campaign->created_by === $approvedBy) {
            throw new \InvalidArgumentException('Pembuat campaign tidak boleh menyetujui campaign sendiri.');
        }

        $snapshot = $this->snapshot->build($campaign);

        $campaign->update([
            'status' => CampaignStatus::Approved,
            'approved_by' => $approvedBy,
            'approved_at' => now(),
            'approved_revision' => $campaign->revision,
            'approval_snapshot' => $snapshot,
        ]);

        $this->audit->handle(user: $approvedBy, action: 'proposal_campaign.approved', subject: $campaign, after: [
            'revision' => $campaign->revision,
            'recipients' => count($snapshot['recipients']),
            'channel' => $campaign->channel?->value,
        ]);

        return $campaign;
    }
}
