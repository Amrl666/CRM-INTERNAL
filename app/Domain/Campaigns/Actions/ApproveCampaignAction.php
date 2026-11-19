<?php

namespace App\Domain\Campaigns\Actions;

use App\Domain\Audit\Actions\RecordAuditAction;
use App\Domain\Campaigns\Enums\CampaignStatus;
use App\Domain\Campaigns\Models\Campaign;
use App\Models\User;

final class ApproveCampaignAction
{
    public function __construct(private readonly RecordAuditAction $audit) {}

    public function handle(Campaign $campaign, int $approvedBy): Campaign
    {
        if ($campaign->status !== CampaignStatus::PendingApproval) {
            throw new \InvalidArgumentException('Campaign belum diajukan untuk approval.');
        }

        $approver = User::query()->findOrFail($approvedBy);

        if ($campaign->created_by === $approvedBy && ! $approver->hasRole('super_admin')
        ) {
            throw new \InvalidArgumentException('Pembuat campaign tidak boleh menyetujui campaign sendiri.');
        }

        $campaign->update([
            'status' => CampaignStatus::Approved,
            'approved_by' => $approvedBy,
            'approved_at' => now(),
        ]);

        $this->audit->handle(user: $approvedBy, action: 'campaign.approved', subject: $campaign);

        return $campaign;
    }
}
