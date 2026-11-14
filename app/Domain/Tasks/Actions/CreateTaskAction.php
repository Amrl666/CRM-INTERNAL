<?php

namespace App\Domain\Tasks\Actions;

use App\Domain\Audit\Actions\RecordAuditAction;
use App\Domain\Tasks\Models\Task;

final class CreateTaskAction
{
    public function __construct(private readonly RecordAuditAction $audit) {}

    public function handle(array $data, ?int $userId = null): Task
    {
        $task = Task::create([
            'title' => $data['title'],
            'priority' => $data['priority'] ?? 'medium',
            'lead_id' => $data['lead_id'] ?? null,
            'deal_id' => $data['deal_id'] ?? null,
            'assigned_to' => $data['assigned_to'] ?? null,
            'due_at' => $data['due_at'] ?? null,
            'status' => 'pending',
        ]);

        $this->audit->handle(user: $userId, action: 'task.created', subject: $task, after: $task->toArray());

        return $task;
    }
}
