<?php

namespace App\Domain\Campaigns\Actions;

use App\Domain\Audit\Actions\RecordAuditAction;
use App\Domain\Campaigns\Enums\CampaignStatus;
use App\Domain\Campaigns\Models\Campaign;
use App\Jobs\FinalizeProposalCampaignPreparationJob;
use App\Jobs\ResolveProposalAudienceJob;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class PrepareProposalCampaignAction
{
    public function __construct(
        private readonly RecordAuditAction $audit,
    ) {}

    public function handle(Campaign $campaign, ?int $userId = null): Campaign
    {
        return DB::transaction(function () use ($campaign, $userId): Campaign {
            $locked = Campaign::query()->lockForUpdate()->findOrFail($campaign->id);

            if ($locked->status !== CampaignStatus::Approved) {
                throw new InvalidArgumentException('Campaign harus disetujui sebelum dimulai.');
            }

            if (! $locked->isApprovedForCurrentRevision()) {
                throw new InvalidArgumentException('Approval campaign sudah tidak berlaku untuk revisi ini.');
            }

            $legacy = $locked->audience_filters === null;

            $locked->update([
                'status' => CampaignStatus::Preparing,
                'audience_snapshot_at' => $legacy
                    ? ($locked->audience_snapshot_at ?? now())
                    : now(),
                'audience_resolution_cursor' => $legacy ? null : null,
                'audience_resolved_at' => $legacy
                    ? ($locked->audience_resolved_at ?? now())
                    : null,
                'last_error' => null,
            ]);

            if ($legacy) {
                FinalizeProposalCampaignPreparationJob::dispatch($locked->id)
                    ->onQueue('campaigns')
                    ->afterCommit();
            } else {
                ResolveProposalAudienceJob::dispatch($locked->id)
                    ->onQueue('campaigns')
                    ->afterCommit();
            }

            $this->audit->handle(
                user: $userId,
                action: 'proposal_campaign.preparing',
                subject: $locked,
                after: ['legacy_snapshot' => $legacy],
            );

            return $locked->refresh();
        });
    }
}
