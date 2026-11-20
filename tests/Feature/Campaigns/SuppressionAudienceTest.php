<?php

use App\Domain\Campaigns\Actions\CreateProposalCampaignAction;
use App\Domain\Campaigns\Actions\GenerateProposalDocumentAction;
use App\Domain\Campaigns\Actions\RequestProposalApprovalAction;
use App\Domain\Campaigns\Actions\ResolveProposalAudienceAction;
use App\Domain\Campaigns\Actions\SendProposalChannelDeliveryAction;
use App\Domain\Campaigns\Enums\CampaignStatus;
use App\Domain\Campaigns\Enums\DeliveryStatus;
use App\Domain\Campaigns\Enums\EligibilityStatus;
use App\Domain\Campaigns\Enums\ProposalChannel;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Models\MessageTemplate;
use App\Domain\Campaigns\Models\ProposalSenderSetting;
use App\Domain\Campaigns\Models\ProposalTemplate;
use App\Domain\Communications\Contracts\ProposalEmailSender;
use App\Domain\Communications\Contracts\ProposalWhatsAppSender;
use App\Domain\Communications\Fakes\FakeProposalEmailSender;
use App\Domain\Communications\Fakes\FakeProposalWhatsAppSender;
use App\Domain\Consent\Enums\Channel;
use App\Domain\Consent\Enums\ConsentStatus;
use App\Domain\Consent\Services\ConsentService;
use App\Domain\Consent\Services\SuppressionService;
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
        'communications.whatsapp.outbound_mode' => 'manual',
        'proposals.pdf_renderer' => 'fake',
        'proposals.whatsapp.proposal_campaign_enabled' => true,
        'proposals.whatsapp.document_endpoint' => 'sendMedia',
    ]);

    ProposalTemplate::factory()->create([
        'slug' => 'suppression-template',
        'version' => '1.0',
        'view_path' => 'proposals.templates.website-school.v1',
    ]);

    $this->emailTemplate = MessageTemplate::factory()->create([
        'channel' => 'EMAIL',
        'subject' => 'Halo {{contact_name}}',
        'body_html' => '<p>{{company_name}}</p>',
        'body_text' => '{{company_name}}',
    ]);
    $this->whatsappTemplate = MessageTemplate::factory()->create([
        'channel' => 'WHATSAPP',
        'whatsapp_body' => 'Halo {{contact_name}}',
    ]);
    $this->creator = User::factory()->create();
    $this->creator->assignRole('super_admin');
    $this->approver = User::factory()->create();
    $this->approver->assignRole('super_admin');
});

/** Create and resolve a campaign for the given lead and channel selection. */
function suppressionCampaign(
    Lead $lead,
    ProposalChannel $channel,
    int $creatorId,
    MessageTemplate $emailTemplate,
    ?MessageTemplate $whatsappTemplate = null,
): Campaign {
    $settings = [];

    if ($channel === ProposalChannel::Both) {
        $settings = [
            'whatsapp_message_template_id' => $whatsappTemplate?->id,
            'whatsapp_message_template' => $whatsappTemplate,
        ];
    }

    $campaign = app(CreateProposalCampaignAction::class)->handle(
        'Suppression',
        ProposalTemplate::firstWhere('slug', 'suppression-template'),
        $channel === ProposalChannel::WhatsApp ? $whatsappTemplate : $emailTemplate,
        $channel,
        ['mode' => 'manual', 'manual_lead_ids' => [$lead->id]],
        $creatorId,
        $settings,
    );

    app(ResolveProposalAudienceAction::class)->handle($campaign);

    return $campaign->refresh();
}

/** Approve, generate PDF, and make the single channel row ready for delivery. */
function suppressionSendableCampaign(Lead $lead, int $creatorId, MessageTemplate $emailTemplate): array
{
    $campaign = suppressionCampaign($lead, ProposalChannel::Email, $creatorId, $emailTemplate);

    // The creator is a super admin, so requesting approval auto-approves.
    app(RequestProposalApprovalAction::class)->handle($campaign, $creatorId);

    $recipient = $campaign->recipients()->first();
    app(GenerateProposalDocumentAction::class)->handle($recipient);

    $campaign->update(['status' => CampaignStatus::Scheduled]);

    $channelRow = $campaign->recipientChannels()->first();
    $channelRow->update([
        'delivery_status' => DeliveryStatus::Scheduled,
        'scheduled_at' => now()->subMinute(),
    ]);

    return [$campaign->refresh(), $channelRow->refresh()];
}

it('excludes a suppressed email address from email campaign channel records', function () {
    $lead = Lead::factory()->create();
    app(ConsentService::class)->record($lead->contact, Channel::Email, ConsentStatus::Granted, 'test');
    app(SuppressionService::class)->suppress(Channel::Email, $lead->contact->email, 'opt-out');

    $campaign = suppressionCampaign($lead, ProposalChannel::Email, $this->creator->id, $this->emailTemplate);

    $row = $campaign->recipientChannels()->first();

    expect($campaign->recipients()->count())->toBe(1)
        ->and($row->channel)->toBe('EMAIL')
        ->and($row->eligibility_status)->toBe(EligibilityStatus::Ineligible)
        ->and($row->delivery_status)->toBe(DeliveryStatus::Skipped)
        ->and($row->eligibility_reason)->toContain('Alamat ada di daftar suppression.')
        ->and($campaign->recipientChannels()
            ->where('eligibility_status', EligibilityStatus::Eligible->value)->count())->toBe(0);
});

it('excludes a suppressed whatsapp number from whatsapp campaign channel records', function () {
    $lead = Lead::factory()->create();
    $lead->contact->update(['phone_e164' => '+6281234567890']);
    app(ConsentService::class)->record($lead->contact, Channel::WhatsApp, ConsentStatus::Granted, 'test');

    // Suppression is stored with an unnormalized local format on purpose.
    app(SuppressionService::class)->suppress(Channel::WhatsApp, '0812-3456-7890', 'opt-out');

    $campaign = suppressionCampaign(
        $lead,
        ProposalChannel::WhatsApp,
        $this->creator->id,
        $this->emailTemplate,
        $this->whatsappTemplate,
    );

    $row = $campaign->recipientChannels()->first();

    expect($row->channel)->toBe('WHATSAPP')
        ->and($row->eligibility_status)->toBe(EligibilityStatus::Ineligible)
        ->and($row->delivery_status)->toBe(DeliveryStatus::Skipped)
        ->and($row->eligibility_reason)->toContain('Alamat ada di daftar suppression.')
        ->and($campaign->recipientChannels()->count())->toBe(1);
});

it('does not exclude the other channel when only one channel is suppressed in a BOTH campaign', function () {
    $lead = Lead::factory()->create();
    $lead->contact->update(['phone_e164' => '+6281234567890']);
    app(ConsentService::class)->record($lead->contact, Channel::Email, ConsentStatus::Granted, 'test');
    app(ConsentService::class)->record($lead->contact, Channel::WhatsApp, ConsentStatus::Granted, 'test');
    app(SuppressionService::class)->suppress(Channel::Email, $lead->contact->email, 'opt-out');

    $campaign = suppressionCampaign(
        $lead,
        ProposalChannel::Both,
        $this->creator->id,
        $this->emailTemplate,
        $this->whatsappTemplate,
    );

    $emailRow = $campaign->recipientChannels()->where('channel', 'EMAIL')->first();
    $whatsappRow = $campaign->recipientChannels()->where('channel', 'WHATSAPP')->first();

    expect($emailRow->delivery_status)->toBe(DeliveryStatus::Skipped)
        ->and($emailRow->eligibility_reason)->toContain('Alamat ada di daftar suppression.')
        ->and($whatsappRow->eligibility_status)->toBe(EligibilityStatus::Eligible)
        ->and($whatsappRow->delivery_status)->toBe(DeliveryStatus::Pending)
        ->and($campaign->recipients()->first()->eligibility_status)->toBe(EligibilityStatus::Eligible);
});

it('blocks a suppressed email immediately before delivery even when it was eligible at resolution', function () {
    $lead = Lead::factory()->create();
    app(ConsentService::class)->record($lead->contact, Channel::Email, ConsentStatus::Granted, 'test');

    [$campaign, $channelRow] = suppressionSendableCampaign($lead, $this->creator->id, $this->emailTemplate);

    $fake = new FakeProposalEmailSender;
    $this->app->instance(ProposalEmailSender::class, $fake);

    // Suppression is added only after the audience snapshot was taken.
    app(SuppressionService::class)->suppress(Channel::Email, $lead->contact->email, 'opt-out-after-snapshot');

    $result = app(SendProposalChannelDeliveryAction::class)->handle($channelRow);

    expect($result->success)->toBeFalse()
        ->and($result->reason)->toBe('ineligible')
        ->and($fake->sent)->toHaveCount(0)
        ->and($campaign->recipientChannels()->first()->delivery_status)->toBe(DeliveryStatus::Skipped)
        ->and($campaign->recipientChannels()->first()->eligibility_reason)
        ->toContain('Alamat ada di daftar suppression.')
        ->and($campaign->recipientChannels()->first()->deliveryAttempts()->count())->toBe(0);
});

it('respects phone normalization when suppression is applied before delivery', function () {
    $lead = Lead::factory()->create();
    $lead->contact->update(['phone_e164' => '+6281234567890']);
    app(ConsentService::class)->record($lead->contact, Channel::WhatsApp, ConsentStatus::Granted, 'test');

    $campaign = suppressionCampaign(
        $lead,
        ProposalChannel::WhatsApp,
        $this->creator->id,
        $this->emailTemplate,
        $this->whatsappTemplate,
    );

    app(RequestProposalApprovalAction::class)->handle($campaign, $this->creator->id);
    app(GenerateProposalDocumentAction::class)->handle($campaign->recipients()->first());
    $campaign->update(['status' => CampaignStatus::Scheduled]);

    $channelRow = $campaign->recipientChannels()->first();
    $channelRow->update([
        'delivery_status' => DeliveryStatus::Scheduled,
        'scheduled_at' => now()->subMinute(),
    ]);

    app(SuppressionService::class)->suppress(Channel::WhatsApp, '0812-3456-7890', 'opt-out-after-snapshot');

    $fake = new FakeProposalWhatsAppSender;
    $this->app->instance(ProposalWhatsAppSender::class, $fake);

    $result = app(SendProposalChannelDeliveryAction::class)->handle($channelRow);

    expect($result->success)->toBeFalse()
        ->and($result->reason)->toBe('ineligible')
        ->and($fake->sent)->toHaveCount(0)
        ->and($channelRow->refresh()->delivery_status)->toBe(DeliveryStatus::Skipped)
        ->and($channelRow->eligibility_reason)->toContain('Alamat ada di daftar suppression.')
        ->and($channelRow->deliveryAttempts()->count())->toBe(0);
});
