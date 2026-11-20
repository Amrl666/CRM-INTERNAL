<?php

use App\Domain\Communications\Actions\CreateEmailDraftAction;
use App\Domain\Communications\Actions\SendApprovedEmailAction;
use App\Domain\Communications\Contracts\EmailProvider;
use App\Domain\Consent\Enums\Channel;
use App\Domain\Consent\Enums\ConsentStatus;
use App\Domain\Consent\Services\CommunicationEligibility;
use App\Domain\Consent\Services\ConsentService;
use App\Domain\Consent\Services\SuppressionService;
use App\Domain\Contacts\Models\Contact;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(RoleAndPermissionSeeder::class)->run();
});

function contactForEmail(?string $email = 'lead@example.test', ?string $phone = null): Contact
{
    return Contact::factory()->create([
        'email' => $email,
        'normalized_email' => $email,
        'phone_e164' => $phone,
    ]);
}

it('creates a draft email only when eligible and records the provider draft', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');
    $this->actingAs($user);
    config(['communications.email.default' => 'fake']);

    $contact = contactForEmail();
    app(ConsentService::class)->record($contact, Channel::Email, ConsentStatus::Granted, 'form');

    $message = app(CreateEmailDraftAction::class)->handle($contact, 'Halo', 'Isi email', $user->id);

    expect($message->status)->toBe('draft')
        ->and($message->provider_message_id)->not->toBeNull()
        ->and($message->channel)->toBe('EMAIL');
});

it('passes the creating user name and position as the email sender signature', function () {
    $user = User::factory()->create([
        'name' => 'Dimas Aulia',
        'position' => 'CEO & Founder',
    ]);
    $user->assignRole('admin');
    config(['communications.email.default' => 'fake']);

    $contact = contactForEmail();
    app(ConsentService::class)->record($contact, Channel::Email, ConsentStatus::Granted, 'form');

    app(CreateEmailDraftAction::class)->handle($contact, 'Halo', 'Isi email', $user->id);

    $draft = app(EmailProvider::class)->createdDrafts[0];

    expect($draft->senderName)->toBe('Dimas Aulia')
        ->and($draft->senderPosition)->toBe('CEO & Founder');
});

it('cancels a draft send when consent has been revoked', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');
    $this->actingAs($user);
    config(['communications.email.default' => 'fake']);

    $contact = contactForEmail();
    app(ConsentService::class)->record($contact, Channel::Email, ConsentStatus::Granted, 'form');
    $message = app(CreateEmailDraftAction::class)->handle($contact, 'Halo', 'Isi email', $user->id);
    app(ConsentService::class)->record($contact, Channel::Email, ConsentStatus::Revoked, 'opt-out');

    app(SendApprovedEmailAction::class)->handle($message->fresh(), $user->id);

    expect($message->fresh()->status)->toBe('cancelled');
});

it('blocks a draft when the address is globally suppressed', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');
    config(['communications.email.default' => 'fake']);

    $contact = contactForEmail('blocked@example.test');
    app(ConsentService::class)->record($contact, Channel::Email, ConsentStatus::Granted, 'form');
    app(SuppressionService::class)->suppress(Channel::Email, 'blocked@example.test', 'opt-out');

    $reasons = CommunicationEligibility::class;
    expect(app(CommunicationEligibility::class)->allows($contact, Channel::Email))->toBeFalse();
});
