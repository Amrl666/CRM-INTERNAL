<?php

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

it('allows users with settings.manage to open settings', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get('/settings')
        ->assertOk()
        ->assertSee('Pengaturan');
});

it('forbids sales agents from opening settings', function () {
    $agent = User::factory()->create();
    $agent->assignRole('sales_agent');

    $this->actingAs($agent)
        ->get('/settings')
        ->assertForbidden();
});

it('allows analysts to open analytics without deal management access', function () {
    $analyst = User::factory()->create();
    $analyst->assignRole('analyst');

    $this->actingAs($analyst)
        ->get('/analytics/overview')
        ->assertOk()
        ->assertSee('Analitik');
});

it('forbids users without analytics permission from opening analytics', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/analytics/overview')
        ->assertForbidden();
});
