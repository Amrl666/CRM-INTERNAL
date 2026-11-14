<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Audit\Actions\RecordAuditAction;
use App\Models\User;

final class AssignRoleAction
{
    public function __construct(private readonly RecordAuditAction $audit) {}

    public function handle(User $user, string $roleName, ?int $assignedBy = null): void
    {
        $before = $user->getRoleNames()->all();

        $user->syncRoles([$roleName]);

        $this->audit->handle(user: $assignedBy, action: 'user.role_changed', subject: $user, before: [
            'roles' => $before,
        ], after: [
            'roles' => [$roleName],
        ]);
    }
}
