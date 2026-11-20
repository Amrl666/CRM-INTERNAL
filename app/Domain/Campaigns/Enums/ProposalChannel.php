<?php

namespace App\Domain\Campaigns\Enums;

use App\Domain\Consent\Enums\Channel;
use LogicException;

enum ProposalChannel: string
{
    case Email = 'EMAIL';
    case WhatsApp = 'WHATSAPP';
    case Both = 'BOTH';

    /** @return Channel[] */
    public function consentChannels(): array
    {
        return match ($this) {
            self::Email => [Channel::Email],
            self::WhatsApp => [Channel::WhatsApp],
            self::Both => [Channel::Email, Channel::WhatsApp],
        };
    }

    /**
     * Backward-compatible helper for single-channel callers.
     * BOTH has no single consent channel; callers must use consentChannels().
     */
    public function consentChannel(): Channel
    {
        return match ($this) {
            self::Email => Channel::Email,
            self::WhatsApp => Channel::WhatsApp,
            self::Both => throw new LogicException('BOTH channel has no single consent channel. Use consentChannels().'),
        };
    }

    public function includesEmail(): bool
    {
        return in_array(self::Email, $this->consentChannels(), true);
    }

    public function includesWhatsApp(): bool
    {
        return in_array(self::WhatsApp, $this->consentChannels(), true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Email => 'Email',
            self::WhatsApp => 'WhatsApp (Evolution)',
            self::Both => 'Email + WhatsApp',
        };
    }
}
