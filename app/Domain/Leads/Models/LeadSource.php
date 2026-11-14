<?php

namespace App\Domain\Leads\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class LeadSource extends Model
{
    use HasUuids;

    protected $guarded = ['id'];
}
