<?php

use App\Domain\Communications\Models\CommunicationMessage;
use App\Domain\Communications\Models\Conversation;
use App\Domain\Communications\Models\ProviderEvent;
use App\Jobs\ProcessProviderEventJob;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeConversationForIdempotency(): Conversation
{
    return Conversation::create([
        'provider' => 'evolution',
        'instance' => 'default',
        'remote_jid' => '628111222333@s.whatsapp.net',
    ]);
}

it('has a database-level partial unique index for conversation_id and provider_message_id', function () {
    $conversation = makeConversationForIdempotency();

    CommunicationMessage::create([
        'conversation_id' => $conversation->id,
        'channel' => 'WHATSAPP',
        'direction' => 'inbound',
        'body' => 'first',
        'provider' => 'evolution',
        'provider_message_id' => 'dup-id',
        'status' => 'received',
    ]);

    expect(fn () => CommunicationMessage::create([
        'conversation_id' => $conversation->id,
        'channel' => 'WHATSAPP',
        'direction' => 'inbound',
        'body' => 'second',
        'provider' => 'evolution',
        'provider_message_id' => 'dup-id',
        'status' => 'received',
    ]))->toThrow(UniqueConstraintViolationException::class);
});

it('stores only one message when the same provider message is processed twice', function () {
    $payload = [
        'id' => 'evt-idempotent-job',
        'event' => 'messages.upsert',
        'instance' => 'default',
        'data' => [
            'key' => [
                'remoteJid' => '628111222333@s.whatsapp.net',
                'fromMe' => false,
                'id' => 'provider-dup',
            ],
            'message' => ['conversation' => 'Halo'],
        ],
    ];

    $event1 = ProviderEvent::create([
        'provider' => 'evolution',
        'event_id' => 'evt-job-1',
        'event_type' => 'messages.upsert',
        'payload' => array_merge($payload, ['id' => 'evt-job-1']),
    ]);
    $event2 = ProviderEvent::create([
        'provider' => 'evolution',
        'event_id' => 'evt-job-2',
        'event_type' => 'messages.upsert',
        'payload' => array_merge($payload, ['id' => 'evt-job-2', 'data' => array_merge($payload['data'], ['key' => array_merge($payload['data']['key'])])]),
    ]);

    // Force same provider_message_id on both by rebuilding payload data id.
    $event2->update(['payload' => [
        'id' => 'evt-job-2',
        'event' => 'messages.upsert',
        'instance' => 'default',
        'data' => [
            'key' => [
                'remoteJid' => '628111222333@s.whatsapp.net',
                'fromMe' => false,
                'id' => 'provider-dup',
            ],
            'message' => ['conversation' => 'Halo'],
        ],
    ]]);

    (new ProcessProviderEventJob($event1->id))->handle();
    (new ProcessProviderEventJob($event2->id))->handle();

    expect(CommunicationMessage::count())->toBe(1)
        ->and($event1->fresh()->processed_at)->not->toBeNull()
        ->and($event2->fresh()->processed_at)->not->toBeNull();
});

it('stores different provider messages separately', function () {
    $conversation = makeConversationForIdempotency();

    CommunicationMessage::create([
        'conversation_id' => $conversation->id,
        'channel' => 'WHATSAPP',
        'direction' => 'inbound',
        'body' => 'first',
        'provider' => 'evolution',
        'provider_message_id' => 'id-1',
        'status' => 'received',
    ]);

    CommunicationMessage::create([
        'conversation_id' => $conversation->id,
        'channel' => 'WHATSAPP',
        'direction' => 'inbound',
        'body' => 'second',
        'provider' => 'evolution',
        'provider_message_id' => 'id-2',
        'status' => 'received',
    ]);

    expect(CommunicationMessage::count())->toBe(2);
});

it('allows multiple messages without provider_message_id', function () {
    $conversation = makeConversationForIdempotency();

    CommunicationMessage::create([
        'conversation_id' => $conversation->id,
        'channel' => 'WHATSAPP',
        'direction' => 'inbound',
        'body' => 'first',
        'provider' => 'evolution',
        'provider_message_id' => null,
        'status' => 'received',
    ]);

    CommunicationMessage::create([
        'conversation_id' => $conversation->id,
        'channel' => 'WHATSAPP',
        'direction' => 'inbound',
        'body' => 'second',
        'provider' => 'evolution',
        'provider_message_id' => null,
        'status' => 'received',
    ]);

    expect(CommunicationMessage::count())->toBe(2);
});

it('does not treat a non-unique database error as a duplicate', function () {
    // Force a foreign-key violation by referencing a non-existent conversation.
    expect(fn () => CommunicationMessage::create([
        'conversation_id' => '00000000-0000-0000-0000-000000000000',
        'channel' => 'WHATSAPP',
        'direction' => 'inbound',
        'body' => 'bad',
        'provider' => 'evolution',
        'provider_message_id' => 'bad-id',
        'status' => 'received',
    ]))->toThrow(QueryException::class);
});
