<?php

namespace App\Domain\Communications\Models;

use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Contacts\Models\Contact;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommunicationMessage extends Model
{
    use HasFactory, HasUuids;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(CommunicationAccount::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(MessageEvent::class)->latest('occurred_at');
    }
}
