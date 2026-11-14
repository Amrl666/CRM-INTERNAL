<?php

namespace App\Domain\Communications\Services;

final class OutboundModeGuard
{
    /**
     * Boot-time guard: reject non-manual WhatsApp outbound mode.
     */
    public function boot(): void
    {
        $mode = config('communications.whatsapp.outbound_mode');

        if ($mode !== 'manual') {
            throw new \RuntimeException(
                "WHATSAPP_OUTBOUND_MODE must be 'manual'. Got: '{$mode}'. "
                .'Outbound automation via Baileys is not supported by this system.'
            );
        }
    }
}
