<?php

namespace App\Livewire\Leads;

use App\Domain\Contacts\Models\Contact;
use App\Domain\Leads\Actions\CreateLeadAction;
use App\Domain\Leads\Models\Lead;
use App\Support\PhoneNormalizer;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.sidebar', ['heading' => 'Lead'])]
class Form extends Component
{
    public ?string $leadId = null;

    public string $company_name = '';

    public string $contact_name = '';

    public string $email = '';

    public string $phone = '';

    public string $industry = '';

    public string $source = '';

    public string $priority = 'medium';

    public function mount(?Lead $lead = null): void
    {
        if ($lead === null || ! $lead->exists) {
            $this->authorize('create', Lead::class);

            return;
        }

        $this->authorize('update', $lead);
        $lead->load(['company', 'contact']);

        $this->leadId = $lead->id;
        $this->company_name = $lead->company->name;
        $this->contact_name = $lead->contact?->full_name ?? '';
        $this->email = $lead->contact?->email ?? '';
        $this->phone = $lead->contact?->phone_e164 ?? '';
        $this->industry = $lead->company->industry ?? '';
        $this->source = $lead->source?->name ?? '';
        $this->priority = $lead->priority;
    }

    public function save(CreateLeadAction $createLead)
    {
        $data = $this->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'contact_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:320'],
            'phone' => ['nullable', 'string', 'max:30'],
            'industry' => ['nullable', 'string', 'max:100'],
            'source' => ['nullable', 'string', 'max:100'],
            'priority' => ['required', 'in:low,medium,high'],
        ]);

        if ($data['email'] === '' && $data['phone'] === '') {
            $this->addError('email', 'Email atau telepon wajib diisi.');

            return;
        }

        if ($this->leadId !== null) {
            $lead = Lead::findOrFail($this->leadId);
            $lead->company->update([
                'name' => trim($data['company_name']),
                'industry' => $data['industry'] ?: null,
            ]);
            $lead->contact?->update([
                'full_name' => trim($data['contact_name']),
                'email' => $data['email'] ?: null,
                'normalized_email' => Contact::normalizeEmail($data['email']),
                'phone_e164' => PhoneNormalizer::toE164($data['phone']),
            ]);
            $lead->update(['priority' => $data['priority']]);

            return redirect()->route('leads.show', $lead);
        }

        $lead = $createLead->handle([
            'company_name' => $data['company_name'],
            'contact_name' => $data['contact_name'],
            'email' => $data['email'] ?: null,
            'phone' => $data['phone'] ?: null,
            'industry' => $data['industry'] ?: null,
            'source' => $data['source'] ?: null,
            'priority' => $data['priority'],
        ], auth()->id());

        return redirect()->route('leads.show', $lead);
    }

    public function render()
    {
        return view('livewire.leads.form');
    }
}
