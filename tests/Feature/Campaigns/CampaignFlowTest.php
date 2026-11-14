<?php

use App\Domain\Campaigns\Actions\ApproveCampaignAction;
use App\Domain\Campaigns\Actions\CreateCampaignAction;
use App\Domain\Campaigns\Actions\GenerateCampaignDraftsAction;
use App\Domain\Campaigns\Actions\RequestCampaignApprovalAction;
use App\Domain\Campaigns\Enums\CampaignStatus;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Models\EmailTemplate;
use App\Domain\Consent\Enums\Channel;
use App\Domain\Consent\Enums\ConsentStatus;
use App\Domain\Consent\Services\ConsentService;
use App\Domain\Leads\Models\Lead;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    config(['communications.email.default' => 'fake']);
});

it('runs the full approval flow and generates drafts only for consented recipients', function () {
    $manager = User::factory()->create();
    $manager->assignRole('sales_manager');
    $approver = User::factory()->create();
    $approver->assignRole('super_admin');

    $consented = Lead::factory()->create();
    $notConsented = Lead::factory()->create();

    $consent = app(ConsentService::class);
    $consent->record($consented->contact, Channel::Email, ConsentStatus::Granted, 'form');

    $template = EmailTemplate::create([
        'name' => 'Test Template',
        'subject' => 'Halo {{company_name}}',
        'body' => 'Halo {{contact_name}}, salam dari kami.',
    ]);

    $this->actingAs($manager);

    $campaign = app(CreateCampaignAction::class)->handle(
        'Campaign Q1',
        $template,
        [$consented->id, $notConsented->id],
        $manager->id,
    );

    expect($campaign->recipients()->count())->toBe(2)
        ->and($campaign->status)->toBe(CampaignStatus::Draft);

    app(RequestCampaignApprovalAction::class)->handle($campaign, $manager->id);
    expect($campaign->fresh()->status)->toBe(CampaignStatus::PendingApproval);

    $this->actingAs($approver);
    app(ApproveCampaignAction::class)->handle($campaign->fresh(), $approver->id);
    expect($campaign->fresh()->status)->toBe(CampaignStatus::Approved);

    app(GenerateCampaignDraftsAction::class)->handle($campaign->fresh(), $approver->id);

    $campaign->refresh();

    expect($campaign->status)->toBe(CampaignStatus::Completed)
        ->and($campaign->recipients()->where('status', 'draft_created')->count())->toBe(1)
        ->and($campaign->recipients()->where('status', 'skipped')->count())->toBe(1);
});

it('prevents the creator from approving their own campaign', function () {
    $manager = User::factory()->create();
    $manager->assignRole('sales_manager');

    $lead = Lead::factory()->create();
    $template = EmailTemplate::create(['name' => 'T', 'subject' => 'S', 'body' => 'B']);

    $campaign = app(CreateCampaignAction::class)->handle('C', $template, [$lead->id], $manager->id);
    app(RequestCampaignApprovalAction::class)->handle($campaign, $manager->id);

    expect(fn () => app(ApproveCampaignAction::class)->handle($campaign->fresh(), $manager->id))
        ->toThrow(InvalidArgumentException::class);
});

it('refuses to generate drafts before approval', function () {
    $template = EmailTemplate::create(['name' => 'T2', 'subject' => 'S', 'body' => 'B']);
    $campaign = Campaign::create([
        'name' => 'Draft',
        'template_id' => $template->id,
        'status' => CampaignStatus::Draft,
    ]);

    expect(fn () => app(GenerateCampaignDraftsAction::class)->handle($campaign))
        ->toThrow(InvalidArgumentException::class);
});
