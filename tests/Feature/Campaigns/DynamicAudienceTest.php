<?php

use App\Domain\Campaigns\Actions\CreateProposalCampaignAction;
use App\Domain\Campaigns\Actions\ResolveProposalAudienceAction;
use App\Domain\Campaigns\Enums\ProposalChannel;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Models\CampaignRecipient;
use App\Domain\Campaigns\Models\MessageTemplate;
use App\Domain\Campaigns\Models\ProposalSenderSetting;
use App\Domain\Campaigns\Models\ProposalTemplate;
use App\Domain\Campaigns\Services\AudiencePreview;
use App\Domain\Campaigns\Support\AudienceFilter;
use App\Domain\Consent\Enums\Channel;
use App\Domain\Consent\Enums\ConsentStatus;
use App\Domain\Consent\Services\ConsentService;
use App\Domain\Leads\Enums\LeadStatus;
use App\Domain\Leads\Models\ImportBatch;
use App\Domain\Leads\Models\ImportRow;
use App\Domain\Leads\Models\Lead;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    ProposalSenderSetting::firstOrCreate(
        ['is_active' => true],
        [
            'company_name' => 'Enigma Studio',
            'sender_name' => 'Dimas Aulia',
            'sender_email' => 'hello@enigma.test',
            'sender_website' => 'enigma.test',
        ]
    );
    config([
        'communications.email.default' => 'fake',
        'proposals.pdf_renderer' => 'fake',
        'proposals.whatsapp.proposal_campaign_enabled' => false,
    ]);

    ProposalTemplate::factory()->create([
        'slug' => 'audience-template',
        'version' => '1.0',
        'view_path' => 'proposals.templates.website-school.v1',
    ]);

    $this->emailTemplate = MessageTemplate::factory()->create(['channel' => 'EMAIL']);
    $this->creator = User::factory()->create();
});

it('stores filters at creation and does not snapshot recipients until start', function () {
    $lead = Lead::factory()->create(['status' => LeadStatus::Qualified]);

    $campaign = app(CreateProposalCampaignAction::class)->handle(
        'Filtered',
        ProposalTemplate::firstWhere('slug', 'audience-template'),
        $this->emailTemplate,
        ProposalChannel::Email,
        ['mode' => 'filter', 'lead_statuses' => ['QUALIFIED']],
        $this->creator->id,
    );

    expect($campaign->audience_filters['lead_statuses'])->toBe(['QUALIFIED'])
        ->and($campaign->recipients()->count())->toBe(0)
        ->and($campaign->audience_resolved_at)->toBeNull();

    app(ResolveProposalAudienceAction::class)->handle($campaign);

    expect($campaign->recipients()->count())->toBe(1)
        ->and($campaign->recipientChannels()->count())->toBe(1)
        ->and($campaign->audience_resolved_at)->not()->toBeNull();
});

it('resolves only matching statuses and deduplicates contacts', function () {
    $matching = Lead::factory()->count(2)->create(['status' => LeadStatus::Qualified]);
    Lead::factory()->create(['status' => LeadStatus::New]);

    $sharedContact = $matching[0]->contact;
    Lead::factory()->create([
        'status' => LeadStatus::Qualified,
        'company_id' => $matching[0]->company_id,
        'contact_id' => $sharedContact->id,
    ]);

    $campaign = app(CreateProposalCampaignAction::class)->handle(
        'Dedupe',
        ProposalTemplate::firstWhere('slug', 'audience-template'),
        $this->emailTemplate,
        ProposalChannel::Email,
        ['mode' => 'filter', 'lead_statuses' => ['QUALIFIED']],
        $this->creator->id,
    );

    app(ResolveProposalAudienceAction::class)->handle($campaign);

    expect($campaign->recipients()->count())->toBe(2)
        ->and($campaign->recipientChannels()->count())->toBe(2);
});

it('estimates audience without writing recipient rows', function () {
    $lead = Lead::factory()->create(['status' => LeadStatus::New]);
    app(ConsentService::class)->record($lead->contact, Channel::Email, ConsentStatus::Granted, 'test');

    $campaign = new Campaign([
        'channel' => ProposalChannel::Email,
        'timezone' => 'Asia/Jakarta',
        'business_start' => '09:00',
        'business_end' => '17:00',
        'rate_limit_per_hour' => 10,
        'min_spacing_seconds' => 120,
    ]);

    $filter = AudienceFilter::fromArray(['mode' => 'filter']);
    $estimate = app(AudiencePreview::class)->estimate($campaign, $filter, fresh: true);

    expect($estimate['estimated'])->toBeTrue()
        ->and($estimate['total_contacts'])->toBe(1)
        ->and($estimate['channels']['EMAIL']['eligible'])->toBe(1)
        ->and(CampaignRecipient::count())->toBe(0);
});

it('excludes leads created after the audience snapshot timestamp', function () {
    $before = Lead::factory()->create(['status' => LeadStatus::New]);

    $campaign = app(CreateProposalCampaignAction::class)->handle(
        'Cutoff',
        ProposalTemplate::firstWhere('slug', 'audience-template'),
        $this->emailTemplate,
        ProposalChannel::Email,
        ['mode' => 'filter', 'lead_statuses' => ['NEW']],
        $this->creator->id,
    );

    $campaign->update(['audience_snapshot_at' => now()]);

    Lead::factory()->create([
        'status' => LeadStatus::New,
        'created_at' => now()->addMinute(),
    ]);

    app(ResolveProposalAudienceAction::class)->handle($campaign);

    expect($campaign->recipients()->count())->toBe(1)
        ->and($campaign->recipients()->first()->contact_id)->toBe($before->contact_id);
});

it('filters by import batch through import_rows', function () {
    $batch = ImportBatch::create([
        'file_hash' => str_repeat('a', 64),
        'filename' => 'leads.csv',
    ]);

    $lead = Lead::factory()->create();
    ImportRow::create([
        'batch_id' => $batch->id,
        'row_number' => 1,
        'lead_id' => $lead->id,
        'status' => 'success',
    ]);
    Lead::factory()->create();

    $campaign = app(CreateProposalCampaignAction::class)->handle(
        'Batch',
        ProposalTemplate::firstWhere('slug', 'audience-template'),
        $this->emailTemplate,
        ProposalChannel::Email,
        ['mode' => 'filter', 'import_batch_ids' => [$batch->id]],
        $this->creator->id,
    );

    app(ResolveProposalAudienceAction::class)->handle($campaign);

    expect($campaign->recipients()->count())->toBe(1)
        ->and($campaign->recipients()->first()->lead_id)->toBe($lead->id);
});

it('resolves every contact in small chunks', function () {
    Lead::factory()->count(7)->create(['status' => LeadStatus::New]);

    $campaign = app(CreateProposalCampaignAction::class)->handle(
        'Chunked',
        ProposalTemplate::firstWhere('slug', 'audience-template'),
        $this->emailTemplate,
        ProposalChannel::Email,
        ['mode' => 'filter', 'lead_statuses' => ['NEW']],
        $this->creator->id,
    );

    config(['proposals.audience.chunk_size' => 2]);
    app(ResolveProposalAudienceAction::class)->handle($campaign, 2);

    expect($campaign->recipients()->count())->toBe(7)
        ->and($campaign->audience_resolved_at)->not()->toBeNull();
});
