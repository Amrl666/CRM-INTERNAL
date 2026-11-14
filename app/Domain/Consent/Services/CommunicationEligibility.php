<?php

namespace App\Domain\Consent\Services;

use App\Domain\Consent\Enums\Channel;
use App\Domain\Consent\Enums\ConsentStatus;
use App\Domain\Contacts\Models\Contact;
use App\Domain\Leads\Models\Lead;

final class CommunicationEligibility
{
    public function __construct(
        private readonly ConsentService $consent,
        private readonly SuppressionService $suppression,
    ) {}

    /** @return string[] */
    public function check(Contact $contact, Channel $channel, ?Lead $lead = null): array
    {
        $reasons = [];

        if ($lead !== null && $lead->isArchived()) {
            $reasons[] = 'Lead sudah diarsipkan.';
        }

        $address = match ($channel) {
            Channel::Email => $contact->normalized_email,
            Channel::WhatsApp => $contact->phone_e164,
        };

        if ($address === null || $address === '') {
            $reasons[] = $channel === Channel::Email ? 'Kontak tidak punya email.' : 'Kontak tidak punya nomor WhatsApp.';
        }

        if ($this->consent->currentStatus($contact, $channel) !== ConsentStatus::Granted) {
            $reasons[] = 'Consent belum GRANTED untuk channel ini (default deny).';
        }

        if ($address !== null && $this->suppression->isSuppressed($channel, $address)) {
            $reasons[] = 'Alamat ada di daftar suppression.';
        }

        if ($channel === Channel::WhatsApp && config('communications.whatsapp.outbound_mode') !== 'manual') {
            $reasons[] = 'Mode WhatsApp harus manual.';
        }

        return $reasons;
    }

    public function allows(Contact $contact, Channel $channel, ?Lead $lead = null): bool
    {
        return $this->check($contact, $channel, $lead) === [];
    }
}
