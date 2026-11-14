<?php

namespace App\Domain\Companies\Models;

use App\Domain\Contacts\Models\Contact;
use App\Domain\Leads\Models\Lead;
use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    use HasFactory, HasUuids;

    protected $guarded = ['id'];

    protected static function newFactory(): CompanyFactory
    {
        return CompanyFactory::new();
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public static function normalizeName(string $name): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', ' ', mb_strtolower($name)) ?? '');
    }
}
