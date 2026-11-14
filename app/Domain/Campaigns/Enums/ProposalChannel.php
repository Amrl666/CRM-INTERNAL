<?php

namespace App\Domain\Campaigns\Enums;

use App\Domain\Consent\Enums\Channel;

enum ProposalChannel: string
{
    case Email = 'EMAIL';
    case WhatsApp = 'WHATSAPP';

    public function consentChannel(): Channel
    {
        return match ($this) {
            self::Email => Channel::Email,
            self::WhatsApp => Channel::WhatsApp,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Email => 'Email (Gmail)',
            self::WhatsApp => 'WhatsApp (Evolution)',
        };
    }
}
