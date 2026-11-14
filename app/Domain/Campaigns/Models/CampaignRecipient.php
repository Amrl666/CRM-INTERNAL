<?php

namespace App\Domain\Campaigns\Models;

use App\Domain\Campaigns\Enums\DeliveryStatus;
use App\Domain\Campaigns\Enums\EligibilityStatus;
use App\Domain\Campaigns\Enums\PdfStatus;
use App\Domain\Contacts\Models\Contact;
use App\Domain\Leads\Models\Lead;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CampaignRecipient extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'eligibility_status' => EligibilityStatus::class,
            'pdf_status' => PdfStatus::class,
            'delivery_status' => DeliveryStatus::class,
            'scheduled_at' => 'immutable_datetime',
            'processed_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'proposal_date' => 'immutable_date',
            'attempts' => 'integer',
            'snapshot' => 'array',
        ];
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

    /** @return HasMany<ProposalDeliveryAttempt, $this> */
    public function deliveryAttempts(): HasMany
    {
        return $this->hasMany(ProposalDeliveryAttempt::class);
    }

    public function isCancelled(): bool
    {
        return $this->delivery_status === DeliveryStatus::Cancelled;
    }
}
