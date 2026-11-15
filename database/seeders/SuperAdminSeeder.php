<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = 'enigmative@gmail.com';
        $password = getenv('SUPERADMIN_PASSWORD');

        if (! $password) {
            throw new \RuntimeException(
                'SUPERADMIN_PASSWORD is required.'
            );
        }

        $role = Role::findByName('super_admin', 'web');

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => 'Enigmative Super Admin',
                'password' => Hash::make($password),
                'is_active' => true,
            ]
        );

        $user->assignRole($role);

        $this->command->info("Super Admin ready: {$user->email}");
    }
}
