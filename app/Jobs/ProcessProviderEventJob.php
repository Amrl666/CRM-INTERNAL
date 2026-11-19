<?php

namespace App\Jobs;

use App\Domain\Communications\Models\CommunicationMessage;
use App\Domain\Communications\Models\Conversation;
use App\Domain\Communications\Models\ProviderEvent;
use App\Domain\Contacts\Models\Contact;
use App\Domain\Leads\Models\Lead;
use App\Support\PhoneNormalizer;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

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

        DB::transaction(function () use ($event): void {
            if ($event->event_type === 'messages.upsert') {
                $this->recordInboundMessage($event->payload ?? []);
            }

            $event->update(['processed_at' => now()]);
        });
    }

    /** @param array<string, mixed> $payload */
    private function recordInboundMessage(array $payload): void
    {
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $key = is_array($data['key'] ?? null) ? $data['key'] : [];

        $remoteJid = trim((string) ($key['remoteJid'] ?? ''));

        // Incomplete payload: nothing to store.
        if ($remoteJid === '') {
            return;
        }

        // Explicitly ignore groups and status broadcasts.
        if (str_ends_with($remoteJid, '@g.us')
            || $remoteJid === 'status@broadcast'
            || str_contains($remoteJid, '@broadcast')) {
            return;
        }

        $instance = trim((string) ($payload['instance'] ?? $data['instance'] ?? config('communications.whatsapp.evolution.instance') ?? ''));
        if ($instance === '') {
            $instance = 'default';
        }

        $provider = 'evolution';
        $providerMessageId = isset($key['id']) ? trim((string) $key['id']) : null;
        if ($providerMessageId === '') {
            $providerMessageId = null;
        }

        $body = $this->extractBody($data);
        $sentAt = $this->extractTimestamp($data, $payload);

        $fromMe = filter_var($key['fromMe'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($fromMe) {
            $this->reconcileOutboundMessage($payload, $data, $key, $remoteJid, $instance, $providerMessageId, $body, $sentAt);

            return;
        }

        $rawPhone = explode('@', $remoteJid)[0];
        $phone = PhoneNormalizer::toE164($rawPhone);

        $pushName = isset($data['pushName']) ? trim((string) $data['pushName']) : null;
        if ($pushName === '') {
            $pushName = null;
        }

        $conversation = $this->findOrCreateConversation(
            $provider,
            $instance,
            $remoteJid,
            $phone,
            $pushName,
        );

        // Idempotency for messages that carry a provider id.
        if ($providerMessageId !== null) {
            $alreadyStored = CommunicationMessage::query()
                ->where('conversation_id', $conversation->id)
                ->where('provider_message_id', $providerMessageId)
                ->exists();

            if ($alreadyStored) {
                return;
            }
        }

        try {
            CommunicationMessage::create([
                'conversation_id' => $conversation->id,
                'contact_id' => $conversation->contact_id,
                'channel' => 'WHATSAPP',
                'direction' => 'inbound',
                'body' => $body,
                'provider' => $provider,
                'provider_message_id' => $providerMessageId,
                'provider_payload' => $payload,
                'status' => 'received',
                'sent_at' => $sentAt,
            ]);
        } catch (UniqueConstraintViolationException $e) {
            $duplicateExists = CommunicationMessage::query()
                ->where('conversation_id', $conversation->id)
                ->where('provider_message_id', $providerMessageId)
                ->exists();

            if (! $duplicateExists) {
                throw $e;
            }

            // Duplicate provider message: skip without failing the job.
            return;
        }

        $preview = $body !== null ? mb_substr($body, 0, 200) : $conversation->last_message_preview;

        $conversation->update([
            'last_message_at' => $sentAt ?? now(),
            'last_message_preview' => $preview,
            'display_name' => $conversation->display_name ?: $pushName,
        ]);
    }

    private function findOrCreateConversation(
        string $provider,
        string $instance,
        string $remoteJid,
        ?string $phone,
        ?string $pushName,
    ): Conversation {
        $identity = [
            'provider' => $provider,
            'instance' => $instance,
            'remote_jid' => $remoteJid,
        ];

        try {
            $conversation = Conversation::firstOrCreate($identity, [
                'phone_e164' => $phone,
                'display_name' => $pushName,
                'status' => 'open',
            ]);
        } catch (UniqueConstraintViolationException $e) {
            $conversation = Conversation::where($identity)->firstOrFail();
        }

        $updates = [];

        if ($conversation->phone_e164 === null && $phone !== null) {
            $updates['phone_e164'] = $phone;
        }

        if ($conversation->display_name === null && $pushName !== null) {
            $updates['display_name'] = $pushName;
        }

        if ($conversation->contact_id === null && $phone !== null) {
            $contact = Contact::query()->where('phone_e164', $phone)->first();

            if ($contact !== null) {
                $updates['contact_id'] = $contact->id;

                if ($conversation->lead_id === null) {
                    $leads = Lead::query()
                        ->where('contact_id', $contact->id)
                        ->whereNull('archived_at')
                        ->get();

                    if ($leads->count() === 1) {
                        $updates['lead_id'] = $leads->first()->id;
                    }
                }
            }
        }

        if ($updates !== []) {
            $conversation->update($updates);
        }

        return $conversation->fresh();
    }

    /** @param array<string, mixed> $payload */
    private function reconcileOutboundMessage(
        array $payload,
        array $data,
        array $key,
        string $remoteJid,
        string $instance,
        ?string $providerMessageId,
        ?string $body,
        ?Carbon $sentAt,
    ): void {
        if (str_ends_with($remoteJid, '@g.us') || str_contains($remoteJid, '@broadcast')) {
            return;
        }

        $conversation = Conversation::query()
            ->where('provider', 'evolution')
            ->where('instance', $instance)
            ->where('remote_jid', $remoteJid)
            ->first();

        if ($conversation === null) {
            return;
        }

        $message = null;

        // Reconcile only when the provider message id is already known.
        // Body/time fallback is intentionally not used because it can
        // incorrectly match another outbound message.
        if ($providerMessageId !== null) {
            $message = CommunicationMessage::query()
                ->where('conversation_id', $conversation->id)
                ->where('provider_message_id', $providerMessageId)
                ->first();
        }

        if ($message === null) {
            return;
        }

        $updates = [
            'status' => 'sent',
            'provider_payload' => $payload,
        ];

        $updates['provider_message_id'] = $providerMessageId;

        if ($sentAt !== null) {
            $updates['sent_at'] = $sentAt;
        }

        $message->update($updates);
    }

    /** @param array<string, mixed> $data */
    private function extractBody(array $data): ?string
    {
        $message = is_array($data['message'] ?? null) ? $data['message'] : [];

        // Unwrap common container messages.
        foreach (['ephemeralMessage', 'viewOnceMessage', 'viewOnceMessageV2'] as $wrapper) {
            if (isset($message[$wrapper]['message']) && is_array($message[$wrapper]['message'])) {
                $message = $message[$wrapper]['message'];
                break;
            }
        }

        if (isset($message['conversation']) && is_string($message['conversation'])) {
            return $message['conversation'];
        }

        if (isset($message['extendedTextMessage']['text']) && is_string($message['extendedTextMessage']['text'])) {
            return $message['extendedTextMessage']['text'];
        }

        foreach (['imageMessage', 'videoMessage', 'documentMessage', 'audioMessage'] as $mediaKey) {
            if (isset($message[$mediaKey]['caption']) && is_string($message[$mediaKey]['caption'])) {
                return $message[$mediaKey]['caption'];
            }
        }

        return null;
    }

    /** @param array<string, mixed> $data */
    private function extractTimestamp(array $data, array $payload): ?Carbon
    {
        $raw = $data['messageTimestamp'] ?? $payload['timestamp'] ?? $payload['date_time'] ?? null;

        if (is_array($raw)) {
            $raw = $raw[0] ?? null;
        }

        if (is_numeric($raw)) {
            $value = (int) $raw;

            // Heuristic: milliseconds when very large.
            if ($value > 1_000_000_000_000) {
                return Carbon::createFromTimestampMs($value);
            }

            if ($value > 0) {
                return Carbon::createFromTimestamp($value);
            }
        }

        if (is_string($raw) && trim($raw) !== '') {
            try {
                return Carbon::parse($raw);
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }
}
