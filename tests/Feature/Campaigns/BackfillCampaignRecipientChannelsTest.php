<?php

use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Models\CampaignRecipient;
use App\Domain\Campaigns\Models\MessageTemplate;
use App\Domain\Campaigns\Models\ProposalDeliveryAttempt;
use App\Domain\Leads\Models\Lead;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->user = User::factory()->create();
});

it('backfills legacy recipients into channel rows and is idempotent', function () {
    $template = MessageTemplate::factory()->create(['channel' => 'EMAIL']);
    $lead = Lead::factory()->create();

    $campaign = Campaign::create([
        'name' => 'Legacy',
        'type' => 'proposal',
        'channel' => 'EMAIL',
        'status' => 'completed',
        'created_by' => $this->user->id,
        'message_template_id' => $template->id,
        'timezone' => 'Asia/Jakarta',
        'business_days' => [1, 2, 3, 4, 5],
        'business_start' => '09:00',
        'business_end' => '17:00',
        'excluded_dates' => [],
        'rate_limit_per_hour' => 10,
        'min_spacing_seconds' => 120,
        'revision' => 1,
    ]);

    $recipientId = Str::uuid()->toString();
    $now = now();

    DB::table('campaign_recipients')->insert([
        'id' => $recipientId,
        'campaign_id' => $campaign->id,
        'contact_id' => $lead->contact_id,
        'lead_id' => $lead->id,
        'status' => 'pending',
        'eligibility_status' => 'eligible',
        'pdf_status' => 'READY',
        'delivery_status' => 'SENT',
        'idempotency_key' => 'proposal:'.$campaign->id.':'.$lead->contact_id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $attemptId = Str::uuid()->toString();
    DB::table('proposal_delivery_attempts')->insert([
        'id' => $attemptId,
        'campaign_recipient_id' => $recipientId,
        'channel' => 'EMAIL',
        'provider' => 'brevo',
        'status' => 'accepted',
        'attempt_number' => 1,
        'attempted_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $this->artisan('campaigns:backfill-recipient-channels')->assertSuccessful();

    $channelRow = DB::table('campaign_recipient_channels')
        ->where('campaign_recipient_id', $recipientId)
        ->first();

    expect($channelRow)->not()->toBeNull()
        ->and($channelRow->channel)->toBe('EMAIL')
        ->and($channelRow->delivery_status)->toBe('SENT')
        ->and($channelRow->idempotency_key)->toBe('proposal:'.$campaign->id.':'.$lead->contact_id.':EMAIL');

    expect(DB::table('proposal_delivery_attempts')->find($attemptId)->campaign_recipient_channel_id)
        ->toBe($channelRow->id);

    expect(Campaign::find($campaign->id)->audience_resolved_at)->not()->toBeNull();

    $this->artisan('campaigns:backfill-recipient-channels')->assertSuccessful();

    expect(DB::table('campaign_recipient_channels')
        ->where('campaign_recipient_id', $recipientId)->count())->toBe(1);
});
