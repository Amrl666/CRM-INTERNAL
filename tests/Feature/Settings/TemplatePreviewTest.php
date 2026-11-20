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

it('defaults to the email draft tab', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin);

    expect(Livewire::test(Templates::class)->get('tab'))->toBe('email');
});

it('shows an error when the footer banner cannot be generated', function () {
    config(['communications.email_banner.path' => public_path('images/missing-email-banner.jpg')]);

    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin);

    Livewire::test(Templates::class)
        ->set('tab', 'email')
        ->set('emailSubject', 'Halo')
        ->set('emailBody', 'Isi pesan.')
        ->call('previewEmail')
        ->assertSee('Banner email gagal dibuat');
});

it('previews email templates with the rendered footer banner', function () {
    $admin = User::factory()->create([
        'name' => 'Dimas Aulia',
        'position' => 'CEO & Founder',
    ]);
    $admin->assignRole('admin');
    $this->actingAs($admin);

    $component = Livewire::test(Templates::class)
        ->set('tab', 'email')
        ->set('emailSubject', 'Halo {{contact_name}}')
        ->set('emailBody', 'Halo {{contact_name}}, salam dari {{company_name}}.')
        ->call('previewEmail')
        ->assertSee('Halo Budi Santoso')
        ->assertSee('Footer banner')
        ->assertSee('/storage/email-banners/', false);

    expect($component->get('previewBannerUrl'))->toContain('/storage/email-banners/')
        ->and($component->get('previewBody'))->not->toContain('email-banners');
});
