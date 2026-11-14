<?php

namespace App\Domain\Consent\Actions;

use App\Domain\Audit\Actions\RecordAuditAction;
use App\Domain\Consent\Enums\Channel;
use App\Domain\Consent\Enums\ConsentStatus;
use App\Domain\Consent\Models\ConsentRecord;
use App\Domain\Consent\Services\ConsentService;
use App\Domain\Contacts\Models\Contact;

final class RecordConsentAction
{
    public function __construct(
        private readonly ConsentService $service,
        private readonly RecordAuditAction $audit,
    ) {}

    public function grant(Contact $contact, Channel $channel, int $recordedBy): ConsentRecord
    {
        $record = $this->service->record($contact, $channel, ConsentStatus::Granted, 'lead detail', null, $recordedBy);

        $this->audit->handle(user: $recordedBy, action: 'consent.granted', subject: $contact, after: [
            'channel' => $channel->value,
        ]);

        return $record;
    }

    public function revoke(Contact $contact, Channel $channel, int $recordedBy): ConsentRecord
    {
        $record = $this->service->record($contact, $channel, ConsentStatus::Revoked, 'unsubscribe', null, $recordedBy);

        $this->audit->handle(user: $recordedBy, action: 'consent.revoked', subject: $contact, after: [
            'channel' => $channel->value,
        ]);

        return $record;
    }
}
