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

final class RequestProposalApprovalAction
{
    public function __construct(
        private readonly AudiencePreview $preview,
        private readonly ProposalApprovalSnapshot $snapshot,
        private readonly RecordAuditAction $audit,
    ) {}

    public function handle(Campaign $campaign, int $userId): Campaign
    {
        if (! in_array($campaign->status, [CampaignStatus::Draft, CampaignStatus::Review], true)) {
            throw new InvalidArgumentException('Campaign tidak dapat diajukan untuk approval pada status ini.');
        }

        if (! $campaign->isProposal()) {
            throw new InvalidArgumentException('Action ini hanya untuk campaign proposal.');
        }

        $user = User::query()->findOrFail($userId);
        $filter = AudienceFilter::fromArray($campaign->audience_filters ?? []);
        $summary = $this->preview->estimate($campaign, $filter, fresh: true);

        $eligible = 0;
        foreach ($summary['channels'] as $counts) {
            $eligible += $counts['eligible'];
        }

        if ($eligible === 0) {
            throw new InvalidArgumentException('Tidak ada estimasi penerima eligible untuk filter/saluran ini.');
        }

        if ($campaign->channel?->includesWhatsApp() && ! config('proposals.whatsapp.proposal_campaign_enabled')) {
            throw new InvalidArgumentException('Campaign proposal WhatsApp belum diaktifkan administrator.');
        }

        if ($user->hasRole('super_admin')) {
            $approvalSnapshot = $this->snapshot->build($campaign, $summary);

            $campaign->update([
                'status' => CampaignStatus::Approved,
                'approved_by' => $userId,
                'approved_at' => now(),
                'approved_revision' => $campaign->revision,
                'approval_snapshot' => $approvalSnapshot,
            ]);

            $this->audit->handle(
                user: $userId,
                action: 'proposal_campaign.auto_approved_super_admin',
                subject: $campaign,
                after: [
                    'revision' => $campaign->revision,
                    'estimated_recipients' => $eligible,
                    'channel' => $campaign->channel?->value,
                ],
            );

            return $campaign->refresh();
        }

        $campaign->update(['status' => CampaignStatus::Review]);

        $this->audit->handle(user: $userId, action: 'proposal_campaign.approval_requested', subject: $campaign, after: [
            'estimated_recipients' => $eligible,
            'channels' => $summary['channels'],
            'revision' => $campaign->revision,
        ]);

        return $campaign->refresh();
    }
}
