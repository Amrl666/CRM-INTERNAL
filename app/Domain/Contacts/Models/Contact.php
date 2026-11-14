<?php

namespace App\Domain\Contacts\Models;

use App\Domain\Companies\Models\Company;
use Database\Factories\ContactFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contact extends Model
{
    use HasFactory, HasUuids;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }

    protected static function newFactory(): ContactFactory
    {
        return ContactFactory::new();
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public static function normalizeEmail(?string $email): ?string
    {
        $email = $email !== null ? trim(mb_strtolower($email)) : null;

        return $email === '' ? null : $email;
    }
}
