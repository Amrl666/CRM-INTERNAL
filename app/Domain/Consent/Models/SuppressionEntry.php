<?php

namespace App\Domain\Consent\Models;

use App\Domain\Consent\Enums\Channel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SuppressionEntry extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['channel' => Channel::class];
    }
}
