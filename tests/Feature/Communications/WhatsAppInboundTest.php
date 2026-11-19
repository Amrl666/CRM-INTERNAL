<?php

use App\Domain\Communications\Models\CommunicationMessage;
use App\Domain\Communications\Models\Conversation;
use App\Domain\Communications\Models\ProviderEvent;
use App\Domain\Contacts\Models\Contact;
use App\Domain\Leads\Models\Lead;
use App\Jobs\ProcessProviderEventJob;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function inboundEvent(array $payload): ProviderEvent
{
    return ProviderEvent::create([
        'provider' => 'evolution',
        'event_id' => (string) ($payload['id'] ?? uniqid('evt-', true)),
        'event_type' => $payload['event'] ?? 'messages.upsert',
        'payload' => $payload,
    ]);
}

function runInbound(array $payload): ProviderEvent
{
    $event = inboundEvent($payload);

    (new ProcessProviderEventJob($event->id))->handle();

    return $event->fresh();
}

it('stores an inbound message from a new number as a new conversation', function () {
    $payload = [
        'id' => 'evt-new-1',
        'event' => 'messages.upsert',
        'instance' => 'default',
        'data' => [
            'key' => [
                'remoteJid' => '628111222333@s.whatsapp.net',
                'fromMe' => false,
                'id' => 'msg-1',
            ],
            'pushName' => 'Budi',
            'message' => ['conversation' => 'Halo admin'],
            'messageTimestamp' => 1700000000,
        ],
    ];

    $event = runInbound($payload);

    expect($event->processed_at)->not->toBeNull();

    $conversation = Conversation::first();
    expect($conversation)->not->toBeNull()
        ->and($conversation->remote_jid)->toBe('628111222333@s.whatsapp.net')
        ->and($conversation->phone_e164)->toBe('+628111222333')
        ->and($conversation->contact_id)->toBeNull()
        ->and($conversation->lead_id)->toBeNull()
        ->and($conversation->display_name)->toBe('Budi');

    $message = CommunicationMessage::first();
    expect($message)->not->toBeNull()
        ->and($message->conversation_id)->toBe($conversation->id)
        ->and($message->channel)->toBe('WHATSAPP')
        ->and($message->direction)->toBe('inbound')
        ->and($message->status)->toBe('received')
        ->and($message->body)->toBe('Halo admin')
        ->and($message->provider_message_id)->toBe('msg-1')
        ->and($message->sent_at?->getTimestamp())->toBe(1700000000);
});

it('links a known contact and its single non-archived lead', function () {
    $contact = Contact::factory()->create(['phone_e164' => '+628111222333']);
    $lead = Lead::factory()->create(['contact_id' => $contact->id, 'archived_at' => null]);

    $payload = [
        'id' => 'evt-known-1',
        'event' => 'messages.upsert',
        'instance' => 'default',
        'data' => [
            'key' => [
                'remoteJid' => '628111222333@s.whatsapp.net',
                'fromMe' => false,
                'id' => 'msg-2',
            ],
            'message' => ['conversation' => 'Saya tertarik'],
        ],
    ];

    runInbound($payload);

    $conversation = Conversation::first();
    expect($conversation->contact_id)->toBe($contact->id)
        ->and($conversation->lead_id)->toBe($lead->id);
});

it('does not auto-select a lead when the contact has multiple leads', function () {
    $contact = Contact::factory()->create(['phone_e164' => '+628111222333']);
    Lead::factory()->create(['contact_id' => $contact->id, 'archived_at' => null]);
    Lead::factory()->create(['contact_id' => $contact->id, 'archived_at' => null]);

    $payload = [
        'id' => 'evt-multi-lead',
        'event' => 'messages.upsert',
        'instance' => 'default',
        'data' => [
            'key' => [
                'remoteJid' => '628111222333@s.whatsapp.net',
                'fromMe' => false,
                'id' => 'msg-3',
            ],
            'message' => ['conversation' => 'Halo'],
        ],
    ];

    runInbound($payload);

    $conversation = Conversation::first();
    expect($conversation->contact_id)->toBe($contact->id)
        ->and($conversation->lead_id)->toBeNull();
});

it('keeps lead null when the known contact has no lead', function () {
    $contact = Contact::factory()->create(['phone_e164' => '+628111222333']);

    $payload = [
        'id' => 'evt-no-lead',
        'event' => 'messages.upsert',
        'instance' => 'default',
        'data' => [
            'key' => [
                'remoteJid' => '628111222333@s.whatsapp.net',
                'fromMe' => false,
                'id' => 'msg-4',
            ],
            'message' => ['conversation' => 'Halo'],
        ],
    ];

    runInbound($payload);

    $conversation = Conversation::first();
    expect($conversation->contact_id)->toBe($contact->id)
        ->and($conversation->lead_id)->toBeNull();
});

it('is idempotent for the same provider event', function () {
    $payload = [
        'id' => 'evt-dup-event',
        'event' => 'messages.upsert',
        'instance' => 'default',
        'data' => [
            'key' => [
                'remoteJid' => '628111222333@s.whatsapp.net',
                'fromMe' => false,
                'id' => 'msg-5',
            ],
            'message' => ['conversation' => 'Halo'],
        ],
    ];

    $event = inboundEvent($payload);
    (new ProcessProviderEventJob($event->id))->handle();
    (new ProcessProviderEventJob($event->id))->handle();

    expect(CommunicationMessage::count())->toBe(1);
});

it('does not store a duplicate when provider_message_id is repeated across events', function () {
    $payload1 = [
        'id' => 'evt-a',
        'event' => 'messages.upsert',
        'instance' => 'default',
        'data' => [
            'key' => [
                'remoteJid' => '628111222333@s.whatsapp.net',
                'fromMe' => false,
                'id' => 'same-provider-id',
            ],
            'message' => ['conversation' => 'Halo'],
        ],
    ];
    $payload2 = [
        'id' => 'evt-b',
        'event' => 'messages.upsert',
        'instance' => 'default',
        'data' => [
            'key' => [
                'remoteJid' => '628111222333@s.whatsapp.net',
                'fromMe' => false,
                'id' => 'same-provider-id',
            ],
            'message' => ['conversation' => 'Halo'],
        ],
    ];

    runInbound($payload1);
    runInbound($payload2);

    expect(CommunicationMessage::count())->toBe(1);
});

it('stores two separate messages when provider_message_id is missing', function () {
    $payload1 = [
        'id' => 'evt-no-id-1',
        'event' => 'messages.upsert',
        'instance' => 'default',
        'data' => [
            'key' => [
                'remoteJid' => '628111222333@s.whatsapp.net',
                'fromMe' => false,
            ],
            'message' => ['conversation' => 'Halo'],
        ],
    ];
    $payload2 = [
        'id' => 'evt-no-id-2',
        'event' => 'messages.upsert',
        'instance' => 'default',
        'data' => [
            'key' => [
                'remoteJid' => '628111222333@s.whatsapp.net',
                'fromMe' => false,
            ],
            'message' => ['conversation' => 'Halo'],
        ],
    ];

    runInbound($payload1);
    runInbound($payload2);

    expect(CommunicationMessage::count())->toBe(2);
});

it('ignores fromMe messages without creating inbound records', function () {
    $payload = [
        'id' => 'evt-fromme',
        'event' => 'messages.upsert',
        'instance' => 'default',
        'data' => [
            'key' => [
                'remoteJid' => '628111222333@s.whatsapp.net',
                'fromMe' => true,
                'id' => 'msg-fromme',
            ],
            'message' => ['conversation' => 'Pesan keluar'],
        ],
    ];

    $event = runInbound($payload);

    expect($event->processed_at)->not->toBeNull()
        ->and(CommunicationMessage::count())->toBe(0)
        ->and(Conversation::count())->toBe(0);
});

it('stores media messages using their caption', function () {
    $payload = [
        'id' => 'evt-media',
        'event' => 'messages.upsert',
        'instance' => 'default',
        'data' => [
            'key' => [
                'remoteJid' => '628111222333@s.whatsapp.net',
                'fromMe' => false,
                'id' => 'msg-media',
            ],
            'message' => [
                'imageMessage' => ['caption' => 'Foto produk'],
            ],
        ],
    ];

    runInbound($payload);

    $message = CommunicationMessage::first();
    expect($message->body)->toBe('Foto produk');
});

it('stores extended text messages', function () {
    $payload = [
        'id' => 'evt-ext',
        'event' => 'messages.upsert',
        'instance' => 'default',
        'data' => [
            'key' => [
                'remoteJid' => '628111222333@s.whatsapp.net',
                'fromMe' => false,
                'id' => 'msg-ext',
            ],
            'message' => [
                'extendedTextMessage' => ['text' => 'Link https://example.com'],
            ],
        ],
    ];

    runInbound($payload);

    expect(CommunicationMessage::first()->body)->toBe('Link https://example.com');
});

it('marks invalid payloads as processed without storing messages', function () {
    $payload = [
        'id' => 'evt-invalid',
        'event' => 'messages.upsert',
        'instance' => 'default',
        'data' => [
            'key' => [],
        ],
    ];

    $event = runInbound($payload);

    expect($event->processed_at)->not->toBeNull()
        ->and(CommunicationMessage::count())->toBe(0)
        ->and(Conversation::count())->toBe(0);
});

it('ignores group and broadcast messages', function () {
    foreach (['1203630@g.us', 'status@broadcast'] as $jid) {
        $payload = [
            'id' => 'evt-skip-'.$jid,
            'event' => 'messages.upsert',
            'instance' => 'default',
            'data' => [
                'key' => [
                    'remoteJid' => $jid,
                    'fromMe' => false,
                    'id' => 'msg-skip',
                ],
                'message' => ['conversation' => 'ignore'],
            ],
        ];

        runInbound($payload);
    }

    expect(CommunicationMessage::count())->toBe(0)
        ->and(Conversation::count())->toBe(0);
});

it('ignores non-message events but marks them processed', function () {
    $event = ProviderEvent::create([
        'provider' => 'evolution',
        'event_id' => 'evt-other',
        'event_type' => 'connection.update',
        'payload' => ['event' => 'connection.update'],
    ]);

    (new ProcessProviderEventJob($event->id))->handle();

    expect($event->fresh()->processed_at)->not->toBeNull();
});

it('keeps legacy email and proposal messages valid', function () {
    $message = CommunicationMessage::create([
        'channel' => 'EMAIL',
        'direction' => 'outbound',
        'status' => 'draft',
        'body' => 'legacy email',
    ]);

    expect($message->id)->not->toBeNull()
        ->and($message->conversation_id)->toBeNull();
});
