<?php

namespace App\Domain\Leads\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Attachment extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }
}
