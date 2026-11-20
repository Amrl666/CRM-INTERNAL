<?php

namespace App\Domain\Campaigns\Models;

use App\Domain\Campaigns\Enums\DeliveryStatus;
use App\Domain\Campaigns\Enums\EligibilityStatus;
use App\Domain\Campaigns\Enums\ProposalChannel;
use App\Domain\Communications\Models\CommunicationMessage;
use App\Domain\Contacts\Models\Contact;
use App\Domain\Leads\Models\Lead;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CampaignRecipientChannel extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'eligibility_status' => EligibilityStatus::class,
            'delivery_status' => DeliveryStatus::class,
            'scheduled_at' => 'immutable_datetime',
            'processed_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'attempts' => 'integer',
        ];
    }

    /** @return BelongsTo<CampaignRecipient, $this> */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(CampaignRecipient::class, 'campaign_recipient_id');
    }

    /** @return BelongsTo<Campaign, $this> */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /** @return BelongsTo<Contact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /** @return BelongsTo<Lead, $this> */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /** @return BelongsTo<CommunicationMessage, $this> */
    public function message(): BelongsTo
    {
        return $this->belongsTo(CommunicationMessage::class, 'message_id');
    }

    /** @return HasMany<ProposalDeliveryAttempt, $this> */
    public function deliveryAttempts(): HasMany
    {
        return $this->hasMany(ProposalDeliveryAttempt::class);
    }

    public function channelEnum(): ProposalChannel
    {
        return $this->channel === 'WHATSAPP'
            ? ProposalChannel::WhatsApp
            : ProposalChannel::Email;
    }

    public function isTerminal(): bool
    {
        return $this->delivery_status?->isTerminal() ?? false;
    }
}
