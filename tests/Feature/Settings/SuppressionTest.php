<?php

use App\Domain\Consent\Models\SuppressionEntry;
use App\Livewire\Settings\Suppression;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

it('allows admin to add a suppression and lists it', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    Livewire::actingAs($admin)
        ->test(Suppression::class)
        ->set('address', 'banned@example.test')
        ->set('channel', 'EMAIL')
        ->set('reason', 'unsubscribe')
        ->call('add')
        ->assertHasNoErrors();

    expect(SuppressionEntry::where('normalized_address', 'banned@example.test')->count())->toBe(1);
});

it('forbids non-admin from accessing suppression page', function () {
    $agent = User::factory()->create();
    $agent->assignRole('sales_agent');

    $this->actingAs($agent)
        ->get(route('settings.suppression'))
        ->assertForbidden();
});
