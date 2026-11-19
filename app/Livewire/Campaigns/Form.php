<?php

namespace App\Livewire\Campaigns;

use App\Domain\Campaigns\Actions\CreateCampaignAction;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Models\EmailTemplate;
use App\Domain\Leads\Models\Lead;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.sidebar', ['heading' => 'Email Drafts'])]
class Form extends Component
{
    public string $name = '';

    public string $template_id = '';

    /** @var string[] */
    public array $leadIds = [];

    public function mount(): void
    {
        $this->authorize('create', Campaign::class);
    }

    public function save(CreateCampaignAction $action)
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'template_id' => ['required', 'uuid', 'exists:email_templates,id'],
            'leadIds' => ['required', 'array', 'min:1'],
            'leadIds.*' => ['uuid', 'distinct'],
        ]);

        $leadIds = array_values(array_unique($data['leadIds']));

        $validLeadIds = Lead::query()
            ->visibleTo(auth()->user())
            ->active()
            ->whereNotNull('contact_id')
            ->whereIn('id', $leadIds)
            ->pluck('id')
            ->all();

        if (count($validLeadIds) !== count($leadIds)) {
            $this->addError('leadIds', 'Sebagian lead yang dipilih tidak valid, sudah diarsipkan, atau tidak memiliki kontak.');

            return;
        }

        try {
            $campaign = $action->handle(
                trim($data['name']),
                EmailTemplate::findOrFail($data['template_id']),
                $validLeadIds,
                auth()->id(),
            );

            session()->flash('status', 'Campaign berhasil dibuat sebagai draft.');

            return redirect()->route('campaigns.show', $campaign);
        } catch (\Throwable $e) {
            report($e);
            $this->addError('form', 'Campaign tidak dapat dibuat. Silakan periksa kembali data dan coba lagi.');
        }
    }

    public function render()
    {
        return view('livewire.campaigns.form', [
            'templates' => EmailTemplate::orderBy('name')->get(),
            'leads' => Lead::query()
                ->visibleTo(auth()->user())
                ->active()
                ->whereNotNull('contact_id')
                ->with(['company', 'contact'])
                ->latest()
                ->limit(200)
                ->get(),
        ]);
    }
}
