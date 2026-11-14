<?php

namespace App\Livewire\Proposals;

use App\Domain\Campaigns\Actions\ApproveProposalCampaignAction;
use App\Domain\Campaigns\Actions\CancelProposalCampaignAction;
use App\Domain\Campaigns\Actions\PauseProposalCampaignAction;
use App\Domain\Campaigns\Actions\RequestProposalApprovalAction;
use App\Domain\Campaigns\Actions\ResumeProposalCampaignAction;
use App\Domain\Campaigns\Actions\ScheduleProposalCampaignAction;
use App\Domain\Campaigns\Enums\CampaignStatus;
use App\Domain\Campaigns\Enums\DeliveryStatus;
use App\Domain\Campaigns\Enums\EligibilityStatus;
use App\Domain\Campaigns\Enums\PdfStatus;
use App\Domain\Campaigns\Models\Campaign;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.sidebar', ['heading' => 'Proposal Campaign'])]
class Show extends Component
{
    use WithPagination;

    public Campaign $campaign;

    public string $cancelReason = '';

    public function mount(Campaign $campaign): void
    {
        abort_unless($campaign->type === 'proposal', 404);
        $this->authorize('view', $campaign);
        $this->campaign = $campaign;
    }

    public function requestApproval(RequestProposalApprovalAction $action): void
    {
        $this->authorize('update', $this->campaign);
        $this->run(fn () => $action->handle($this->campaign, (int) auth()->id()));
    }

    public function approve(ApproveProposalCampaignAction $action): void
    {
        $this->authorize('approve', Campaign::class);
        $this->run(fn () => $action->handle($this->campaign, (int) auth()->id()));
    }

    public function schedule(ScheduleProposalCampaignAction $action): void
    {
        $this->authorize('send', Campaign::class);
        $this->run(fn () => $action->handle($this->campaign, (int) auth()->id()));
    }

    public function pause(PauseProposalCampaignAction $action): void
    {
        $this->authorize('send', Campaign::class);
        $this->run(fn () => $action->handle($this->campaign, (int) auth()->id()));
    }

    public function resume(ResumeProposalCampaignAction $action): void
    {
        $this->authorize('send', Campaign::class);
        $this->run(fn () => $action->handle($this->campaign, (int) auth()->id()));
    }

    public function cancel(CancelProposalCampaignAction $action): void
    {
        $this->authorize('cancel', Campaign::class);
        $this->run(fn () => $action->handle($this->campaign, (int) auth()->id(), $this->cancelReason ?: null));
        $this->cancelReason = '';
    }

    public function render()
    {
        $recipients = $this->campaign->recipients()
            ->with(['contact.company', 'lead'])
            ->orderBy('created_at')
            ->paginate(25);

        $preview = $this->campaign->recipients()
            ->where('eligibility_status', EligibilityStatus::Eligible)
            ->whereNotNull('rendered_subject')
            ->first();

        return view('livewire.proposals.show', [
            'recipients' => $recipients,
            'preview' => $preview,
            'counts' => $this->campaign->deliveryCounts(),
            'eligibleCount' => $this->campaign->recipients()->where('eligibility_status', EligibilityStatus::Eligible)->count(),
            'ineligibleCount' => $this->campaign->recipients()->where('eligibility_status', EligibilityStatus::Ineligible)->count(),
            'pdfReady' => $this->campaign->recipients()->where('pdf_status', PdfStatus::Ready)->count(),
            'pdfFailed' => $this->campaign->recipients()->where('pdf_status', PdfStatus::Failed)->count(),
            'pdfPending' => $this->campaign->recipients()->whereIn('pdf_status', [PdfStatus::Pending, PdfStatus::Generating])->count(),
            'eligibilityReasons' => $this->campaign->recipients()
                ->where('eligibility_status', EligibilityStatus::Ineligible)
                ->selectRaw('eligibility_reason, count(*) as total')
                ->groupBy('eligibility_reason')
                ->pluck('total', 'eligibility_reason'),
            'nextDelivery' => $this->campaign->recipients()
                ->whereNotNull('scheduled_at')
                ->where('scheduled_at', '>=', now())
                ->whereNotIn('delivery_status', DeliveryStatus::terminal())
                ->min('scheduled_at'),
            'isCreator' => $this->campaign->created_by === auth()->id(),
            'polling' => in_array($this->campaign->status, [
                CampaignStatus::Scheduled, CampaignStatus::Running,
                CampaignStatus::Draft, CampaignStatus::Review,
            ], true),
        ]);
    }

    private function run(callable $callback): void
    {
        $this->resetErrorBag();

        try {
            $callback();
        } catch (\Throwable $e) {
            $this->addError('campaign', $e->getMessage());
        }

        $this->campaign->refresh();
    }
}
