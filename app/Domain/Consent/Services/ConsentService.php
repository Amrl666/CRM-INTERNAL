<?php

namespace App\Domain\Consent\Services;

use App\Domain\Consent\Enums\Channel;
use App\Domain\Consent\Enums\ConsentStatus;
use App\Domain\Consent\Models\ConsentRecord;
use App\Domain\Contacts\Models\Contact;
use Illuminate\Support\Facades\DB;

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

    /**
     * Batch version of currentStatus(). Returns contact_id => ConsentStatus.
     *
     * @param  string[]  $contactIds
     * @return array<string, ConsentStatus>
     */
    public function currentStatusesForContacts(array $contactIds, Channel $channel): array
    {
        if ($contactIds === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($contactIds), '?'));
        $bindings = [...array_values($contactIds), $channel->value];

        $rows = DB::select(<<<SQL
            SELECT DISTINCT ON (contact_id) contact_id, status
            FROM consent_records
            WHERE contact_id IN ({$placeholders})
              AND channel = ?
            ORDER BY contact_id, recorded_at DESC
            SQL, $bindings);

        $statuses = [];
        foreach ($rows as $row) {
            $status = ConsentStatus::tryFrom((string) $row->status);
            if ($status !== null) {
                $statuses[(string) $row->contact_id] = $status;
            }
        }

        return $statuses;
    }
}
