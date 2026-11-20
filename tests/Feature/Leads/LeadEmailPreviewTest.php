<?php

use App\Domain\Communications\Models\CommunicationMessage;
use App\Domain\Leads\Models\Lead;
use App\Livewire\Leads\Show;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

it('shows the inline email preview with the rendered banner on lead detail', function () {
    $admin = User::factory()->create([
        'name' => 'Dimas Aulia',
        'position' => 'CEO & Founder',
    ]);
    $admin->assignRole('super_admin');
    $this->actingAs($admin);

    $lead = Lead::factory()->create();
    $lead->contact->update([
        'email' => 'budi@example.test',
        'normalized_email' => 'budi@example.test',
    ]);

    Livewire::test(Show::class, ['lead' => $lead])
        ->set('emailSubject', 'Halo Budi')
        ->set('emailBody', 'Isi pesan untuk Budi.')
        ->assertSee('Pratinjau email')
        ->assertSee('Isi pesan untuk Budi.')
        ->assertSee('data:image/jpeg;base64,', false);
});

it('shows the rendered body of an existing draft from the lead message list', function () {
    $admin = User::factory()->create([
        'name' => 'Dimas Aulia',
        'position' => 'CEO & Founder',
    ]);
    $admin->assignRole('super_admin');
    $this->actingAs($admin);

    $lead = Lead::factory()->create();
    $lead->contact->update([
        'email' => 'budi@example.test',
        'normalized_email' => 'budi@example.test',
    ]);

    $draft = CommunicationMessage::create([
        'contact_id' => $lead->contact->id,
        'channel' => 'EMAIL',
        'direction' => 'outbound',
        'subject' => 'Draft lama',
        'body' => 'Isi draft lama.',
        'provider' => 'fake',
        'status' => 'draft',
        'sent_by' => $admin->id,
    ]);

    Livewire::test(Show::class, ['lead' => $lead])
        ->call('toggleDraftPreview', $draft->id)
        ->assertSee('Sembunyikan')
        ->assertSee('Isi draft lama.')
        ->assertSee('data:image/jpeg;base64,', false);
});
