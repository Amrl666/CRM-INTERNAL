<?php

use App\Domain\Campaigns\Actions\CreateProposalCampaignAction;
use App\Domain\Campaigns\Actions\FinalizeProposalCampaignPreparationAction;
use App\Domain\Campaigns\Actions\GenerateProposalDocumentAction;
use App\Domain\Campaigns\Actions\PrepareProposalCampaignAction;
use App\Domain\Campaigns\Actions\RequestProposalApprovalAction;
use App\Domain\Campaigns\Actions\ResolveProposalAudienceAction;
use App\Domain\Campaigns\Actions\SendProposalChannelDeliveryAction;
use App\Domain\Campaigns\Enums\CampaignStatus;
use App\Domain\Campaigns\Enums\DeliveryStatus;
use App\Domain\Campaigns\Enums\EligibilityStatus;
use App\Domain\Campaigns\Enums\PdfStatus;
use App\Domain\Campaigns\Enums\ProposalChannel;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Models\MessageTemplate;
use App\Domain\Campaigns\Models\ProposalDeliveryAttempt;
use App\Domain\Campaigns\Models\ProposalSenderSetting;
use App\Domain\Campaigns\Models\ProposalTemplate;
use App\Domain\Communications\Contracts\ProposalEmailSender;
use App\Domain\Communications\Contracts\ProposalWhatsAppSender;
use App\Domain\Communications\DTOs\CommunicationResult;
use App\Domain\Communications\DTOs\ProposalWhatsAppData;
use App\Domain\Communications\Fakes\FakeProposalEmailSender;
use App\Domain\Communications\Models\CommunicationMessage;
use App\Domain\Consent\Enums\Channel;
use App\Domain\Consent\Enums\ConsentStatus;
use App\Domain\Consent\Services\ConsentService;
use App\Domain\Leads\Models\Lead;
use App\Jobs\SendProposalChannelDeliveryJob;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

/** WhatsApp provider that always reports a retryable rate-limit failure. */
final class RateLimitedWhatsAppSender implements ProposalWhatsAppSender
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
        return 'rate-limited';
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
        'slug' => 'idempotency-template',
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
});

/** Create, resolve, approve and prepare a single-channel campaign for delivery. */
function idempotentEmailCampaign(Lead $lead, int $creatorId, MessageTemplate $emailTemplate): array
{
    app(ConsentService::class)->record($lead->contact, Channel::Email, ConsentStatus::Granted, 'test');

    $campaign = app(CreateProposalCampaignAction::class)->handle(
        'Idempotency',
        ProposalTemplate::firstWhere('slug', 'idempotency-template'),
        $emailTemplate,
        ProposalChannel::Email,
        ['mode' => 'manual', 'manual_lead_ids' => [$lead->id]],
        $creatorId,
        [
            'business_days' => [1, 2, 3, 4, 5, 6, 7],
            'business_start' => '00:00',
            'business_end' => '23:59',
            'rate_limit_per_hour' => 120,
            'min_spacing_seconds' => 0,
        ],
    );

    app(ResolveProposalAudienceAction::class)->handle($campaign);
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

/** Create, resolve, approve and prepare a BOTH campaign for delivery. */
function idempotentBothCampaign(
    Lead $lead,
    int $creatorId,
    MessageTemplate $emailTemplate,
    MessageTemplate $whatsappTemplate,
): array {
    $lead->contact->update(['phone_e164' => '+6281234567890']);
    app(ConsentService::class)->record($lead->contact, Channel::Email, ConsentStatus::Granted, 'test');
    app(ConsentService::class)->record($lead->contact, Channel::WhatsApp, ConsentStatus::Granted, 'test');

    $campaign = app(CreateProposalCampaignAction::class)->handle(
        'Idempotency Both',
        ProposalTemplate::firstWhere('slug', 'idempotency-template'),
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
    app(RequestProposalApprovalAction::class)->handle($campaign, $creatorId);

    app(GenerateProposalDocumentAction::class)->handle($campaign->recipients()->first());
    $campaign->update(['status' => CampaignStatus::Scheduled]);

    foreach ($campaign->recipientChannels()->get() as $row) {
        $row->update([
            'delivery_status' => DeliveryStatus::Scheduled,
            'scheduled_at' => now()->subMinute(),
        ]);
    }

    return [$campaign->refresh(), $campaign->recipientChannels()->where('channel', 'EMAIL')->first(), $campaign->recipientChannels()->where('channel', 'WHATSAPP')->first()];
}

it('does not send twice when the same channel row is processed twice', function () {
    $lead = Lead::factory()->create();
    [, $channelRow] = idempotentEmailCampaign($lead, $this->creator->id, $this->emailTemplate);

    $fake = new FakeProposalEmailSender;
    $this->app->instance(ProposalEmailSender::class, $fake);

    $first = app(SendProposalChannelDeliveryAction::class)->handle($channelRow);
    $second = app(SendProposalChannelDeliveryAction::class)->handle($channelRow->refresh());

    expect($first->reason)->toBeNull()
        ->and($first->success)->toBeTrue()
        ->and($second->success)->toBeFalse()
        ->and($second->reason)->toBe('already_processed')
        ->and($fake->sent)->toHaveCount(1)
        ->and($channelRow->refresh()->deliveryAttempts()->count())->toBe(1)
        ->and(CommunicationMessage::query()
            ->where('campaign_id', $channelRow->campaign_id)->count())->toBe(1);
});

it('protects concurrent processing for the same contact and channel', function () {
    $lead = Lead::factory()->create();

    [$firstCampaign] = idempotentEmailCampaign($lead, $this->creator->id, $this->emailTemplate);

    app(ConsentService::class)->record($lead->contact, Channel::Email, ConsentStatus::Granted, 'test');
    $secondCampaign = app(CreateProposalCampaignAction::class)->handle(
        'Concurrent',
        ProposalTemplate::firstWhere('slug', 'idempotency-template'),
        $this->emailTemplate,
        ProposalChannel::Email,
        ['mode' => 'manual', 'manual_lead_ids' => [$lead->id]],
        $this->creator->id,
    );
    app(ResolveProposalAudienceAction::class)->handle($secondCampaign);

    $firstRow = $firstCampaign->recipientChannels()->first();
    $secondRow = $secondCampaign->recipientChannels()->first();

    $firstRow->update(['delivery_status' => DeliveryStatus::Processing]);

    expect(fn () => $secondRow->update(['delivery_status' => DeliveryStatus::Processing]))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('does not resend email when whatsapp fails and retries only whatsapp', function () {
    $lead = Lead::factory()->create();
    [, $emailRow, $whatsappRow] = idempotentBothCampaign(
        $lead,
        $this->creator->id,
        $this->emailTemplate,
        $this->whatsappTemplate,
    );

    $fakeEmail = new FakeProposalEmailSender;
    $fakeWhatsapp = new RateLimitedWhatsAppSender;
    $this->app->instance(ProposalEmailSender::class, $fakeEmail);
    $this->app->instance(ProposalWhatsAppSender::class, $fakeWhatsapp);

    app(SendProposalChannelDeliveryAction::class)->handle($emailRow);
    app(SendProposalChannelDeliveryAction::class)->handle($whatsappRow);

    // Move past the shared campaign spacing before retrying.
    Carbon::setTestNow(now()->addMinutes(5));
    $emailAgain = app(SendProposalChannelDeliveryAction::class)->handle($emailRow->refresh());
    app(SendProposalChannelDeliveryAction::class)->handle($whatsappRow->refresh());

    Carbon::setTestNow(now()->addMinutes(5));
    app(SendProposalChannelDeliveryAction::class)->handle($whatsappRow->refresh());
    Carbon::setTestNow();

    expect($emailRow->refresh()->delivery_status)->toBe(DeliveryStatus::Accepted)
        ->and($emailAgain->reason)->toBe('already_processed')
        ->and($fakeEmail->sent)->toHaveCount(1)
        ->and($emailRow->deliveryAttempts()->count())->toBe(1)
        ->and($whatsappRow->refresh()->delivery_status)->toBe(DeliveryStatus::Scheduled)
        ->and($fakeWhatsapp->sent)->toHaveCount(2)
        ->and(ProposalDeliveryAttempt::query()
            ->where('campaign_recipient_channel_id', $whatsappRow->id)->count())->toBe(2);
});

it('never automatically resends an UNKNOWN delivery', function () {
    $lead = Lead::factory()->create();
    [$campaign, $channelRow] = idempotentEmailCampaign($lead, $this->creator->id, $this->emailTemplate);

    $channelRow->update([
        'delivery_status' => DeliveryStatus::Unknown,
        'attempts' => 2,
        'processed_at' => now(),
    ]);

    $fake = new FakeProposalEmailSender;
    $this->app->instance(ProposalEmailSender::class, $fake);

    $result = app(SendProposalChannelDeliveryAction::class)->handle($channelRow->refresh());

    expect($result->success)->toBeFalse()
        ->and($result->reason)->toBe('already_processed')
        ->and($fake->sent)->toHaveCount(0)
        ->and($channelRow->refresh()->delivery_status)->toBe(DeliveryStatus::Unknown)
        ->and($channelRow->attempts)->toBe(2)
        ->and($channelRow->deliveryAttempts()->count())->toBe(0);

    // A new campaign for the same contact must also exclude the UNKNOWN channel.
    app(ConsentService::class)->record($lead->contact, Channel::Email, ConsentStatus::Granted, 'test');
    $next = app(CreateProposalCampaignAction::class)->handle(
        'After Unknown',
        ProposalTemplate::firstWhere('slug', 'idempotency-template'),
        $this->emailTemplate,
        ProposalChannel::Email,
        ['mode' => 'manual', 'manual_lead_ids' => [$lead->id]],
        $this->creator->id,
    );
    app(ResolveProposalAudienceAction::class)->handle($next);

    $nextRow = $next->recipientChannels()->first();
    expect($nextRow->delivery_status)->toBe(DeliveryStatus::Skipped)
        ->and($nextRow->eligibility_status)->toBe(EligibilityStatus::Ineligible)
        ->and($nextRow->eligibility_reason)->toContain('tidak dikirim ulang otomatis');
});

/** Create an approved campaign that still waits for audience resolution. */
function preparingCampaign(int $creatorId, MessageTemplate $emailTemplate): Campaign
{
    $lead = Lead::factory()->create();
    app(ConsentService::class)->record($lead->contact, Channel::Email, ConsentStatus::Granted, 'test');

    $campaign = app(CreateProposalCampaignAction::class)->handle(
        'Preparation',
        ProposalTemplate::firstWhere('slug', 'idempotency-template'),
        $emailTemplate,
        ProposalChannel::Email,
        ['mode' => 'manual', 'manual_lead_ids' => [$lead->id]],
        $creatorId,
    );

    app(RequestProposalApprovalAction::class)->handle($campaign, $creatorId);

    return $campaign->refresh();
}

it('moves an approved campaign to Preparing and does not schedule before resolution', function () {
    Queue::fake();
    $campaign = preparingCampaign($this->creator->id, $this->emailTemplate);

    expect($campaign->status)->toBe(CampaignStatus::Approved)
        ->and($campaign->audience_resolved_at)->toBeNull();

    $prepared = app(PrepareProposalCampaignAction::class)->handle($campaign, $this->creator->id);

    expect($prepared->status)->toBe(CampaignStatus::Preparing)
        ->and($prepared->audience_snapshot_at)->not()->toBeNull()
        ->and($prepared->audience_resolved_at)->toBeNull()
        ->and($prepared->recipients()->count())->toBe(0);

    // Finalization must not run ahead of audience resolution.
    app(FinalizeProposalCampaignPreparationAction::class)->handle($prepared->refresh(), $this->creator->id);

    expect($prepared->refresh()->status)->toBe(CampaignStatus::Preparing)
        ->and($prepared->recipients()->count())->toBe(0);
});

it('waits for PDF generation and schedules only once everything is ready', function () {
    Queue::fake();
    $campaign = preparingCampaign($this->creator->id, $this->emailTemplate);
    $campaign = app(PrepareProposalCampaignAction::class)->handle($campaign, $this->creator->id);

    app(ResolveProposalAudienceAction::class)->handle($campaign);
    $campaign->refresh();

    expect($campaign->audience_resolved_at)->not()->toBeNull()
        ->and($campaign->recipients()->count())->toBe(1)
        ->and($campaign->recipientChannels()->count())->toBe(1);

    $recipient = $campaign->recipients()->first();
    $recipient->update(['pdf_status' => PdfStatus::Generating]);

    app(FinalizeProposalCampaignPreparationAction::class)->handle($campaign->refresh(), $this->creator->id);

    expect($campaign->refresh()->status)->toBe(CampaignStatus::Preparing)
        ->and($campaign->recipientChannels()->first()->delivery_status)->toBe(DeliveryStatus::Pending);

    $recipient->update(['pdf_status' => PdfStatus::Ready]);
    app(FinalizeProposalCampaignPreparationAction::class)->handle($campaign->refresh(), $this->creator->id);

    expect($campaign->refresh()->status)->toBe(CampaignStatus::Scheduled)
        ->and($campaign->recipientChannels()->first()->delivery_status)->toBe(DeliveryStatus::Scheduled)
        ->and($campaign->recipientChannels()->first()->scheduled_at)->not()->toBeNull();

    Queue::assertPushed(SendProposalChannelDeliveryJob::class, 1);
});

it('repeated finalization does not duplicate recipients or scheduling side effects', function () {
    Queue::fake();
    $campaign = preparingCampaign($this->creator->id, $this->emailTemplate);
    $campaign = app(PrepareProposalCampaignAction::class)->handle($campaign, $this->creator->id);
    app(ResolveProposalAudienceAction::class)->handle($campaign);
    $campaign->recipients()->first()->update(['pdf_status' => PdfStatus::Ready]);

    app(FinalizeProposalCampaignPreparationAction::class)->handle($campaign->refresh(), $this->creator->id);

    $recipients = $campaign->recipients()->count();
    $channels = $campaign->recipientChannels()->count();
    $scheduledAt = (string) $campaign->recipientChannels()->first()->scheduled_at;

    app(FinalizeProposalCampaignPreparationAction::class)->handle($campaign->refresh(), $this->creator->id);

    expect($campaign->refresh()->status)->toBe(CampaignStatus::Scheduled)
        ->and($campaign->recipients()->count())->toBe($recipients)
        ->and($campaign->recipientChannels()->count())->toBe($channels)
        ->and((string) $campaign->recipientChannels()->first()->scheduled_at)->toBe($scheduledAt);

    Queue::assertPushed(SendProposalChannelDeliveryJob::class, 1);
});
