<?php

namespace App\Domain\Consent\Services;

use App\Domain\Consent\Enums\Channel;
use App\Domain\Consent\Enums\ConsentStatus;
use App\Domain\Consent\Models\ConsentRecord;
use App\Domain\Contacts\Models\Contact;

final class ConsentService
{
    public function record(
        Contact $contact,
        Channel $channel,
        ConsentStatus $status,
        ?string $source = null,
        ?string $evidence = null,
        ?int $recordedBy = null,
    ): ConsentRecord {
        return ConsentRecord::create([
            'contact_id' => $contact->id,
            'channel' => $channel,
            'status' => $status,
            'source' => $source,
            'evidence' => $evidence,
            'recorded_by' => $recordedBy,
            'recorded_at' => now(),
        ]);
    }

    public function currentStatus(Contact $contact, Channel $channel): ConsentStatus
    {
        $latest = ConsentRecord::query()
            ->where('contact_id', $contact->id)
            ->where('channel', $channel)
            ->latest('recorded_at')
            ->first();

        return $latest?->status ?? ConsentStatus::Unknown;
    }
}
