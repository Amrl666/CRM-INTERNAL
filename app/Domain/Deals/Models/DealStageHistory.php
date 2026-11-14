<?php

namespace App\Domain\Deals\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class DealStageHistory extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['changed_at' => 'immutable_datetime'];
    }
}
