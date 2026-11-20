<?php

use App\Livewire\Settings\Templates;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

it('previews email templates with the rendered footer banner', function () {
    $admin = User::factory()->create([
        'name' => 'Dimas Aulia',
        'position' => 'CEO & Founder',
    ]);
    $admin->assignRole('admin');
    $this->actingAs($admin);

    Livewire::test(Templates::class)
        ->set('tab', 'email')
        ->set('emailSubject', 'Halo {{contact_name}}')
        ->set('emailBody', 'Halo {{contact_name}}, salam dari {{company_name}}.')
        ->call('previewEmail')
        ->assertSee('Halo Budi Santoso')
        ->assertSee('data:image/jpeg;base64,', false);
});
