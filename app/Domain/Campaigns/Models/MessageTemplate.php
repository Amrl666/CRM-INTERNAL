<?php

namespace App\Domain\Campaigns\Models;

use Database\Factories\MessageTemplateFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MessageTemplate extends Model
{
    use HasFactory, HasUuids;

    protected static function newFactory(): MessageTemplateFactory
    {
        return MessageTemplateFactory::new();
    }

    public const VARIABLES = [
        'company_name', 'contact_name', 'email', 'phone', 'industry', 'source',
        'proposal_number', 'proposal_date', 'sender_name', 'sender_email',
        'sender_phone', 'sender_website', 'sender_company',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'version' => 'integer',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
