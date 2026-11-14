<?php

namespace App\Domain\Campaigns\Models;

use App\Domain\Campaigns\Enums\CampaignStatus;
use App\Domain\Campaigns\Enums\DeliveryStatus;
use App\Domain\Campaigns\Enums\ProposalChannel;
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
            'business_days' => 'array',
            'excluded_dates' => 'array',
            'approval_snapshot' => 'array',
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

    /** @return HasMany<CampaignRecipient, $this> */
    public function recipients(): HasMany
    {
        return $this->hasMany(CampaignRecipient::class);
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
                CampaignStatus::Scheduled,
                CampaignStatus::Running,
                CampaignStatus::Paused,
            ], true);
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
}
