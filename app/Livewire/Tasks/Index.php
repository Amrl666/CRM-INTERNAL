<?php

namespace App\Livewire\Tasks;

use App\Domain\Leads\Models\Lead;
use App\Domain\Tasks\Actions\CompleteTaskAction;
use App\Domain\Tasks\Actions\CreateTaskAction;
use App\Domain\Tasks\Models\Task;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.sidebar', ['heading' => 'Tugas'])]
class Index extends Component
{
    public string $title = '';

    public ?string $leadId = null;

    public string $priority = 'medium';

    public string $due_at = '';

    #[Url]
    public string $filterStatus = 'all';

    public function mount(): void
    {
        $this->authorize('tasks.manage');
    }

    public function create(CreateTaskAction $action): void
    {
        $this->authorize('tasks.manage');

        $this->leadId = $this->leadId ?: null;

        $data = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'priority' => ['required', 'in:low,medium,high'],
            'leadId' => ['nullable', 'uuid', 'exists:leads,id'],
            'due_at' => ['nullable', 'date'],
        ]);

        if ($this->leadId !== null && $this->leadId !== '') {
            $visible = Lead::query()
                ->visibleTo(auth()->user())
                ->whereKey($this->leadId)
                ->exists();

            if (! $visible) {
                $this->addError('leadId', 'Lead yang dipilih tidak ditemukan atau bukan milik Anda.');

                return;
            }
        }

        try {
            $action->handle([
                'title' => trim($data['title']),
                'priority' => $data['priority'],
                'lead_id' => $this->leadId ?: null,
                'assigned_to' => auth()->id(),
                'due_at' => $data['due_at'] ?: null,
            ], auth()->id());

            $this->reset('title', 'leadId', 'due_at');
            $this->priority = 'medium';
            $this->resetValidation();
            $this->dispatch('notify', message: 'Tugas berhasil dibuat.', type: 'success');
        } catch (\Throwable $e) {
            report($e);
            $this->addError('form', 'Tugas tidak dapat disimpan. Silakan coba lagi.');
        }
    }

    public function complete(Task $task, CompleteTaskAction $action): void
    {
        $this->authorize('update', $task);

        try {
            $action->handle($task, auth()->id());
            $this->dispatch('notify', message: 'Tugas ditandai selesai.', type: 'success');
        } catch (\Throwable $e) {
            report($e);
            $this->addError('form', 'Tugas tidak dapat diselesaikan. Silakan coba lagi.');
        }
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
            $query->whereIn('status', ['pending', 'in_progress'])
                ->orderByRaw('CASE WHEN due_at IS NULL THEN 1 ELSE 0 END')
                ->orderBy('due_at');
        } elseif ($this->filterStatus === 'completed') {
            $query->where('status', 'completed')->latest('completed_at');
        } else {
            $query->orderByRaw('CASE WHEN status = \'completed\' THEN 1 ELSE 0 END')
                ->orderByRaw('CASE WHEN due_at IS NULL THEN 1 ELSE 0 END')
                ->orderBy('due_at');
        }

        return view('livewire.tasks.index', [
            'tasks' => $query->get(),
            'leads' => Lead::query()
                ->visibleTo(auth()->user())
                ->active()
                ->with('company')
                ->orderBy('created_at', 'desc')
                ->limit(100)
                ->get(),
        ]);
    }
}
