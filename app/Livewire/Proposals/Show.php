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
use App\Domain\Campaigns\Enums\ProposalChannel;
use App\Domain\Campaigns\Models\Campaign;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.sidebar', ['heading' => 'Outreach'])]
class Show extends Component
{
    use WithPagination;

    #[Locked]
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
        $this->run(fn () => $action->handle($this->campaign, (int) auth()->id()), 'Campaign diajukan untuk approval.');
    }

    public function approve(ApproveProposalCampaignAction $action): void
    {
        $this->authorize('approve', Campaign::class);
        $this->run(fn () => $action->handle($this->campaign, (int) auth()->id()), 'Campaign berhasil disetujui.');
    }

    public function submitForReview(): void
    {
        $this->authorize('update', $this->campaign);
        $this->resetErrorBag();

        if ($this->campaign->status !== CampaignStatus::Draft) {
            $this->addError('campaign', 'Campaign hanya dapat diajukan pada status draft.');

            return;
        }

        $start = function (): void {
            $this->campaign = app(RequestProposalApprovalAction::class)->handle($this->campaign, (int) auth()->id());

            if ($this->campaign->status === CampaignStatus::Review) {
                $this->campaign = app(ApproveProposalCampaignAction::class)->handle($this->campaign, (int) auth()->id());
            }
        };

        $this->run($start, 'Campaign berhasil diajukan untuk review dan disetujui.');

        $this->campaign->refresh();
    }

    public function schedule(ScheduleProposalCampaignAction $action): void
    {
        $this->authorize('send', Campaign::class);
        $this->run(fn () => $action->handle($this->campaign, (int) auth()->id()), 'Pengiriman berhasil dijadwalkan.');
    }

    public function pause(PauseProposalCampaignAction $action): void
    {
        $this->authorize('send', Campaign::class);
        $this->run(fn () => $action->handle($this->campaign, (int) auth()->id()), 'Campaign dijeda.');
    }

    public function resume(ResumeProposalCampaignAction $action): void
    {
        $this->authorize('send', Campaign::class);
        $this->run(fn () => $action->handle($this->campaign, (int) auth()->id()), 'Campaign dilanjutkan.');
    }

    public function cancel(CancelProposalCampaignAction $action): void
    {
        $this->authorize('cancel', Campaign::class);
        $this->validate(['cancelReason' => ['nullable', 'string', 'max:500']]);

        $cancelled = $this->run(
            fn () => $action->handle($this->campaign, (int) auth()->id(), trim($this->cancelReason) ?: null),
            'Campaign dibatalkan.',
        );

        if ($cancelled) {
            $this->cancelReason = '';
        }
    }

    public function render()
    {
        $recipients = $this->campaign->recipients()
            ->with(['contact.company', 'lead'])
            ->orderBy('created_at')
            ->paginate(25);

        $previewQuery = $this->campaign->recipients()
            ->where('eligibility_status', EligibilityStatus::Eligible);

        if ($this->campaign->channel === ProposalChannel::WhatsApp) {
            $previewQuery
                ->whereNotNull('rendered_whatsapp')
                ->where('rendered_whatsapp', '<>', '');
        } else {
            $previewQuery->whereNotNull('rendered_subject');
        }

        $preview = $previewQuery->first();

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

    private function run(callable $callback, ?string $successMessage = null): bool
    {
        $this->resetErrorBag();

        try {
            $callback();
            $this->campaign->refresh();

            if ($successMessage !== null) {
                $this->dispatch('notify', message: $successMessage, type: 'success');
            }

            return true;
        } catch (\InvalidArgumentException $e) {
            $this->addError('campaign', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);
            $this->addError('campaign', 'Aksi tidak dapat diproses. Silakan coba lagi.');
        }

        $this->campaign->refresh();

        return false;
    }
}
