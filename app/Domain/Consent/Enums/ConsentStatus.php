<?php

namespace App\Domain\Consent\Enums;

enum ConsentStatus: string
{
    case Unknown = 'UNKNOWN';
    case Granted = 'GRANTED';
    case Revoked = 'REVOKED';
}
