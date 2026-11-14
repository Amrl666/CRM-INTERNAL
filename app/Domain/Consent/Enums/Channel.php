<?php

namespace App\Domain\Consent\Enums;

enum Channel: string
{
    case Email = 'EMAIL';
    case WhatsApp = 'WHATSAPP';
}
