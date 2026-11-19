<?php

namespace App\Domain\Consent\Actions;

use App\Domain\Audit\Actions\RecordAuditAction;
use App\Domain\Consent\Enums\Channel;
use App\Domain\Consent\Enums\ConsentStatus;
use App\Domain\Consent\Services\ConsentService;
use App\Domain\Consent\Services\SuppressionService;
use App\Domain\Contacts\Models\Contact;

final class AutoGrantLeadConsentAction
{
    public function __construct(
        private readonly ConsentService $consent,
        private readonly SuppressionService $suppression,
        private readonly RecordAuditAction $audit,
    ) {}

    /**
     * Grant consent for contact addresses entered through a trusted lead-entry flow.
     *
     * Revoked consent and suppressed addresses are never overridden.
     *
     * @return string[] Channels that received a new GRANTED record.
     */
    public function handle(Contact $contact, ?int $recordedBy = null, string $source = 'lead form'): array
    {
        $granted = [];

        foreach ([Channel::Email, Channel::WhatsApp] as $channel) {
            $address = $channel === Channel::Email
                ? $contact->normalized_email
                : $contact->phone_e164;

            if (! $this->isValidAddress($channel, $address)) {
                continue;
            }

            if ($this->suppression->isSuppressed($channel, $address)) {
                continue;
            }

            if ($this->consent->currentStatus($contact, $channel) !== ConsentStatus::Unknown) {
                continue;
            }

            $this->consent->record(
                $contact,
                $channel,
                ConsentStatus::Granted,
                $source,
                'Alamat dimasukkan melalui alur pembuatan lead dan lolos validasi format.',
                $recordedBy,
            );

            $this->audit->handle(user: $recordedBy, action: 'consent.granted', subject: $contact, after: [
                'channel' => $channel->value,
                'source' => $source,
                'automatic' => true,
            ]);

            $granted[] = $channel->value;
        }

        return $granted;
    }

    private function isValidAddress(Channel $channel, ?string $address): bool
    {
        if ($address === null || trim($address) === '') {
            return false;
        }

        if ($channel === Channel::Email) {
            return filter_var($address, FILTER_VALIDATE_EMAIL) !== false;
        }

        return preg_match('/^\+[1-9]\d{6,14}$/', $address) === 1;
    }
}
