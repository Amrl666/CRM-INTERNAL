<?php

namespace App\Domain\Campaigns\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ProposalSenderSetting extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public static function active(): self
    {
        return static::query()->where('is_active', true)->latest('updated_at')->firstOrFail();
    }

    /** @return array<string, string|null> */
    public function toTemplateVariables(): array
    {
        return [
            'sender_company' => $this->company_name,
            'sender_name' => $this->sender_name,
            'sender_email' => $this->sender_email,
            'sender_phone' => $this->sender_phone,
            'sender_website' => $this->sender_website,
            'sender_footer' => $this->footer_text,
        ];
    }
}
