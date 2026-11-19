<?php

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Consent\Actions\AutoGrantLeadConsentAction;
use App\Domain\Consent\Enums\Channel;
use App\Domain\Consent\Enums\ConsentStatus;
use App\Domain\Consent\Models\ConsentRecord;
use App\Domain\Consent\Services\ConsentService;
use App\Domain\Consent\Services\SuppressionService;
use App\Domain\Leads\Actions\CreateLeadAction;
use App\Domain\Leads\Models\Lead;
use App\Livewire\Leads\Form as LeadForm;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function createLeadWithConsentIntent(User $user, array $overrides = [], bool $grantConsent = true): Lead
{
    return app(CreateLeadAction::class)->handle(array_merge([
        'company_name' => 'PT Consent Test',
        'contact_name' => 'Budi Santoso',
        'email' => 'Budi@Example.COM',
        'phone' => '081234567890',
    ], $overrides), $user->id, grantConsent: $grantConsent);
}

it('grants email and whatsapp consent when a manual lead has valid addresses', function () {
    $user = User::factory()->create();

    $lead = createLeadWithConsentIntent($user);

    $records = ConsentRecord::query()
        ->where('contact_id', $lead->contact_id)
        ->get()
        ->keyBy(fn (ConsentRecord $record): string => $record->channel->value);

    expect($records)->toHaveCount(2)
        ->and($records['EMAIL']->status)->toBe(ConsentStatus::Granted)
        ->and($records['EMAIL']->source)->toBe('lead form')
        ->and($records['EMAIL']->recorded_by)->toBe($user->id)
        ->and($records['WHATSAPP']->status)->toBe(ConsentStatus::Granted)
        ->and($records['WHATSAPP']->source)->toBe('lead form')
        ->and($records['WHATSAPP']->recorded_by)->toBe($user->id)
        ->and(AuditLog::where('action', 'consent.granted')->count())->toBe(2);

    $service = app(ConsentService::class);

    expect($service->currentStatus($lead->contact, Channel::Email))->toBe(ConsentStatus::Granted)
        ->and($service->currentStatus($lead->contact, Channel::WhatsApp))->toBe(ConsentStatus::Granted);
});

it('does not grant consent when the grant flag is absent', function () {
    $user = User::factory()->create();

    createLeadWithConsentIntent($user, grantConsent: false);

    expect(ConsentRecord::count())->toBe(0);
});

it('skips missing and invalid addresses', function () {
    $user = User::factory()->create();

    createLeadWithConsentIntent($user, [
        'email' => 'not-an-email',
        'phone' => '12345',
    ]);

    expect(ConsentRecord::count())->toBe(0);

    createLeadWithConsentIntent($user, [
        'company_name' => 'PT Empty Address',
        'email' => null,
        'phone' => null,
    ]);

    expect(ConsentRecord::count())->toBe(0);
});

it('grants consent for whichever valid channel is available', function () {
    $user = User::factory()->create();

    $emailLead = createLeadWithConsentIntent($user, [
        'company_name' => 'PT Email Only',
        'email' => 'email-only@example.com',
        'phone' => null,
    ]);

    $whatsAppLead = createLeadWithConsentIntent($user, [
        'company_name' => 'PT WhatsApp Only',
        'email' => null,
        'phone' => '081298765432',
    ]);

    expect(ConsentRecord::query()
        ->where('contact_id', $emailLead->contact_id)
        ->where('channel', Channel::Email)
        ->count())->toBe(1)
        ->and(ConsentRecord::query()
            ->where('contact_id', $emailLead->contact_id)
            ->where('channel', Channel::WhatsApp)
            ->count())->toBe(0)
        ->and(ConsentRecord::query()
            ->where('contact_id', $whatsAppLead->contact_id)
            ->where('channel', Channel::WhatsApp)
            ->count())->toBe(1)
        ->and(ConsentRecord::query()
            ->where('contact_id', $whatsAppLead->contact_id)
            ->where('channel', Channel::Email)
            ->count())->toBe(0);
});

it('does not grant consent for suppressed addresses', function () {
    $user = User::factory()->create();
    $suppression = app(SuppressionService::class);

    $suppression->suppress(Channel::Email, 'budi@example.com', 'bounce', $user->id);
    $suppression->suppress(Channel::WhatsApp, '+6281234567890', 'opt-out', $user->id);

    createLeadWithConsentIntent($user);

    expect(ConsentRecord::count())->toBe(0);
});

it('grants consent when a new lead is created from the manual form', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $user = User::factory()->create();
    $user->assignRole('admin');
    $this->actingAs($user);

    Livewire::test(LeadForm::class)
        ->set('company_name', 'PT Form Consent')
        ->set('contact_name', 'Budi Form')
        ->set('email', 'budi@example.com')
        ->set('phone', '081234567890')
        ->set('priority', 'medium')
        ->call('save')
        ->assertHasNoErrors();

    $lead = Lead::query()
        ->whereHas('company', fn ($query) => $query->where('normalized_name', 'pt form consent'))
        ->firstOrFail();

    expect(ConsentRecord::query()->where('contact_id', $lead->contact_id)->count())->toBe(2);
});

it('does not override revoked consent when auto-grant runs again', function () {
    $user = User::factory()->create();
    $lead = createLeadWithConsentIntent($user);
    $service = app(ConsentService::class);

    $service->record($lead->contact, Channel::Email, ConsentStatus::Revoked, 'unsubscribe');

    app(AutoGrantLeadConsentAction::class)->handle($lead->contact, $user->id);

    expect($service->currentStatus($lead->contact, Channel::Email))->toBe(ConsentStatus::Revoked)
        ->and(ConsentRecord::query()
            ->where('contact_id', $lead->contact_id)
            ->where('channel', Channel::WhatsApp)
            ->count())->toBe(1);
});
