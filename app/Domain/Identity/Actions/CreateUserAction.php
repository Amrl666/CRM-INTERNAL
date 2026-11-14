<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Audit\Actions\RecordAuditAction;
use App\Models\User;

final class CreateUserAction
{
    public function __construct(private readonly RecordAuditAction $audit) {}

    /**
     * @param  array{name: string, email: string, password: string, role: string, phone?: string|null, is_active?: bool}  $data
     */
    public function handle(array $data, ?int $createdBy = null): User
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => mb_strtolower($data['email']),
            'password' => $data['password'],
            'phone' => $data['phone'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        if (! in_array($data['role'], ['super_admin', 'admin', 'sales_manager', 'sales_agent', 'analyst', 'viewer'], true)) {
            throw new \InvalidArgumentException("Unknown role: {$data['role']}");
        }

        $user->assignRole($data['role']);

        $this->audit->handle(user: $createdBy, action: 'user.created', subject: $user, after: [
            'email' => $user->email,
            'role' => $data['role'],
        ]);

        return $user;
    }
}
