<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleAndPermissionSeeder extends Seeder
{
    /** @var array<string, string[]> */
    private const ROLE_PERMISSIONS = [
        'super_admin' => [],
        'admin' => [
            'users.manage',
            'settings.manage',
            'leads.view',
            'leads.create',
            'leads.update',
            'leads.delete',
            'leads.import',
            'leads.export',
            'leads.assign',
            'campaigns.view',
            'campaigns.create',
            'campaigns.approve',
            'campaigns.send',
            'deals.view',
            'deals.create',
            'deals.update',
            'deals.close',
            'tasks.manage',
            'analytics.view',
        ],
        'sales_manager' => [
            'leads.view',
            'leads.create',
            'leads.update',
            'leads.assign',
            'campaigns.view',
            'campaigns.create',
            'deals.view',
            'deals.create',
            'deals.update',
            'deals.close',
            'tasks.manage',
            'analytics.view',
        ],
        'sales_agent' => [
            'leads.view',
            'leads.create',
            'leads.update',
            'campaigns.view',
            'deals.view',
            'deals.update',
            'analytics.view',
        ],
        'analyst' => [
            'analytics.view',
        ],
        'viewer' => [
            'leads.view',
            'campaigns.view',
            'deals.view',
            'analytics.view',
        ],
    ];

    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $allPermissions = collect(self::ROLE_PERMISSIONS)
            ->flatten()
            ->unique()
            ->values();

        foreach ($allPermissions as $permissionName) {
            Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ]);
        }

        foreach (self::ROLE_PERMISSIONS as $roleName => $permissionNames) {
            $role = Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
            ]);

            if ($roleName === 'super_admin') {
                $role->syncPermissions(Permission::all());

                continue;
            }

            $role->syncPermissions($permissionNames);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
