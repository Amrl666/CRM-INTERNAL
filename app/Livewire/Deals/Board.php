<?php

namespace App\Livewire\Deals;

use App\Domain\Deals\Actions\MoveDealStageAction;
use App\Domain\Deals\Models\Deal;
use App\Domain\Deals\Models\Pipeline;
use App\Domain\Deals\Models\PipelineStage;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.sidebar', ['heading' => 'Deals'])]
class Board extends Component
{
    public ?string $pipelineId = null;

    public function mount(): void
    {
        $this->authorize('viewAny', Deal::class);

        $this->pipelineId = Pipeline::where('is_default', true)->value('id')
            ?? Pipeline::value('id');
    }

    public function move(string $dealId, string $stageId, MoveDealStageAction $action): void
    {
        $deal = Deal::findOrFail($dealId);
        $stage = PipelineStage::findOrFail($stageId);

        $this->authorize('update', $deal);

        try {
            $action->handle($deal, $stage, auth()->id());
            $this->dispatch('notify', message: 'Deal dipindahkan ke stage '.$stage->name.'.', type: 'success');
        } catch (\InvalidArgumentException $e) {
            $this->addError('board', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);
            $this->addError('board', 'Deal tidak dapat dipindahkan. Silakan coba lagi.');
        }
    }

    public function render()
    {
        $pipeline = $this->pipelineId ? Pipeline::with('stages')->find($this->pipelineId) : null;

        if ($pipeline === null) {
            $pipeline = Pipeline::with('stages')->where('is_default', true)->first()
                ?? Pipeline::with('stages')->first();
        }

        return view('livewire.deals.board', [
            'pipeline' => $pipeline,
            'pipelines' => Pipeline::orderBy('name')->get(),
            'stages' => $pipeline->stages ?? collect(),
            'deals' => Deal::query()
                ->visibleTo(auth()->user())
                ->when($pipeline, fn ($q) => $q->where('pipeline_id', $pipeline->id))
                ->with(['company', 'owner', 'stage'])
                ->get()
                ->groupBy('stage_id'),
        ]);
    }
}
