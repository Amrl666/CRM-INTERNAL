<?php

namespace App\Domain\Leads\Models;

use App\Domain\Companies\Models\Company;
use App\Domain\Contacts\Models\Contact;
use App\Domain\Leads\Enums\LeadStatus;
use App\Models\User;
use Database\Factories\LeadFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lead extends Model
{
    use HasFactory, HasUuids;

    protected $guarded = ['id'];

    protected static function newFactory(): LeadFactory
    {
        return LeadFactory::new();
    }

    protected function casts(): array
    {
        return [
            'status' => LeadStatus::class,
            'qualified_at' => 'immutable_datetime',
            'archived_at' => 'immutable_datetime',
            'qualification_score' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(LeadSource::class, 'source_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class)->latest('occurred_at');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(Note::class)->latest();
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(LeadAssignment::class)->latest('assigned_at');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'lead_tags');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->can('leads.assign')) {
            return $query;
        }

        return $query->where('owner_id', $user->id);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }
}
