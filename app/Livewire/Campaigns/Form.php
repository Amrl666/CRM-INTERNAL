<?php

namespace App\Livewire\Campaigns;

use App\Domain\Campaigns\Actions\CreateCampaignAction;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Models\EmailTemplate;
use App\Domain\Leads\Models\Lead;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.sidebar', ['heading' => 'Campaign'])]
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
        ]);

        $campaign = $action->handle(
            $data['name'],
            EmailTemplate::findOrFail($data['template_id']),
            $data['leadIds'],
            auth()->id(),
        );

        return redirect()->route('campaigns.show', $campaign);
    }

    public function render()
    {
        return view('livewire.campaigns.form', [
            'templates' => EmailTemplate::orderBy('name')->get(),
            'leads' => Lead::query()
                ->active()
                ->whereNotNull('contact_id')
                ->with(['company', 'contact'])
                ->latest()
                ->limit(200)
                ->get(),
        ]);
    }
}
