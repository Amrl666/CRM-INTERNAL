<?php

namespace App\Livewire\Campaigns;

use App\Domain\Campaigns\Actions\ApproveCampaignAction;
use App\Domain\Campaigns\Actions\RequestCampaignApprovalAction;
use App\Domain\Campaigns\Enums\CampaignStatus;
use App\Domain\Campaigns\Models\Campaign;
use App\Jobs\GenerateCampaignDraftsJob;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('layouts.sidebar', ['heading' => 'Email Drafts'])]
class Show extends Component
{
    #[Locked]
    public Campaign $campaign;

    public function mount(Campaign $campaign): void
    {
        $this->authorize('view', $campaign);
        $this->campaign = $campaign;
    }

    public function requestApproval(RequestCampaignApprovalAction $action): void
    {
        $this->authorize('update', $this->campaign);

        try {
            $action->handle($this->campaign, auth()->id());
            $this->dispatch('notify', message: 'Campaign diajukan untuk approval.', type: 'success');
        } catch (\InvalidArgumentException $e) {
            $this->addError('campaign', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);
            $this->addError('campaign', 'Campaign tidak dapat diajukan. Silakan coba lagi.');
        }

        $this->campaign->refresh();
    }

    public function approve(ApproveCampaignAction $action): void
    {
        $this->authorize('approve', Campaign::class);

        try {
            $action->handle($this->campaign, auth()->id());
            $this->dispatch('notify', message: 'Campaign berhasil disetujui.', type: 'success');
        } catch (\InvalidArgumentException $e) {
            $this->addError('campaign', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);
            $this->addError('campaign', 'Campaign tidak dapat disetujui. Silakan coba lagi.');
        }

        $this->campaign->refresh();
    }

    public function generateDrafts(): void
    {
        $this->authorize('send', Campaign::class);

        $this->campaign->refresh();

        if ($this->campaign->status !== CampaignStatus::Approved) {
            $this->addError('campaign', 'Campaign harus disetujui terlebih dahulu.');

            return;
        }

        try {
            GenerateCampaignDraftsJob::dispatch($this->campaign->id);
            $this->dispatch('notify', message: 'Pembuatan draft email dimasukkan ke antrean.', type: 'success');
        } catch (\Throwable $e) {
            report($e);
            $this->addError('campaign', 'Draft email tidak dapat dimasukkan ke antrean. Silakan coba lagi.');
        }

        $this->campaign->refresh();
    }

    public function render()
    {
        return view('livewire.campaigns.show', [
            'recipients' => $this->campaign->recipients()->with('contact.company')->get(),
            'status' => $this->campaign->status,
        ]);
    }
}
