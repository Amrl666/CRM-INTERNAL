<?php

namespace App\Domain\Campaigns\Models;

use Database\Factories\ProposalTemplateFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProposalTemplate extends Model
{
    use HasFactory, HasUuids;

    protected static function newFactory(): ProposalTemplateFactory
    {
        return ProposalTemplateFactory::new();
    }

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'required_variables' => 'array',
            'assets' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<Campaign, $this> */
    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
