<?php

namespace App\Domain\Campaigns\Models;

use App\Domain\Campaigns\Enums\CampaignStatus;
use App\Domain\Campaigns\Enums\DeliveryStatus;
use App\Domain\Campaigns\Enums\ProposalChannel;
use App\Domain\Consent\Enums\Channel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    use HasUuids;

    /** @var array<string, mixed> */
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => CampaignStatus::class,
            'channel' => ProposalChannel::class,
            'approved_at' => 'immutable_datetime',
            'scheduled_start_at' => 'immutable_datetime',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'paused_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'audience_snapshot_at' => 'immutable_datetime:Y-m-d H:i:s.u',
            'audience_resolved_at' => 'immutable_datetime:Y-m-d H:i:s.u',
            'business_days' => 'array',
            'excluded_dates' => 'array',
            'approval_snapshot' => 'array',
            'audience_filters' => 'array',
            'rate_limit_per_hour' => 'integer',
            'min_spacing_seconds' => 'integer',
            'revision' => 'integer',
            'approved_revision' => 'integer',
        ];
    }

    /** @return BelongsTo<EmailTemplate, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(EmailTemplate::class);
    }

    /** @return BelongsTo<ProposalTemplate, $this> */
    public function proposalTemplate(): BelongsTo
    {
        return $this->belongsTo(ProposalTemplate::class);
    }

    /** @return BelongsTo<MessageTemplate, $this> */
    public function messageTemplate(): BelongsTo
    {
        return $this->belongsTo(MessageTemplate::class);
    }

    /** @return BelongsTo<MessageTemplate, $this> */
    public function whatsappMessageTemplate(): BelongsTo
    {
        return $this->belongsTo(MessageTemplate::class, 'whatsapp_message_template_id');
    }

    /** @return HasMany<CampaignRecipient, $this> */
    public function recipients(): HasMany
    {
        return $this->hasMany(CampaignRecipient::class);
    }

    /** @return HasMany<CampaignRecipientChannel, $this> */
    public function recipientChannels(): HasMany
    {
        return $this->hasMany(CampaignRecipientChannel::class);
    }

    public function isProposal(): bool
    {
        return $this->type === 'proposal';
    }

    public function isPaused(): bool
    {
        return $this->status === CampaignStatus::Paused;
    }

    public function isApprovedForCurrentRevision(): bool
    {
        return $this->approved_by !== null
            && $this->approved_at !== null
            && $this->approved_revision !== null
            && $this->approved_revision === $this->revision
            && in_array($this->status, [
                CampaignStatus::Approved,
                CampaignStatus::Preparing,
                CampaignStatus::Scheduled,
                CampaignStatus::Running,
                CampaignStatus::Paused,
            ], true);
    }

    /** @return array<int, CampaignRecipientChannel> */
    public function selectedChannels(): array
    {
        return $this->channel?->consentChannels() ?? [];
    }

    public function includesChannel(Channel $channel): bool
    {
        return in_array($channel, $this->selectedChannels(), true);
    }

    public function audienceIsResolved(): bool
    {
        return $this->audience_resolved_at !== null;
    }

    /** @return array<string, int> */
    public function deliveryCounts(): array
    {
        $counts = [];
        foreach (DeliveryStatus::cases() as $status) {
            $counts[$status->value] = 0;
        }

        $rows = $this->recipients()
            ->selectRaw('delivery_status, count(*) as total')
            ->groupBy('delivery_status')
            ->pluck('total', 'delivery_status');

        foreach ($rows as $status => $total) {
            $counts[(string) $status] = (int) $total;
        }

        return $counts;
    }

    /**
     * Channel-level counts for monitoring. Falls back to recipient counts
     * for legacy campaigns that have no channel rows.
     *
     * @return array<string, array<string, int>>
     */
    public function channelDeliveryCounts(): array
    {
        $counts = [];
        foreach (['EMAIL', 'WHATSAPP'] as $channel) {
            foreach (DeliveryStatus::cases() as $status) {
                $counts[$channel][$status->value] = 0;
            }
        }

        if (! $this->isProposal()) {
            return $counts;
        }

        $rows = $this->recipientChannels()
            ->selectRaw('channel, delivery_status, count(*) as total')
            ->groupBy('channel', 'delivery_status')
            ->get();

        foreach ($rows as $row) {
            $channel = (string) $row->channel;
            if (! isset($counts[$channel])) {
                continue;
            }
            $counts[$channel][(string) $row->delivery_status] = (int) $row->total;
        }

        return $counts;
    }
}
