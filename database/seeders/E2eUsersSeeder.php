<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class E2eUsersSeeder extends Seeder
{
    public function run(): void
    {
        $map = [
            'admin@example.com' => 'admin',
            'manager@e2e.test' => 'sales_manager',
            'agent@e2e.test' => 'sales_agent',
            'viewer@e2e.test' => 'viewer',
        ];

        foreach ($map as $email => $roleName) {
            $user = User::firstOrCreate(['email' => $email], [
                'name' => ucfirst($roleName),
                'password' => bcrypt('password'),
                'is_active' => true,
            ]);

            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $user->assignRole($role);
        }
    }
}
