<?php

namespace App\Domain\Campaigns\Actions;

use App\Domain\Audit\Actions\RecordAuditAction;
use App\Domain\Campaigns\Enums\CampaignStatus;
use App\Domain\Campaigns\Enums\EligibilityStatus;
use App\Domain\Campaigns\Enums\PdfStatus;
use App\Domain\Campaigns\Models\Campaign;

final class RequestProposalApprovalAction
{
    public function __construct(
        private readonly RefreshProposalEligibilityAction $eligibility,
        private readonly RenderProposalMessageAction $renderMessage,
        private readonly RecordAuditAction $audit,
    ) {}

    public function handle(Campaign $campaign, int $userId): Campaign
    {
        if (! in_array($campaign->status, [CampaignStatus::Draft, CampaignStatus::Review], true)) {
            throw new \InvalidArgumentException('Campaign tidak dapat diajukan untuk approval pada status ini.');
        }

        $summary = $this->eligibility->handle($campaign, $userId);

        if ($summary['eligible'] === 0) {
            throw new \InvalidArgumentException('Tidak ada penerima eligible. Perbaiki consent/suppression terlebih dahulu.');
        }

        $notReady = $campaign->recipients()
            ->where('eligibility_status', EligibilityStatus::Eligible)
            ->where('pdf_status', '!=', PdfStatus::Ready)
            ->count();

        if ($notReady > 0) {
            throw new \InvalidArgumentException("{$notReady} penerima eligible belum memiliki PDF siap. Tunggu proses dokumen selesai.");
        }

        $campaign->recipients()
            ->where('eligibility_status', EligibilityStatus::Eligible)
            ->with(['contact.company', 'lead.source'])
            ->each(fn ($recipient) => $this->renderMessage->handle($recipient));

        $campaign->update(['status' => CampaignStatus::Review]);

        $this->audit->handle(user: $userId, action: 'proposal_campaign.approval_requested', subject: $campaign, after: [
            'eligible' => $summary['eligible'],
            'ineligible' => $summary['ineligible'],
            'reasons' => $summary['reasons'],
            'revision' => $campaign->revision,
        ]);

        return $campaign;
    }
}
