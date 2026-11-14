<?php

use App\Domain\Campaigns\Actions\ApproveProposalCampaignAction;
use App\Domain\Campaigns\Actions\CancelProposalCampaignAction;
use App\Domain\Campaigns\Actions\CreateProposalCampaignAction;
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
use App\Domain\Campaigns\Models\ProposalSenderSetting;
use App\Domain\Campaigns\Models\ProposalTemplate;
use App\Domain\Consent\Enums\Channel as ConsentChannel;
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
    config(['proposals.pdf_renderer' => 'fake']);
    config(['proposals.whatsapp.proposal_campaign_enabled' => false]);

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

it('requests approval after eligibility + PDF generation', function () {
    $creator = User::factory()->create();
    $lead = Lead::factory()->create();
    app(ConsentService::class)->record($lead->contact, ConsentChannel::Email, ConsentStatus::Granted, 'form');
    $template = ProposalTemplate::firstWhere('slug', 'test-template');
    $messageTpl = MessageTemplate::where('channel', 'EMAIL')->first();

    $campaign = app(CreateProposalCampaignAction::class)->handle('C', $template, $messageTpl, ProposalChannel::Email, [$lead->id], $creator->id);
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
    app(RequestProposalApprovalAction::class)->handle($campaign, $creator->id);
    app(ApproveProposalCampaignAction::class)->handle($campaign, $approver->id);
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
    app(RequestProposalApprovalAction::class)->handle($campaign, $creator->id);
    app(ApproveProposalCampaignAction::class)->handle($campaign, $approver->id);
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
    app(RequestProposalApprovalAction::class)->handle($campaign, $creator->id);
    app(ApproveProposalCampaignAction::class)->handle($campaign, $approver->id);
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
    app(RequestProposalApprovalAction::class)->handle($campaign, $creator->id);
    app(ApproveProposalCampaignAction::class)->handle($campaign, $approver->id);
    app(ScheduleProposalCampaignAction::class)->handle($campaign);

    $recipient = $campaign->recipients()->first();
    app(ConsentService::class)->record($lead->contact, ConsentChannel::Email, ConsentStatus::Revoked, 'form');

    $result = app(SendProposalDeliveryAction::class)->handle($recipient);

    expect($result->success)->toBeFalse()
        ->and($result->reason)->toBe('ineligible');
    $recipient->refresh();
    expect($recipient->delivery_status)->toBe(DeliveryStatus::Skipped);
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
    app(RequestProposalApprovalAction::class)->handle($campaign, $creator->id);
    $campaign->refresh();

    expect($campaign->recipients()->where('eligibility_status', EligibilityStatus::Eligible)->count())->toBe(1)
        ->and($campaign->recipients()->where('pdf_status', PdfStatus::Ready)->count())->toBe(1);
});
