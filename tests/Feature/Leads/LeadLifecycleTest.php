<?php

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Companies\Models\Company;
use App\Domain\Leads\Actions\ChangeLeadStatusAction;
use App\Domain\Leads\Actions\CreateLeadAction;
use App\Domain\Leads\Enums\LeadStatus;
use App\Domain\Leads\Models\Activity;
use App\Domain\Leads\Models\Lead;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

it('creates a lead with normalized contact data, activity and audit trail', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $this->actingAs($admin);

    $lead = app(CreateLeadAction::class)->handle([
        'company_name' => 'PT Contoh Jaya',
        'contact_name' => 'Budi Santoso',
        'email' => '  Budi@Example.COM ',
        'phone' => '081234567890',
        'industry' => 'SaaS',
        'source' => 'Referral',
    ], $admin->id);

    expect($lead->company->normalized_name)->toBe('pt contoh jaya')
        ->and($lead->contact->normalized_email)->toBe('budi@example.com')
        ->and($lead->contact->phone_e164)->toBe('+6281234567890')
        ->and($lead->status)->toBe(LeadStatus::New)
        ->and(Activity::where('lead_id', $lead->id)->where('type', 'lead.created')->exists())->toBeTrue()
        ->and(AuditLog::where('action', 'lead.created')->where('subject_id', $lead->id)->exists())->toBeTrue();
});

it('rejects invalid status transitions and accepts valid ones', function () {
    $lead = Lead::factory()->create(['status' => LeadStatus::New]);

    expect(fn () => app(ChangeLeadStatusAction::class)->handle($lead, LeadStatus::Won))
        ->toThrow(InvalidArgumentException::class);

    app(ChangeLeadStatusAction::class)->handle($lead->fresh(), LeadStatus::Qualified);

    expect($lead->fresh()->status)->toBe(LeadStatus::Qualified)
        ->and($lead->fresh()->qualified_at)->not->toBeNull();
});

it('scopes lead visibility for sales agents to their own leads', function () {
    $agent = User::factory()->create();
    $agent->assignRole('sales_agent');

    $own = Lead::factory()->create([
        'company_id' => Company::factory()->create(['name' => 'Milik Sendiri', 'normalized_name' => 'milik sendiri'])->id,
        'owner_id' => $agent->id,
    ]);
    $other = Lead::factory()->create([
        'company_id' => Company::factory()->create(['name' => 'Milik Orang', 'normalized_name' => 'milik orang'])->id,
    ]);

    $visible = Lead::query()->visibleTo($agent)->pluck('id');

    expect($visible)->toContain($own->id)
        ->and($visible)->not->toContain($other->id)
        ->and($agent->can('view', $other))->toBeFalse();
});
