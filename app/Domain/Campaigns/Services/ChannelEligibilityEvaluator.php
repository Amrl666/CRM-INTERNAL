<?php

namespace App\Domain\Campaigns\Services;

use App\Domain\Consent\Enums\Channel;
use App\Domain\Consent\Enums\ConsentStatus;
use App\Domain\Consent\Services\ConsentService;
use App\Domain\Consent\Services\SuppressionService;
use App\Domain\Contacts\Models\Contact;
use App\Domain\Leads\Models\Lead;
use Illuminate\Support\Collection;

/**
 * Batch eligibility evaluation for a chunk of recipients.
 * Mirrors CommunicationEligibility::check() without N+1 queries.
 */
final class ChannelEligibilityEvaluator
{
    public function __construct(
        private readonly ConsentService $consent,
        private readonly SuppressionService $suppression,
        private readonly PriorOutreachGuard $prior,
    ) {}

    /**
     * @param  Collection<int, object>  $pairs  rows with contact_id and lead_id
     * @param  Channel[]  $channels
     * @return array<string, array{eligible: bool, reasons: string[]}> key = contactId|CHANNEL
     */
    public function checkMany(Collection $pairs, array $channels, ?string $campaignId = null): array
    {
        $contactIds = $pairs->pluck('contact_id')->filter()->unique()->values()->all();
        $leadIds = $pairs->pluck('lead_id')->filter()->unique()->values()->all();

        $contacts = Contact::query()
            ->whereIn('id', $contactIds)
            ->with('company')
            ->get()
            ->keyBy('id');

        $leads = Lead::query()
            ->whereIn('id', $leadIds)
            ->get()
            ->keyBy('id');

        $results = [];
        $addressesByChannel = [];

        foreach ($channels as $channel) {
            $addresses = [];
            foreach ($contacts as $contact) {
                $address = $channel === Channel::Email
                    ? $contact->normalized_email
                    : $contact->phone_e164;
                if ($address !== null && $address !== '') {
                    $addresses[] = $address;
                }
            }
            $addressesByChannel[$channel->value] = $this->suppression->suppressedAddresses($channel, $addresses);
        }

        foreach ($channels as $channel) {
            $statuses = $this->consent->currentStatusesForContacts($contactIds, $channel);
            $history = $this->prior->blockedContacts($contactIds, $channel, $campaignId);
            $suppressed = $addressesByChannel[$channel->value] ?? [];

            foreach ($pairs as $pair) {
                $contactId = (string) $pair->contact_id;
                $leadId = (string) ($pair->lead_id ?? '');
                $contact = $contacts->get($contactId);
                $lead = $leads->get($leadId);

                $reasons = [];

                if ($lead !== null && $lead->isArchived()) {
                    $reasons[] = 'Lead sudah diarsipkan.';
                }

                if ($contact === null) {
                    $reasons[] = $channel === Channel::Email
                        ? 'Kontak tidak punya email.'
                        : 'Kontak tidak punya nomor WhatsApp.';
                } else {
                    $address = $channel === Channel::Email
                        ? $contact->normalized_email
                        : $contact->phone_e164;

                    if ($address === null || $address === '') {
                        $reasons[] = $channel === Channel::Email
                            ? 'Kontak tidak punya email.'
                            : 'Kontak tidak punya nomor WhatsApp.';
                    } elseif ($channel === Channel::Email && filter_var($address, FILTER_VALIDATE_EMAIL) === false) {
                        $reasons[] = 'Format email tidak valid.';
                    }

                    if (($statuses[$contactId] ?? null) !== ConsentStatus::Granted) {
                        $reasons[] = 'Consent belum GRANTED untuk channel ini (default deny).';
                    }

                    if (
                        $address !== null
                        && $address !== ''
                        && isset($suppressed[$this->suppression->normalize($channel, $address)])
                    ) {
                        $reasons[] = 'Alamat ada di daftar suppression.';
                    }
                }

                if ($channel === Channel::WhatsApp && config('communications.whatsapp.outbound_mode') !== 'manual') {
                    $reasons[] = 'Mode WhatsApp harus manual.';
                }

                if (isset($history[$contactId])) {
                    $reasons[] = $history[$contactId];
                }

                $results[$contactId.'|'.$channel->value] = [
                    'eligible' => $reasons === [],
                    'reasons' => $reasons,
                ];
            }
        }

        return $results;
    }
}
