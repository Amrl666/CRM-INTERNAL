<?php

use App\Domain\Campaigns\Actions\CreateProposalCampaignAction;
use App\Domain\Campaigns\Actions\ResolveProposalAudienceAction;
use App\Domain\Campaigns\Enums\DeliveryStatus;
use App\Domain\Campaigns\Enums\EligibilityStatus;
use App\Domain\Campaigns\Enums\ProposalChannel;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Models\MessageTemplate;
use App\Domain\Campaigns\Models\ProposalSenderSetting;
use App\Domain\Campaigns\Models\ProposalTemplate;
use App\Domain\Consent\Enums\Channel;
use App\Domain\Consent\Enums\ConsentStatus;
use App\Domain\Consent\Services\ConsentService;
use App\Domain\Leads\Models\Lead;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
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
        'slug' => 'duplicate-template',
        'version' => '1.0',
        'view_path' => 'proposals.templates.website-school.v1',
    ]);

    $this->emailTemplate = MessageTemplate::factory()->create(['channel' => 'EMAIL']);
    $this->user = User::factory()->create();
});

function duplicateCampaign(Lead $lead, string $name, int $userId): Campaign
{
    app(ConsentService::class)->record($lead->contact, Channel::Email, ConsentStatus::Granted, 'test');

    $campaign = app(CreateProposalCampaignAction::class)->handle(
        $name,
        ProposalTemplate::firstWhere('slug', 'duplicate-template'),
        MessageTemplate::where('channel', 'EMAIL')->first(),
        ProposalChannel::Email,
        ['mode' => 'manual', 'manual_lead_ids' => [$lead->id]],
        $userId,
    );

    app(ResolveProposalAudienceAction::class)->handle($campaign);

    return $campaign->refresh();
}

it('excludes a contact with prior successful email but keeps whatsapp logic separate', function () {
    $lead = Lead::factory()->create();
    $lead->contact->update(['phone_e164' => '+6281234567890']);

    $first = duplicateCampaign($lead, 'First', $this->user->id);
    $first->recipientChannels()->first()->update([
        'delivery_status' => DeliveryStatus::Sent,
    ]);

    $second = duplicateCampaign($lead, 'Second', $this->user->id);

    $row = $second->recipientChannels()->first();

    expect($row->eligibility_status)->toBe(EligibilityStatus::Ineligible)
        ->and($row->delivery_status)->toBe(DeliveryStatus::Skipped)
        ->and($row->eligibility_reason)->toContain('Sudah pernah menerima outreach');
});

it('hard-excludes UNKNOWN but allows FAILED in a new campaign', function () {
    $leadUnknown = Lead::factory()->create();
    $first = duplicateCampaign($leadUnknown, 'Unknown', $this->user->id);
    $first->recipientChannels()->first()->update(['delivery_status' => DeliveryStatus::Unknown]);

    $second = duplicateCampaign($leadUnknown, 'Retry unknown', $this->user->id);
    expect($second->recipientChannels()->first()->delivery_status)->toBe(DeliveryStatus::Skipped);

    $leadFailed = Lead::factory()->create();
    $third = duplicateCampaign($leadFailed, 'Failed', $this->user->id);
    $third->recipientChannels()->first()->update(['delivery_status' => DeliveryStatus::Failed]);

    $fourth = duplicateCampaign($leadFailed, 'Retry failed', $this->user->id);
    expect($fourth->recipientChannels()->first()->delivery_status)->toBe(DeliveryStatus::Pending);
});

it('prevents two simultaneous PROCESSING sends for the same contact and channel', function () {
    $lead = Lead::factory()->create();
    $first = duplicateCampaign($lead, 'One', $this->user->id);
    $second = duplicateCampaign($lead, 'Two', $this->user->id);

    $firstRow = $first->recipientChannels()->first();
    $secondRow = $second->recipientChannels()->first();

    $firstRow->update(['delivery_status' => DeliveryStatus::Processing]);

    expect(fn () => $secondRow->update(['delivery_status' => DeliveryStatus::Processing]))
        ->toThrow(UniqueConstraintViolationException::class);
});
