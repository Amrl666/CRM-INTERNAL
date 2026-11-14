<?php

use App\Domain\Consent\Enums\Channel;
use App\Domain\Consent\Enums\ConsentStatus;
use App\Domain\Consent\Models\SuppressionEntry;
use App\Domain\Consent\Services\CommunicationEligibility;
use App\Domain\Consent\Services\ConsentService;
use App\Domain\Consent\Services\SuppressionService;
use App\Domain\Contacts\Models\Contact;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function contactWith(?string $email = 'lead@example.test', ?string $phone = null): Contact
{
    return Contact::factory()->create([
        'email' => $email,
        'normalized_email' => $email,
        'phone_e164' => $phone,
    ]);
}

it('keeps consent records append-only', function () {
    $contact = contactWith();
    $record = app(ConsentService::class)->record($contact, Channel::Email, ConsentStatus::Granted, 'form');

    expect(fn () => $record->update(['status' => ConsentStatus::Revoked]))
        ->toThrow(QueryException::class);
});

it('denies communication by default and allows only with granted consent', function () {
    $contact = contactWith();
    $eligibility = app(CommunicationEligibility::class);

    expect($eligibility->allows($contact, Channel::Email))->toBeFalse();

    app(ConsentService::class)->record($contact, Channel::Email, ConsentStatus::Granted, 'form');
    expect($eligibility->allows($contact->fresh(), Channel::Email))->toBeTrue();

    app(ConsentService::class)->record($contact->fresh(), Channel::Email, ConsentStatus::Revoked, 'unsubscribe');
    expect($eligibility->allows($contact->fresh(), Channel::Email))->toBeFalse();
});

it('blocks suppressed addresses and normalizes phone numbers', function () {
    $contact = contactWith(null, '+6281234567890');
    app(ConsentService::class)->record($contact, Channel::WhatsApp, ConsentStatus::Granted, 'chat');

    $suppression = app(SuppressionService::class);
    $suppression->suppress(Channel::WhatsApp, '0812-3456-7890', 'opt-out');

    expect(SuppressionEntry::count())->toBe(1)
        ->and($suppression->isSuppressed(Channel::WhatsApp, '+6281234567890'))->toBeTrue()
        ->and(app(CommunicationEligibility::class)->allows($contact, Channel::WhatsApp))->toBeFalse();
});

it('blocks whatsapp eligibility outside manual mode', function () {
    $contact = contactWith(null, '+6281234567890');
    app(ConsentService::class)->record($contact, Channel::WhatsApp, ConsentStatus::Granted, 'chat');

    config(['communications.whatsapp.outbound_mode' => 'auto']);

    expect(app(CommunicationEligibility::class)->allows($contact, Channel::WhatsApp))->toBeFalse();
});
