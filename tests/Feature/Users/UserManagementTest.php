<?php

use App\Domain\Audit\Models\AuditLog;
use App\Livewire\Users\Form;
use App\Livewire\Users\Index;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

it('renders the user list for admins', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $this->actingAs($admin)
        ->get('/settings/users')
        ->assertOk()
        ->assertSee($admin->email);
});

it('forbids users without users.manage permission', function () {
    $viewer = User::factory()->create();
    $viewer->assignRole('viewer');

    $this->actingAs($viewer)
        ->get('/settings/users')
        ->assertForbidden();
});

it('creates a user with a role and writes an audit record', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    Livewire\Livewire::actingAs($admin)->test(Form::class)
        ->set('name', 'Sales Baru')
        ->set('email', 'sales@example.com')
        ->set('role', 'sales_agent')
        ->set('is_active', true)
        ->set('password', 'rahasia-123')
        ->set('password_confirmation', 'rahasia-123')
        ->call('save')
        ->assertHasNoErrors();

    $created = User::where('email', 'sales@example.com')->first();

    expect($created)->not->toBeNull()
        ->and($created->hasRole('sales_agent'))->toBeTrue()
        ->and(AuditLog::where('action', 'user.created')->where('subject_id', $created->id)->exists())->toBeTrue();
});

it('deactivates another user and records it', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $target = User::factory()->create(['is_active' => true]);
    $target->assignRole('viewer');

    Livewire\Livewire::actingAs($admin)->test(Index::class)
        ->call('toggleActive', $target->id)
        ->assertHasNoErrors();

    expect($target->fresh()->is_active)->toBeFalse()
        ->and(AuditLog::where('action', 'user.deactivated')->exists())->toBeTrue();
});

it('prevents self-deactivation', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    Livewire\Livewire::actingAs($admin)->test(Index::class)
        ->call('toggleActive', $admin->id)
        ->assertHasErrors('search');

    expect($admin->fresh()->is_active)->toBeTrue();
});

it('prevents deactivating the last active super admin', function () {
    $lastSuperAdmin = User::factory()->create(['is_active' => true]);
    $lastSuperAdmin->assignRole('super_admin');

    $inactiveSuperAdmin = User::factory()->create(['is_active' => false]);
    $inactiveSuperAdmin->assignRole('super_admin');

    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('admin');

    Livewire\Livewire::actingAs($admin)->test(Index::class)
        ->call('toggleActive', $lastSuperAdmin->id)
        ->assertHasErrors('search');

    expect($lastSuperAdmin->fresh()->is_active)->toBeTrue();
});
