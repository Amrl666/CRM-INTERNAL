<?php

namespace App\Domain\Tasks\Actions;

use App\Domain\Audit\Actions\RecordAuditAction;
use App\Domain\Tasks\Models\Task;

final class CompleteTaskAction
{
    public function __construct(private readonly RecordAuditAction $audit) {}

    public function handle(Task $task, ?int $userId = null): Task
    {
        $task->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $this->audit->handle(user: $userId, action: 'task.completed', subject: $task, after: [
            'completed_at' => (string) ($task->completed_at ?? ''),
        ]);

        return $task;
    }
}
