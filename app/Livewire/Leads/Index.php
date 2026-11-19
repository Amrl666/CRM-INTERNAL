<?php

namespace App\Livewire\Leads;

use App\Domain\Leads\Actions\ArchiveLeadAction;
use App\Domain\Leads\Enums\LeadStatus;
use App\Domain\Leads\Models\Lead;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.sidebar', ['heading' => 'Lead'])]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $priority = '';

    #[Url]
    public string $owner = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Lead::class);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'status', 'priority', 'owner'], true)) {
            $this->resetPage();
        }
    }

    public function archive(string $leadId, ArchiveLeadAction $action): void
    {
        $lead = Lead::findOrFail($leadId);
        $this->authorize('update', $lead);

        try {
            $action->handle($lead, auth()->id());
            $this->dispatch('notify', message: 'Lead berhasil diarsipkan.', type: 'success');
        } catch (\Throwable $e) {
            report($e);
            $this->addError('archive', 'Lead tidak dapat diarsipkan. Silakan coba lagi.');
        }
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'status', 'priority', 'owner');
        $this->resetPage();
    }

    public function render()
    {
        $leads = Lead::query()
            ->visibleTo(auth()->user())
            ->active()
            ->with(['company', 'contact', 'owner'])
            ->when($this->search !== '', function ($query) {
                $term = '%'.mb_strtolower($this->search).'%';

                $query->where(function ($q) use ($term) {
                    $q->whereHas('company', fn ($c) => $c->whereRaw('LOWER(name) LIKE ?', [$term]))
                        ->orWhereHas('contact', fn ($c) => $c->whereRaw('LOWER(full_name) LIKE ?', [$term])
                            ->orWhereRaw('LOWER(normalized_email) LIKE ?', [$term]));
                });
            })
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->when($this->priority !== '', fn ($q) => $q->where('priority', $this->priority))
            ->when($this->owner !== '', fn ($q) => $q->where('owner_id', (int) $this->owner))
            ->latest()
            ->paginate(15);

        return view('livewire.leads.index', [
            'leads' => $leads,
            'statuses' => LeadStatus::cases(),
            'owners' => auth()->user()->can('leads.assign') ? User::orderBy('name')->get() : collect(),
        ]);
    }
}
