<?php

use App\Domain\Campaigns\Actions\ApproveProposalCampaignAction;
use App\Domain\Campaigns\Actions\CreateProposalCampaignAction;
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
use App\Domain\Communications\DTOs\CommunicationResult;
use App\Domain\Communications\DTOs\ProposalWhatsAppData;
use App\Domain\Communications\Fakes\FakeProposalEmailSender;
use App\Domain\Consent\Enums\Channel;
use App\Domain\Consent\Enums\ConsentStatus;
use App\Domain\Consent\Services\ConsentService;
use App\Domain\Leads\Models\Lead;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

final class FailingWhatsAppSender implements ProposalWhatsAppSender
{
    /** @var ProposalWhatsAppData[] */
    public array $sent = [];

    public function isEnabled(): bool
    {
        return true;
    }

    public function sendProposalDocument(ProposalWhatsAppData $message): CommunicationResult
    {
        $this->sent[] = $message;

        return CommunicationResult::fail('evolution_rate_limited');
    }

    public function getProviderName(): string
    {
        return 'failing';
    }
}

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
        'slug' => 'multi-template',
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
    $this->creator = User::factory()->create(['name' => 'Creator Utama', 'position' => 'Sales Lead']);
    $this->approver = User::factory()->create();
    $this->approver->assignRole('super_admin');
});

function multiCampaign(
    Lead $lead,
    MessageTemplate $emailTemplate,
    MessageTemplate $whatsappTemplate,
    int $creatorId,
    bool $emailConsent = true,
    bool $whatsappConsent = true,
): Campaign {
    if ($emailConsent) {
        app(ConsentService::class)->record($lead->contact, Channel::Email, ConsentStatus::Granted, 'test');
    }
    if ($whatsappConsent) {
        $lead->contact->update(['phone_e164' => '+6281234567890']);
        app(ConsentService::class)->record($lead->contact, Channel::WhatsApp, ConsentStatus::Granted, 'test');
    }

    $campaign = app(CreateProposalCampaignAction::class)->handle(
        'BOTH campaign',
        ProposalTemplate::firstWhere('slug', 'multi-template'),
        $emailTemplate,
        ProposalChannel::Both,
        ['mode' => 'manual', 'manual_lead_ids' => [$lead->id]],
        $creatorId,
        [
            'whatsapp_message_template_id' => $whatsappTemplate->id,
            'whatsapp_message_template' => $whatsappTemplate,
            'business_days' => [1, 2, 3, 4, 5, 6, 7],
            'business_start' => '00:00',
            'business_end' => '23:59',
            'rate_limit_per_hour' => 120,
            'min_spacing_seconds' => 0,
        ],
    );

    app(ResolveProposalAudienceAction::class)->handle($campaign);

    return $campaign->refresh();
}

it('creates independent channel rows and skips the ineligible channel', function () {
    $lead = Lead::factory()->create();
    $campaign = multiCampaign($lead, $this->emailTemplate, $this->whatsappTemplate, $this->creator->id, emailConsent: true, whatsappConsent: false);

    $email = $campaign->recipientChannels()->where('channel', 'EMAIL')->first();
    $whatsapp = $campaign->recipientChannels()->where('channel', 'WHATSAPP')->first();

    expect($email->eligibility_status)->toBe(EligibilityStatus::Eligible)
        ->and($email->delivery_status)->toBe(DeliveryStatus::Pending)
        ->and($whatsapp->eligibility_status)->toBe(EligibilityStatus::Ineligible)
        ->and($whatsapp->delivery_status)->toBe(DeliveryStatus::Skipped)
        ->and($whatsapp->eligibility_reason)->toContain('Consent');
});

it('creates two eligible rows when both channels qualify', function () {
    $lead = Lead::factory()->create();
    $campaign = multiCampaign($lead, $this->emailTemplate, $this->whatsappTemplate, $this->creator->id);

    expect($campaign->recipientChannels()->where('eligibility_status', EligibilityStatus::Eligible->value)->count())->toBe(2)
        ->and($campaign->recipientChannels()->pluck('id')->unique()->count())->toBe(2);
});

it('sends email with creator banner when both channels are eligible', function () {
    $lead = Lead::factory()->create();
    $campaign = multiCampaign($lead, $this->emailTemplate, $this->whatsappTemplate, $this->creator->id);

    app(RequestProposalApprovalAction::class)->handle($campaign, $this->creator->id);
    app(ApproveProposalCampaignAction::class)->handle($campaign, $this->approver->id);

    $campaign->update(['status' => CampaignStatus::Scheduled]);

    $emailRow = $campaign->recipientChannels()->where('channel', 'EMAIL')->first();
    $emailRow->update([
        'delivery_status' => DeliveryStatus::Scheduled,
        'scheduled_at' => now()->subMinute(),
    ]);

    $fake = new FakeProposalEmailSender;
    $this->app->instance(ProposalEmailSender::class, $fake);

    $result = app(SendProposalChannelDeliveryAction::class)->handle($emailRow);

    expect($result->success)->toBeTrue()
        ->and($fake->sent)->toHaveCount(1)
        ->and($fake->sent[0]->senderName)->toBe('Creator Utama')
        ->and($fake->sent[0]->senderPosition)->toBe('Sales Lead')
        ->and($campaign->recipientChannels()->where('channel', 'WHATSAPP')->first()->delivery_status)
        ->toBe(DeliveryStatus::Pending);
});

it('retries only the failed channel', function () {
    $lead = Lead::factory()->create();
    $campaign = multiCampaign($lead, $this->emailTemplate, $this->whatsappTemplate, $this->creator->id);

    app(RequestProposalApprovalAction::class)->handle($campaign, $this->creator->id);
    app(ApproveProposalCampaignAction::class)->handle($campaign, $this->approver->id);
    $campaign->update(['status' => CampaignStatus::Scheduled]);

    $emailRow = $campaign->recipientChannels()->where('channel', 'EMAIL')->first();
    $whatsappRow = $campaign->recipientChannels()->where('channel', 'WHATSAPP')->first();

    foreach ([$emailRow, $whatsappRow] as $row) {
        $row->update([
            'delivery_status' => DeliveryStatus::Scheduled,
            'scheduled_at' => now()->subMinute(),
        ]);
    }

    $this->app->instance(ProposalEmailSender::class, new FakeProposalEmailSender);
    $failingWhatsapp = new FailingWhatsAppSender;
    $this->app->instance(ProposalWhatsAppSender::class, $failingWhatsapp);

    app(SendProposalChannelDeliveryAction::class)->handle($emailRow->refresh());

    // Move the clock forward so the shared campaign spacing does not push the
    // WhatsApp slot into the future after the Email attempt.
    Carbon::setTestNow(now()->addMinutes(5));

    app(SendProposalChannelDeliveryAction::class)->handle($whatsappRow->refresh());
    Carbon::setTestNow();

    expect($emailRow->refresh()->delivery_status)->toBe(DeliveryStatus::Accepted)
        ->and($whatsappRow->refresh()->delivery_status)->toBe(DeliveryStatus::Scheduled)
        ->and($emailRow->deliveryAttempts()->count())->toBe(1)
        ->and($whatsappRow->deliveryAttempts()->count())->toBe(1);
});
