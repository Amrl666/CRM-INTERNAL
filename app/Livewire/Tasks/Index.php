<?php

namespace App\Livewire\Tasks;

use App\Domain\Leads\Models\Lead;
use App\Domain\Tasks\Actions\CompleteTaskAction;
use App\Domain\Tasks\Actions\CreateTaskAction;
use App\Domain\Tasks\Models\Task;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.sidebar', ['heading' => 'Tugas'])]
class Index extends Component
{
    public string $title = '';

    public ?string $leadId = null;

    public string $priority = 'medium';

    public string $filterStatus = 'all';

    public function mount(): void
    {
        $this->authorize('tasks.manage');
    }

    public function create(CreateTaskAction $action): void
    {
        $this->validate(['title' => ['required', 'string', 'max:255']]);

        $action->handle([
            'title' => trim($this->title),
            'priority' => $this->priority,
            'lead_id' => $this->leadId ?: null,
            'assigned_to' => auth()->id(),
        ], auth()->id());

        $this->title = '';
        $this->leadId = null;
    }

    public function complete(Task $task, CompleteTaskAction $action): void
    {
        $this->authorize('update', $task);
        $action->handle($task, auth()->id());
    }

    public function render()
    {
        $query = Task::query()
            ->with(['lead.company', 'deal'])
            ->where(function ($q) {
                $q->whereNull('assigned_to')
                    ->orWhere('assigned_to', auth()->id());
            });

        if ($this->filterStatus === 'pending') {
            $query->whereIn('status', ['pending', 'in_progress']);
        } elseif ($this->filterStatus === 'completed') {
            $query->where('status', 'completed');
        }

        return view('livewire.tasks.index', [
            'tasks' => $query->latest('due_at')->get(),
            'leads' => $this->canSeeLeads() ? Lead::query()->active()->with('company')->limit(100)->get() : collect(),
        ]);
    }

    private function canSeeLeads(): bool
    {
        return auth()->user()?->can('leads.view') ?? false;
    }
}
