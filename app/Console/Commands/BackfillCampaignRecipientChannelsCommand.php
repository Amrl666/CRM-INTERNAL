<?php

namespace App\Console\Commands;

use App\Domain\Campaigns\Enums\ProposalChannel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BackfillCampaignRecipientChannelsCommand extends Command
{
    protected $signature = 'campaigns:backfill-recipient-channels
        {--chunk=500 : Number of recipients per batch}
        {--campaign= : Limit to one campaign id}
        {--dry-run : Report what would be created without writing}';

    protected $description = 'Backfill campaign_recipient_channels from legacy proposal recipients';

    public function handle(): int
    {
        $chunk = max(1, (int) $this->option('chunk'));
        $campaignId = $this->option('campaign');
        $dryRun = (bool) $this->option('dry-run');

        $query = DB::table('campaign_recipients as cr')
            ->join('campaigns as c', 'c.id', '=', 'cr.campaign_id')
            ->where('c.type', 'proposal')
            ->select([
                'cr.id as id',
                'cr.campaign_id',
                'cr.contact_id',
                'cr.lead_id',
                'cr.eligibility_status',
                'cr.eligibility_reason',
                'cr.delivery_status',
                'cr.scheduled_at',
                'cr.processed_at',
                'cr.attempts',
                'cr.skip_reason',
                'cr.last_error',
                'cr.idempotency_key',
                'cr.provider_message_id',
                'cr.message_id',
                'cr.cancelled_at',
                'c.channel as campaign_channel',
                'c.message_template_id',
            ]);

        if ($campaignId) {
            $query->where('cr.campaign_id', $campaignId);
        }

        $created = 0;
        $attemptsLinked = 0;

        $cursor = null;

        do {
            $recipients = (clone $query)
                ->when($cursor !== null, fn ($q) => $q->where('cr.id', '>', $cursor))
                ->orderBy('cr.id')
                ->limit($chunk)
                ->get();

            if ($recipients->isEmpty()) {
                break;
            }

            $rows = [];

            foreach ($recipients as $recipient) {
                $channelValue = (string) ($recipient->campaign_channel ?? '');
                $channel = ProposalChannel::tryFrom($channelValue);

                if ($channel === null) {
                    continue;
                }

                foreach ($channel->consentChannels() as $consentChannel) {
                    $rows[] = [
                        'id' => Str::uuid()->toString(),
                        'campaign_recipient_id' => $recipient->id,
                        'campaign_id' => $recipient->campaign_id,
                        'contact_id' => $recipient->contact_id,
                        'lead_id' => $recipient->lead_id,
                        'channel' => $consentChannel->value,
                        'eligibility_status' => $recipient->eligibility_status ?? 'pending',
                        'eligibility_reason' => $recipient->eligibility_reason,
                        'delivery_status' => $recipient->delivery_status ?? 'PENDING',
                        'scheduled_at' => $recipient->scheduled_at,
                        'processed_at' => $recipient->processed_at,
                        'attempts' => $recipient->attempts ?? 0,
                        'skip_reason' => $recipient->skip_reason,
                        'last_error' => $recipient->last_error,
                        'idempotency_key' => $recipient->idempotency_key
                            ? $recipient->idempotency_key.':'.$consentChannel->value
                            : null,
                        'provider_message_id' => $recipient->provider_message_id,
                        'message_id' => $recipient->message_id,
                        'cancelled_at' => $recipient->cancelled_at,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            if ($rows !== []) {
                if (! $dryRun) {
                    DB::table('campaign_recipient_channels')->insertOrIgnore($rows);
                }

                $created += count($rows);
            }

            $cursor = (string) $recipients->last()->id;
        } while (true);

        if (! $dryRun) {
            $attemptsLinked = DB::affectingStatement(<<<'SQL'
                UPDATE proposal_delivery_attempts pda
                SET campaign_recipient_channel_id = crc.id
                FROM campaign_recipient_channels crc
                WHERE crc.campaign_recipient_id = pda.campaign_recipient_id
                  AND crc.channel = pda.channel
                  AND pda.campaign_recipient_channel_id IS NULL
                SQL);

            DB::table('campaigns as c')
                ->where('c.type', 'proposal')
                ->whereNull('c.audience_snapshot_at')
                ->whereExists(function ($query): void {
                    $query->selectRaw('1')
                        ->from('campaign_recipients as cr')
                        ->whereColumn('cr.campaign_id', 'c.id');
                })
                ->update([
                    'c.audience_snapshot_at' => DB::raw('c.created_at'),
                    'c.audience_resolved_at' => DB::raw('c.created_at'),
                ]);

            DB::table('campaigns')
                ->where('type', 'proposal')
                ->where('channel', 'WHATSAPP')
                ->whereNull('whatsapp_message_template_id')
                ->update(['whatsapp_message_template_id' => DB::raw('message_template_id')]);
        }

        $this->info(sprintf(
            '%s %d channel rows; %d attempts linked.',
            $dryRun ? 'Would create' : 'Created',
            $created,
            $attemptsLinked,
        ));

        return self::SUCCESS;
    }
}
