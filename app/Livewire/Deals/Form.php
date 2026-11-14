<?php

namespace App\Livewire\Deals;

use App\Domain\Companies\Models\Company;
use App\Domain\Deals\Models\Deal;
use App\Domain\Deals\Models\Pipeline;
use App\Domain\Deals\Models\PipelineStage;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.sidebar', ['heading' => 'Deal'])]
class Form extends Component
{
    public ?string $dealId = null;

    public string $company_id = '';

    public string $title = '';

    public string $value = '0';

    public string $expected_close_date = '';

    public string $stage_id = '';

    public function mount(?Deal $deal = null): void
    {
        if ($deal === null || ! $deal->exists) {
            $this->authorize('create', Deal::class);
            $this->stage_id = (string) (PipelineStage::where('pipeline_id', Pipeline::where('is_default', true)->value('id'))->orderBy('position')->value('id') ?? '');

            return;
        }

        $this->authorize('update', $deal);

        $this->dealId = $deal->id;
        $this->company_id = $deal->company_id;
        $this->title = $deal->title;
        $this->value = (string) $deal->value;
        $this->expected_close_date = $deal->expected_close_date ? $deal->expected_close_date->format('Y-m-d') : '';
        $this->stage_id = $deal->stage_id;
    }

    public function save()
    {
        $data = $this->validate([
            'company_id' => ['required', 'uuid', 'exists:companies,id'],
            'title' => ['required', 'string', 'max:255'],
            'value' => ['required', 'numeric', 'min:0'],
            'expected_close_date' => ['nullable', 'date'],
            'stage_id' => ['required', 'uuid', 'exists:pipeline_stages,id'],
        ]);

        $stage = PipelineStage::findOrFail($data['stage_id']);

        if ($this->dealId !== null) {
            $deal = Deal::findOrFail($this->dealId);
            $deal->update([
                'company_id' => $data['company_id'],
                'title' => $data['title'],
                'value' => $data['value'],
                'expected_close_date' => $data['expected_close_date'] ?: null,
                'stage_id' => $stage->id,
            ]);

            return redirect()->route('deals.index');
        }

        Deal::create([
            'company_id' => $data['company_id'],
            'pipeline_id' => $stage->pipeline_id,
            'stage_id' => $stage->id,
            'owner_id' => auth()->id(),
            'title' => $data['title'],
            'value' => $data['value'],
            'expected_close_date' => $data['expected_close_date'] ?: null,
        ]);

        return redirect()->route('deals.index');
    }

    public function render()
    {
        return view('livewire.deals.form', [
            'companies' => Company::orderBy('name')->get(),
            'stages' => PipelineStage::with('pipeline')->orderBy('position')->get(),
        ]);
    }
}
