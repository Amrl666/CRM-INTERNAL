<?php

namespace App\Jobs;

use App\Domain\Communications\Models\ProviderEvent;
use App\Domain\Contacts\Models\Contact;
use App\Domain\Leads\Models\Activity;
use App\Domain\Leads\Models\Lead;
use App\Support\PhoneNormalizer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessProviderEventJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $eventId)
    {
        $this->onQueue('webhooks');
    }

    public function handle(): void
    {
        $event = ProviderEvent::find($this->eventId);

        if ($event === null || $event->processed_at !== null) {
            return;
        }

        if ($event->event_type === 'messages.upsert') {
            $this->recordInboundMessage($event->payload ?? []);
        }

        $event->update(['processed_at' => now()]);
    }

    /** @param array<string, mixed> $payload */
    private function recordInboundMessage(array $payload): void
    {
        $data = $payload['data'] ?? [];
        $remoteJid = (string) ($data['key']['remoteJid'] ?? '');

        if ($remoteJid === '') {
            return;
        }

        $phone = PhoneNormalizer::toE164(explode('@', $remoteJid)[0]);

        if ($phone === null) {
            return;
        }

        $contact = Contact::where('phone_e164', $phone)->first();

        if ($contact === null) {
            return;
        }

        $lead = Lead::where('contact_id', $contact->id)->whereNull('archived_at')->latest()->first();

        if ($lead === null) {
            return;
        }

        Activity::create([
            'lead_id' => $lead->id,
            'type' => 'whatsapp.inbound',
            'description' => 'Pesan WhatsApp masuk: '.mb_substr((string) ($data['message']['conversation'] ?? 'media/lainnya'), 0, 200),
            'occurred_at' => now(),
        ]);
    }
}
