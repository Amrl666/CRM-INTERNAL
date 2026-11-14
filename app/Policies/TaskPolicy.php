<?php

namespace App\Policies;

use App\Domain\Tasks\Models\Task;
use App\Models\User;

class TaskPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('tasks.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('tasks.manage');
    }

    public function update(User $user, Task $task): bool
    {
        return $user->can('tasks.manage')
            && ($user->can('leads.assign') || $task->assigned_to === $user->id || $task->assigned_to === null);
    }
}
