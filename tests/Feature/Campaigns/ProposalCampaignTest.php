<?php

use App\Domain\Campaigns\Actions\ApproveProposalCampaignAction;
use App\Domain\Campaigns\Actions\CancelProposalCampaignAction;
use App\Domain\Campaigns\Actions\CreateProposalCampaignAction;
use App\Domain\Campaigns\Actions\GenerateProposalDocumentAction;
use App\Domain\Campaigns\Actions\PauseProposalCampaignAction;
use App\Domain\Campaigns\Actions\RefreshProposalEligibilityAction;
use App\Domain\Campaigns\Actions\RenderProposalMessageAction;
use App\Domain\Campaigns\Actions\RequestProposalApprovalAction;
use App\Domain\Campaigns\Actions\ScheduleProposalCampaignAction;
use App\Domain\Campaigns\Actions\SendProposalDeliveryAction;
use App\Domain\Campaigns\Enums\CampaignStatus;
use App\Domain\Campaigns\Enums\DeliveryStatus;
use App\Domain\Campaigns\Enums\EligibilityStatus;
use App\Domain\Campaigns\Enums\PdfStatus;
use App\Domain\Campaigns\Enums\ProposalChannel;
use App\Domain\Campaigns\Models\CampaignRecipient;
use App\Domain\Campaigns\Models\MessageTemplate;
use App\Domain\Campaigns\Models\ProposalDeliveryAttempt;
use App\Domain\Campaigns\Models\ProposalSenderSetting;
use App\Domain\Campaigns\Models\ProposalTemplate;
use App\Domain\Campaigns\Services\BusinessSchedule;
use App\Domain\Communications\Contracts\ProposalEmailSender;
use App\Domain\Communications\Fakes\FakeProposalEmailSender;
use App\Domain\Consent\Enums\Channel as ConsentChannel;
use App\Domain\Consent\Enums\ConsentStatus;
use App\Domain\Consent\Services\ConsentService;
use App\Domain\Leads\Models\Lead;
use App\Livewire\Proposals\Show;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    config(['communications.email.default' => 'fake']);
    config(['proposals.pdf_renderer' => 'fake']);
    config(['proposals.whatsapp.proposal_campaign_enabled' => false]);

    $this->prepareCampaignForApproval = function ($campaign): void {
        app(RefreshProposalEligibilityAction::class)->handle($campaign);

        $campaign->recipients()
            ->where('eligibility_status', EligibilityStatus::Eligible)
            ->get()
            ->each(function (CampaignRecipient $recipient): void {
                app(GenerateProposalDocumentAction::class)->handle($recipient);
            });
    };

    ProposalSenderSetting::firstOrCreate(
        ['is_active' => true],
        [
            'company_name' => 'Enigma Studio',
            'sender_name' => 'Dimas Aulia',
            'sender_email' => 'hello@enigma.test',
            'sender_website' => 'enigma.test',
        ]
    );

    ProposalTemplate::factory()->create([
        'slug' => 'test-template',
        'version' => '1.0',
        'view_path' => 'proposals.templates.website-school.v1',
        'required_variables' => ['company_name', 'contact_name', 'proposal_number', 'proposal_date', 'sender_company', 'sender_name', 'sender_email'],
    ]);

    MessageTemplate::factory()->create([
        'name' => 'Test Email Template',
        'channel' => 'EMAIL',
        'subject' => 'Proposal untuk {{company_name}}',
        'body_html' => '<p>Halo {{contact_name}},</p><p>Proposal {{proposal_number}}</p>',
        'body_text' => "Halo {{contact_name}},\nProposal {{proposal_number}}",
        'is_active' => true,
    ]);
});

it('creates a proposal campaign in draft', function () {
    $creator = User::factory()->create();
    $lead = Lead::factory()->create();
    app(ConsentService::class)->record($lead->contact, ConsentChannel::Email, ConsentStatus::Granted, 'form');
    $template = ProposalTemplate::firstWhere('slug', 'test-template');
    $messageTpl = MessageTemplate::where('channel', 'EMAIL')->first();

    $campaign = app(CreateProposalCampaignAction::class)->handle(
        'Q1', $template, $messageTpl, ProposalChannel::Email,
        [$lead->id], $creator->id,
    );

    expect($campaign->status)->toBe(CampaignStatus::Draft)
        ->and($campaign->type)->toBe('proposal')
        ->and($campaign->recipients()->count())->toBe(1);
});

it('computes recipient eligibility immediately when a proposal campaign is created', function () {
    $creator = User::factory()->create();
    $consented = Lead::factory()->create();
    $notConsented = Lead::factory()->create();
    app(ConsentService::class)->record($consented->contact, ConsentChannel::Email, ConsentStatus::Granted, 'form');
    $template = ProposalTemplate::firstWhere('slug', 'test-template');
    $messageTpl = MessageTemplate::where('channel', 'EMAIL')->first();

    $campaign = app(CreateProposalCampaignAction::class)->handle(
        'Immediate Eligibility',
        $template,
        $messageTpl,
        ProposalChannel::Email,
        [$consented->id, $notConsented->id],
        $creator->id,
    );

    $recipients = $campaign->recipients()->get()->keyBy('lead_id');

    expect($campaign->recipients()->where('eligibility_status', EligibilityStatus::Pending)->count())->toBe(0)
        ->and($recipients[$consented->id]->eligibility_status)->toBe(EligibilityStatus::Eligible)
        ->and($recipients[$consented->id]->delivery_status)->toBe(DeliveryStatus::Pending)
        ->and($recipients[$notConsented->id]->eligibility_status)->toBe(EligibilityStatus::Ineligible)
        ->and($recipients[$notConsented->id]->delivery_status)->toBe(DeliveryStatus::Skipped)
        ->and($recipients[$notConsented->id]->eligibility_reason)->toContain('Consent belum GRANTED');
});

it('removes the manual eligibility refresh and whatsapp warning from the outreach page', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $lead = Lead::factory()->create();
    $lead->contact->update(['phone_e164' => '+6281234567890']);
    app(ConsentService::class)->record($lead->contact, ConsentChannel::WhatsApp, ConsentStatus::Granted, 'form');

    config([
        'proposals.whatsapp.proposal_campaign_enabled' => true,
        'communications.whatsapp.outbound_mode' => 'manual',
    ]);

    $messageTpl = MessageTemplate::factory()->create([
        'name' => 'WhatsApp UI',
        'channel' => 'WHATSAPP',
        'whatsapp_body' => 'Halo {{contact_name}}',
    ]);

    $campaign = app(CreateProposalCampaignAction::class)->handle(
        'WhatsApp UI',
        ProposalTemplate::firstWhere('slug', 'test-template'),
        $messageTpl,
        ProposalChannel::WhatsApp,
        [$lead->id],
        $admin->id,
    );

    $this->actingAs($admin);

    Livewire::test(Show::class, ['campaign' => $campaign])
        ->assertDontSee('Periksa Ulang Eligibility')
        ->assertDontSee('WHATSAPP_PROPOSAL_CAMPAIGN_ENABLED')
        ->assertDontSee('kapabilitas terpisah')
        ->assertSee('Ajukan approval');
});

it('only shows approval actions when a campaign is in review', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $lead = Lead::factory()->create();
    app(ConsentService::class)->record($lead->contact, ConsentChannel::Email, ConsentStatus::Granted, 'form');

    $campaign = app(CreateProposalCampaignAction::class)->handle(
        'Review UI',
        ProposalTemplate::firstWhere('slug', 'test-template'),
        MessageTemplate::where('channel', 'EMAIL')->first(),
        ProposalChannel::Email,
        [$lead->id],
        $admin->id,
    );

    $campaign->update(['status' => CampaignStatus::Review]);

    $this->actingAs($admin);

    Livewire::test(Show::class, ['campaign' => $campaign])
        ->assertDontSee('Ajukan approval')
        ->assertDontSee('Review & Submit')
        ->assertSee('Setujui campaign');
});

it('prevents WhatsApp when feature flag disabled', function () {
    $creator = User::factory()->create();
    $lead = Lead::factory()->create();
    $template = ProposalTemplate::firstWhere('slug', 'test-template');
    $messageTpl = MessageTemplate::factory()->create(['name' => 'WA', 'channel' => 'WHATSAPP', 'whatsapp_body' => 'Hi {{contact_name}}']);

    expect(fn () => app(CreateProposalCampaignAction::class)->handle(
        'C', $template, $messageTpl, ProposalChannel::WhatsApp, [$lead->id], $creator->id,
    ))->toThrow(InvalidArgumentException::class);
});

it('rejects empty lead list', function () {
    $creator = User::factory()->create();
    $template = ProposalTemplate::firstWhere('slug', 'test-template');
    $messageTpl = MessageTemplate::where('channel', 'EMAIL')->first();

    expect(fn () => app(CreateProposalCampaignAction::class)->handle('X', $template, $messageTpl, ProposalChannel::Email, [], $creator->id))
        ->toThrow(InvalidArgumentException::class);
});

it('automatically approves proposal submitted by super admin', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super_admin');

    $lead = Lead::factory()->create();
    app(ConsentService::class)->record($lead->contact, ConsentChannel::Email, ConsentStatus::Granted, 'form');

    $template = ProposalTemplate::firstWhere('slug', 'test-template');
    $messageTpl = MessageTemplate::where('channel', 'EMAIL')->first();

    $campaign = app(CreateProposalCampaignAction::class)->handle(
        'Super Admin Proposal',
        $template,
        $messageTpl,
        ProposalChannel::Email,
        [$lead->id],
        $superAdmin->id,
    );

    ($this->prepareCampaignForApproval)($campaign);

    $result = app(RequestProposalApprovalAction::class)->handle($campaign, $superAdmin->id);

    expect($result->status)->toBe(CampaignStatus::Approved)
        ->and($result->approved_by)->toBe($superAdmin->id)
        ->and($result->approved_at)->not()->toBeNull()
        ->and($result->approved_revision)->toBe($result->revision)
        ->and($result->approval_snapshot)->not()->toBeNull()
        ->and($result->isApprovedForCurrentRevision())->toBeTrue();
});

it('requests approval after eligibility + PDF generation', function () {
    $creator = User::factory()->create();
    $lead = Lead::factory()->create();
    app(ConsentService::class)->record($lead->contact, ConsentChannel::Email, ConsentStatus::Granted, 'form');
    $template = ProposalTemplate::firstWhere('slug', 'test-template');
    $messageTpl = MessageTemplate::where('channel', 'EMAIL')->first();

    $campaign = app(CreateProposalCampaignAction::class)->handle('C', $template, $messageTpl, ProposalChannel::Email, [$lead->id], $creator->id);
    ($this->prepareCampaignForApproval)($campaign);
    app(RequestProposalApprovalAction::class)->handle($campaign, $creator->id);
    $campaign->refresh();

    expect($campaign->status)->toBe(CampaignStatus::Review);
});

it('blocks creator self-approval', function () {
    $creator = User::factory()->create();
    $lead = Lead::factory()->create();
    app(ConsentService::class)->record($lead->contact, ConsentChannel::Email, ConsentStatus::Granted, 'form');
    $template = ProposalTemplate::firstWhere('slug', 'test-template');
    $messageTpl = MessageTemplate::where('channel', 'EMAIL')->first();

    $campaign = app(CreateProposalCampaignAction::class)->handle('C', $template, $messageTpl, ProposalChannel::Email, [$lead->id], $creator->id);
    ($this->prepareCampaignForApproval)($campaign);
    app(RequestProposalApprovalAction::class)->handle($campaign, $creator->id);
    $campaign->refresh();

    expect(fn () => app(ApproveProposalCampaignAction::class)->handle($campaign, $creator->id))->toThrow(InvalidArgumentException::class);
});

it('allows approver different from creator and records snapshot', function () {
    $creator = User::factory()->create();
    $approver = User::factory()->create();
    $lead = Lead::factory()->create();
    app(ConsentService::class)->record($lead->contact, ConsentChannel::Email, ConsentStatus::Granted, 'form');
    $template = ProposalTemplate::firstWhere('slug', 'test-template');
    $messageTpl = MessageTemplate::where('channel', 'EMAIL')->first();

    $campaign = app(CreateProposalCampaignAction::class)->handle('C', $template, $messageTpl, ProposalChannel::Email, [$lead->id], $creator->id);
    ($this->prepareCampaignForApproval)($campaign);
    app(RequestProposalApprovalAction::class)->handle($campaign, $creator->id);
    $campaign->refresh();

    $result = app(ApproveProposalCampaignAction::class)->handle($campaign, $approver->id);

    expect($result->status)->toBe(CampaignStatus::Approved)
        ->and($result->approved_by)->toBe($approver->id)
        ->and($result->approved_revision)->toBe($result->revision)
        ->and($result->approval_snapshot)->not()->toBeNull();
});

it('schedules cannot start before approval', function () {
    $creator = User::factory()->create();
    $lead = Lead::factory()->create();
    app(ConsentService::class)->record($lead->contact, ConsentChannel::Email, ConsentStatus::Granted, 'form');
    $template = ProposalTemplate::firstWhere('slug', 'test-template');
    $messageTpl = MessageTemplate::where('channel', 'EMAIL')->first();

    $campaign = app(CreateProposalCampaignAction::class)->handle('U', $template, $messageTpl, ProposalChannel::Email, [$lead->id], $creator->id);
    expect(fn () => app(ScheduleProposalCampaignAction::class)->handle($campaign))->toThrow(InvalidArgumentException::class);
});

it('skips ineligible recipients on schedule (no consent)', function () {
    $creator = User::factory()->create();
    $approver = User::factory()->create();
    $consented = Lead::factory()->create();
    $notConsented = Lead::factory()->create();
    app(ConsentService::class)->record($consented->contact, ConsentChannel::Email, ConsentStatus::Granted, 'form');
    $template = ProposalTemplate::firstWhere('slug', 'test-template');
    $messageTpl = MessageTemplate::where('channel', 'EMAIL')->first();

    $campaign = app(CreateProposalCampaignAction::class)->handle('M', $template, $messageTpl, ProposalChannel::Email, [$consented->id, $notConsented->id], $creator->id);
    ($this->prepareCampaignForApproval)($campaign);
    app(RequestProposalApprovalAction::class)->handle($campaign, $creator->id);
    app(ApproveProposalCampaignAction::class)->handle($campaign, $approver->id);
    Queue::fake();
    app(ScheduleProposalCampaignAction::class)->handle($campaign);

    $eligible = $campaign->recipients()
        ->whereIn('lead_id', [$consented->id, $notConsented->id])
        ->get();

    expect($eligible->pluck('eligibility_status')->unique()->values()->toArray())
        ->toContain(EligibilityStatus::Eligible, EligibilityStatus::Ineligible);
});

it('pauses a scheduled campaign', function () {
    $creator = User::factory()->create();
    $approver = User::factory()->create();
    $lead = Lead::factory()->create();
    app(ConsentService::class)->record($lead->contact, ConsentChannel::Email, ConsentStatus::Granted, 'form');
    $template = ProposalTemplate::firstWhere('slug', 'test-template');
    $messageTpl = MessageTemplate::where('channel', 'EMAIL')->first();

    $campaign = app(CreateProposalCampaignAction::class)->handle('P', $template, $messageTpl, ProposalChannel::Email, [$lead->id], $creator->id);
    ($this->prepareCampaignForApproval)($campaign);
    app(RequestProposalApprovalAction::class)->handle($campaign, $creator->id);
    app(ApproveProposalCampaignAction::class)->handle($campaign, $approver->id);
    Queue::fake();
    app(ScheduleProposalCampaignAction::class)->handle($campaign);
    $campaign->refresh();

    expect($campaign->status)->toBe(CampaignStatus::Scheduled);

    app(PauseProposalCampaignAction::class)->handle($campaign, $creator->id);
    $campaign->refresh();
    expect($campaign->status)->toBe(CampaignStatus::Paused);
});

it('cancels a campaign and marks pending recipients cancelled', function () {
    $creator = User::factory()->create();
    $approver = User::factory()->create();
    $lead = Lead::factory()->create();
    app(ConsentService::class)->record($lead->contact, ConsentChannel::Email, ConsentStatus::Granted, 'form');
    $template = ProposalTemplate::firstWhere('slug', 'test-template');
    $messageTpl = MessageTemplate::where('channel', 'EMAIL')->first();

    $campaign = app(CreateProposalCampaignAction::class)->handle('Ca', $template, $messageTpl, ProposalChannel::Email, [$lead->id], $creator->id);
    ($this->prepareCampaignForApproval)($campaign);
    app(RequestProposalApprovalAction::class)->handle($campaign, $creator->id);
    app(ApproveProposalCampaignAction::class)->handle($campaign, $approver->id);
    Queue::fake();
    app(ScheduleProposalCampaignAction::class)->handle($campaign);
    $campaign->refresh();

    app(CancelProposalCampaignAction::class)->handle($campaign, $creator->id, 'Customer requested cancellation');
    $campaign->refresh();

    expect($campaign->status)->toBe(CampaignStatus::Cancelled);
    $campaign->recipients()->each(function (CampaignRecipient $r) {
        if ($r->delivery_status === DeliveryStatus::Pending || $r->delivery_status === DeliveryStatus::Scheduled) {
            expect($r->delivery_status)->toBe(DeliveryStatus::Cancelled);
        }
    });
});

it('renders message templates with real recipient data', function () {
    $creator = User::factory()->create();
    $lead = Lead::factory()->create();
    app(ConsentService::class)->record($lead->contact, ConsentChannel::Email, ConsentStatus::Granted, 'form');
    $template = ProposalTemplate::firstWhere('slug', 'test-template');
    $messageTpl = MessageTemplate::where('channel', 'EMAIL')->first();

    $campaign = app(CreateProposalCampaignAction::class)->handle('Rt', $template, $messageTpl, ProposalChannel::Email, [$lead->id], $creator->id);
    $recipient = $campaign->recipients()->first();
    app(RefreshProposalEligibilityAction::class)->handle($campaign);

    $result = app(RenderProposalMessageAction::class)->handle($recipient);

    expect($result->rendered_subject)->toContain((string) $lead->contact->company->name)
        ->and($result->rendered_body_text)->toContain((string) $lead->contact->full_name);
});

it('re-rechecks eligibility immediately before delivery send attempt', function () {
    $creator = User::factory()->create();
    $approver = User::factory()->create();
    $lead = Lead::factory()->create();
    app(ConsentService::class)->record($lead->contact, ConsentChannel::Email, ConsentStatus::Granted, 'form');
    $template = ProposalTemplate::firstWhere('slug', 'test-template');
    $messageTpl = MessageTemplate::where('channel', 'EMAIL')->first();

    $campaign = app(CreateProposalCampaignAction::class)->handle('Re', $template, $messageTpl, ProposalChannel::Email, [$lead->id], $creator->id);
    ($this->prepareCampaignForApproval)($campaign);
    app(RequestProposalApprovalAction::class)->handle($campaign, $creator->id);
    app(ApproveProposalCampaignAction::class)->handle($campaign, $approver->id);
    Queue::fake();
    app(ScheduleProposalCampaignAction::class)->handle($campaign);

    $recipient = $campaign->recipients()->first();
    app(ConsentService::class)->record($lead->contact, ConsentChannel::Email, ConsentStatus::Revoked, 'form');

    $result = app(SendProposalDeliveryAction::class)->handle($recipient);

    expect($result->success)->toBeFalse()
        ->and($result->reason)->toBe('ineligible');
    $recipient->refresh();
    expect($recipient->delivery_status)->toBe(DeliveryStatus::Skipped);
});

it('uses the outreach creator name and position for the email banner', function () {
    $creator = User::factory()->create([
        'name' => 'Rina Utami',
        'position' => 'Sales Lead',
    ]);
    $approver = User::factory()->create();
    $lead = Lead::factory()->create();
    app(ConsentService::class)->record($lead->contact, ConsentChannel::Email, ConsentStatus::Granted, 'form');

    $campaign = app(CreateProposalCampaignAction::class)->handle(
        'Banner Sender',
        ProposalTemplate::firstWhere('slug', 'test-template'),
        MessageTemplate::where('channel', 'EMAIL')->first(),
        ProposalChannel::Email,
        [$lead->id],
        $creator->id,
        [
            'timezone' => 'Asia/Jakarta',
            'business_days' => [1, 2, 3, 4, 5, 6, 7],
            'business_start' => '00:00',
            'business_end' => '23:59',
        ],
    );
    ($this->prepareCampaignForApproval)($campaign);
    app(RequestProposalApprovalAction::class)->handle($campaign, $creator->id);
    app(ApproveProposalCampaignAction::class)->handle($campaign, $approver->id);

    $campaign->update(['status' => CampaignStatus::Scheduled]);
    $recipient = $campaign->recipients()->first();
    $recipient->update([
        'delivery_status' => DeliveryStatus::Scheduled,
        'scheduled_at' => now()->subMinute(),
    ]);

    $fakeEmailSender = new FakeProposalEmailSender;
    $this->app->instance(ProposalEmailSender::class, $fakeEmailSender);

    $result = app(SendProposalDeliveryAction::class)->handle($recipient);

    expect($result->success)->toBeTrue()
        ->and($fakeEmailSender->sent)->toHaveCount(1)
        ->and($fakeEmailSender->sent[0]->senderName)->toBe('Rina Utami')
        ->and($fakeEmailSender->sent[0]->senderPosition)->toBe('Sales Lead');
});

it('uses idempotency key to prevent duplicate deliveries', function () {
    $creator = User::factory()->create();
    $lead = Lead::factory()->create();
    $template = ProposalTemplate::firstWhere('slug', 'test-template');
    $messageTpl = MessageTemplate::where('channel', 'EMAIL')->first();

    $campaign = app(CreateProposalCampaignAction::class)->handle('Id', $template, $messageTpl, ProposalChannel::Email, [$lead->id], $creator->id);
    $recipient = $campaign->recipients()->first();

    $keyBefore = $recipient->idempotency_key;
    expect($keyBefore)->not()->toBeNull();
    expect(str_starts_with((string) $keyBefore, 'proposal:'))->toBeTrue();
});

it('updates candidate eligible count when consent granted and pdf ready', function () {
    $creator = User::factory()->create();
    $lead = Lead::factory()->create();
    app(ConsentService::class)->record($lead->contact, ConsentChannel::Email, ConsentStatus::Granted, 'form');
    $template = ProposalTemplate::firstWhere('slug', 'test-template');
    $messageTpl = MessageTemplate::where('channel', 'EMAIL')->first();

    $campaign = app(CreateProposalCampaignAction::class)->handle('Ce', $template, $messageTpl, ProposalChannel::Email, [$lead->id], $creator->id);
    ($this->prepareCampaignForApproval)($campaign);
    app(RequestProposalApprovalAction::class)->handle($campaign, $creator->id);
    $campaign->refresh();

    expect($campaign->recipients()->where('eligibility_status', EligibilityStatus::Eligible)->count())->toBe(1)
        ->and($campaign->recipients()->where('pdf_status', PdfStatus::Ready)->count())->toBe(1);
});

it('counts all actual deliveries in the current local hour when planning new slots', function () {
    $creator = User::factory()->create();

    $leads = collect(range(1, 3))
        ->map(fn () => Lead::factory()->create());

    $template = ProposalTemplate::firstWhere('slug', 'test-template');
    $messageTpl = MessageTemplate::where('channel', 'EMAIL')->first();

    $campaign = app(CreateProposalCampaignAction::class)->handle(
        'Rate Limit Regression',
        $template,
        $messageTpl,
        ProposalChannel::Email,
        $leads->pluck('id')->all(),
        $creator->id,
        [
            'timezone' => 'Asia/Jakarta',
            'business_days' => [1, 2, 3, 4, 5, 6, 7],
            'business_start' => '09:00',
            'business_end' => '17:00',
            'rate_limit_per_hour' => 2,
            'min_spacing_seconds' => 0,
        ],
    );

    $recipients = $campaign->recipients()
        ->orderBy('created_at')
        ->get();

    foreach ([0, 1] as $index) {
        $recipient = $recipients[$index];

        $recipient->update([
            'delivery_status' => DeliveryStatus::Accepted,
            'processed_at' => CarbonImmutable::parse(
                '2026-11-18 09:'.($index === 0 ? '00' : '05').':00',
                'Asia/Jakarta',
            )->utc(),
        ]);

        ProposalDeliveryAttempt::create([
            'campaign_recipient_id' => $recipient->id,
            'channel' => 'EMAIL',
            'provider' => 'test',
            'status' => 'accepted',
            'attempt_number' => 1,
            'attempted_at' => CarbonImmutable::parse(
                '2026-11-18 09:'.($index === 0 ? '00' : '05').':00',
                'Asia/Jakarta',
            )->utc(),
        ]);
    }

    $plan = app(BusinessSchedule::class)->plan(
        $campaign,
        [$recipients[2]->id],
        CarbonImmutable::parse('2026-11-18 09:45:00', 'Asia/Jakarta'),
    );

    expect($plan)
        ->toHaveKey($recipients[2]->id)
        ->and($plan[$recipients[2]->id]->setTimezone('Asia/Jakarta')->format('Y-m-d H:i:s'))
        ->toBe('2026-11-18 10:00:00');
});

it('respects minimum spacing after an actual delivery attempt', function () {
    $creator = User::factory()->create();

    $leads = collect(range(1, 2))
        ->map(fn () => Lead::factory()->create());

    $campaign = app(CreateProposalCampaignAction::class)->handle(
        'Spacing Regression',
        ProposalTemplate::firstWhere('slug', 'test-template'),
        MessageTemplate::where('channel', 'EMAIL')->first(),
        ProposalChannel::Email,
        $leads->pluck('id')->all(),
        $creator->id,
        [
            'timezone' => 'Asia/Jakarta',
            'business_days' => [1, 2, 3, 4, 5, 6, 7],
            'business_start' => '09:00',
            'business_end' => '17:00',
            'rate_limit_per_hour' => 120,
            'min_spacing_seconds' => 600,
        ],
    );

    $recipients = $campaign->recipients()->orderBy('created_at')->get();

    $attemptedAt = CarbonImmutable::parse(
        '2026-11-18 09:00:00',
        'Asia/Jakarta',
    );

    ProposalDeliveryAttempt::create([
        'campaign_recipient_id' => $recipients[0]->id,
        'channel' => 'EMAIL',
        'provider' => 'test',
        'status' => 'accepted',
        'attempt_number' => 1,
        'attempted_at' => $attemptedAt->utc(),
    ]);

    $plan = app(BusinessSchedule::class)->plan(
        $campaign,
        [$recipients[1]->id],
        CarbonImmutable::parse('2026-11-18 09:05:00', 'Asia/Jakarta'),
    );

    expect(
        $plan[$recipients[1]->id]
            ->setTimezone('Asia/Jakarta')
            ->format('Y-m-d H:i:s')
    )->toBe('2026-11-18 09:10:00');
});

it('respects existing scheduled reservations when planning new slots', function () {
    $creator = User::factory()->create();

    $leads = collect(range(1, 2))
        ->map(fn () => Lead::factory()->create());

    $campaign = app(CreateProposalCampaignAction::class)->handle(
        'Reservation Regression',
        ProposalTemplate::firstWhere('slug', 'test-template'),
        MessageTemplate::where('channel', 'EMAIL')->first(),
        ProposalChannel::Email,
        $leads->pluck('id')->all(),
        $creator->id,
        [
            'timezone' => 'Asia/Jakarta',
            'business_days' => [1, 2, 3, 4, 5, 6, 7],
            'business_start' => '09:00',
            'business_end' => '17:00',
            'rate_limit_per_hour' => 120,
            'min_spacing_seconds' => 600,
        ],
    );

    $recipients = $campaign->recipients()->orderBy('created_at')->get();

    $recipients[0]->update([
        'delivery_status' => DeliveryStatus::Scheduled,
        'scheduled_at' => CarbonImmutable::parse(
            '2026-11-18 09:00:00',
            'Asia/Jakarta',
        )->utc(),
    ]);

    $plan = app(BusinessSchedule::class)->plan(
        $campaign,
        [$recipients[1]->id],
        CarbonImmutable::parse('2026-11-18 09:05:00', 'Asia/Jakarta'),
    );

    expect(
        $plan[$recipients[1]->id]
            ->setTimezone('Asia/Jakarta')
            ->format('Y-m-d H:i:s')
    )->toBe('2026-11-18 09:10:00');
});
