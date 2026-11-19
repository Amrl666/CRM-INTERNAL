<?php

use App\Domain\Communications\Actions\SendWhatsAppTextAction;
use App\Domain\Communications\Contracts\WhatsAppProvider;
use App\Domain\Communications\Models\CommunicationMessage;
use App\Domain\Communications\Models\Conversation;
use App\Domain\Communications\Models\ProviderEvent;
use App\Jobs\ProcessProviderEventJob;
use App\Jobs\SendWhatsAppTextJob;
use App\Livewire\Inbox\Index;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(RoleAndPermissionSeeder::class)->run();
});

function outboundUser(): User
{
    $user = User::factory()->create();
    $user->assignRole('admin');

    return $user;
}

function outboundConversation(): Conversation
{
    return Conversation::create([
        'provider' => 'evolution',
        'instance' => 'default',
        'remote_jid' => '6281234567890@s.whatsapp.net',
        'phone_e164' => '+6281234567890',
        'status' => 'open',
    ]);
}

it('sends a text message successfully and stores provider message id', function () {
    Http::fake([
        '*/message/sendText/*' => Http::response([
            'key' => ['id' => 'provider-123', 'fromMe' => true],
            'message' => ['conversation' => 'Halo'],
            'status' => 'PENDING',
        ], 201),
    ]);

    $conversation = outboundConversation();
    $message = CommunicationMessage::create([
        'conversation_id' => $conversation->id,
        'channel' => 'WHATSAPP',
        'direction' => 'outbound',
        'body' => 'Halo',
        'provider' => 'evolution',
        'status' => 'pending',
    ]);

    (new SendWhatsAppTextJob($message->id))->handle(app(WhatsAppProvider::class));

    $message->refresh();
    expect($message->status)->toBe('sent')
        ->and($message->provider_message_id)->toBe('provider-123')
        ->and(Http::recorded())->toHaveCount(1);
});

it('transitions pending to sending to sent', function () {
    Http::fake([
        '*/message/sendText/*' => Http::response([
            'key' => ['id' => 'provider-xyz', 'fromMe' => true],
            'status' => 'PENDING',
        ], 201),
    ]);

    $conversation = outboundConversation();
    $message = CommunicationMessage::create([
        'conversation_id' => $conversation->id,
        'channel' => 'WHATSAPP',
        'direction' => 'outbound',
        'body' => 'Halo',
        'provider' => 'evolution',
        'status' => 'pending',
    ]);

    expect($message->status)->toBe('pending');

    (new SendWhatsAppTextJob($message->id))->handle(app(WhatsAppProvider::class));

    expect($message->fresh()->status)->toBe('sent');
});

it('marks a clear rejection as failed', function () {
    Http::fake([
        '*/message/sendText/*' => Http::response(['error' => 'bad'], 400),
    ]);

    $conversation = outboundConversation();
    $message = CommunicationMessage::create([
        'conversation_id' => $conversation->id,
        'channel' => 'WHATSAPP',
        'direction' => 'outbound',
        'body' => 'Halo',
        'provider' => 'evolution',
        'status' => 'pending',
    ]);

    (new SendWhatsAppTextJob($message->id))->handle(app(WhatsAppProvider::class));

    expect($message->fresh()->status)->toBe('failed');
});

it('marks a timeout as unknown', function () {
    Http::fake(function () {
        throw new ConnectionException('Connection timed out');
    });

    $conversation = outboundConversation();
    $message = CommunicationMessage::create([
        'conversation_id' => $conversation->id,
        'channel' => 'WHATSAPP',
        'direction' => 'outbound',
        'body' => 'Halo',
        'provider' => 'evolution',
        'status' => 'pending',
    ]);

    (new SendWhatsAppTextJob($message->id))->handle(app(WhatsAppProvider::class));

    expect($message->fresh()->status)->toBe('unknown');
});

it('does not automatically retry an unknown message', function () {
    Http::fake(function () {
        throw new ConnectionException('Connection timed out');
    });

    $conversation = outboundConversation();
    $message = CommunicationMessage::create([
        'conversation_id' => $conversation->id,
        'channel' => 'WHATSAPP',
        'direction' => 'outbound',
        'body' => 'Halo',
        'provider' => 'evolution',
        'status' => 'pending',
    ]);

    $job = new SendWhatsAppTextJob($message->id);
    $job->tries = 1;

    $job->handle(app(WhatsAppProvider::class));

    expect($message->fresh()->status)->toBe('unknown');
});

it('does not send a second time when the job runs twice', function () {
    Http::fake([
        '*/message/sendText/*' => Http::response([
            'key' => ['id' => 'provider-once', 'fromMe' => true],
            'status' => 'PENDING',
        ], 201),
    ]);

    $conversation = outboundConversation();
    $message = CommunicationMessage::create([
        'conversation_id' => $conversation->id,
        'channel' => 'WHATSAPP',
        'direction' => 'outbound',
        'body' => 'Halo',
        'provider' => 'evolution',
        'status' => 'pending',
    ]);

    $provider = app(WhatsAppProvider::class);

    (new SendWhatsAppTextJob($message->id))->handle($provider);
    (new SendWhatsAppTextJob($message->id))->handle($provider);

    expect(Http::recorded())->toHaveCount(1);
});

it('prevents users without inbox.manage from sending', function () {
    $user = User::factory()->create();
    $user->assignRole('viewer');
    $conversation = outboundConversation();

    $this->actingAs($user);

    Livewire::test(Index::class)
        ->set('selected', $conversation->id)
        ->set('messageText', 'Halo')
        ->call('sendMessage', app(SendWhatsAppTextAction::class))
        ->assertForbidden();
});

it('reconciles a webhook fromMe with an existing outbound message', function () {
    $conversation = outboundConversation();
    $message = CommunicationMessage::create([
        'conversation_id' => $conversation->id,
        'channel' => 'WHATSAPP',
        'direction' => 'outbound',
        'body' => 'Halo',
        'provider' => 'evolution',
        'status' => 'sent',
        'provider_message_id' => 'provider-webhook-1',
    ]);

    $payload = [
        'id' => 'evt-fromme-1',
        'event' => 'messages.upsert',
        'instance' => 'default',
        'data' => [
            'key' => [
                'remoteJid' => '6281234567890@s.whatsapp.net',
                'fromMe' => true,
                'id' => 'provider-webhook-1',
            ],
            'message' => ['conversation' => 'Halo'],
            'messageTimestamp' => 1700000000,
        ],
    ];

    $event = ProviderEvent::create([
        'provider' => 'evolution',
        'event_id' => 'evt-fromme-1',
        'event_type' => 'messages.upsert',
        'payload' => $payload,
    ]);

    (new ProcessProviderEventJob($event->id))->handle();

    expect(CommunicationMessage::count())->toBe(1)
        ->and($message->fresh()->status)->toBe('sent');
});

it('does not create a new message for duplicate fromMe webhooks', function () {
    $conversation = outboundConversation();
    CommunicationMessage::create([
        'conversation_id' => $conversation->id,
        'channel' => 'WHATSAPP',
        'direction' => 'outbound',
        'body' => 'Halo',
        'provider' => 'evolution',
        'status' => 'sent',
        'provider_message_id' => 'provider-dup-webhook',
    ]);

    $payload = [
        'id' => 'evt-fromme-dup',
        'event' => 'messages.upsert',
        'instance' => 'default',
        'data' => [
            'key' => [
                'remoteJid' => '6281234567890@s.whatsapp.net',
                'fromMe' => true,
                'id' => 'provider-dup-webhook',
            ],
            'message' => ['conversation' => 'Halo'],
        ],
    ];

    $event = ProviderEvent::create([
        'provider' => 'evolution',
        'event_id' => 'evt-fromme-dup',
        'event_type' => 'messages.upsert',
        'payload' => $payload,
    ]);

    (new ProcessProviderEventJob($event->id))->handle();

    expect(CommunicationMessage::count())->toBe(1);
});

it('handles a fromMe webhook arriving before the api response safely', function () {
    $conversation = outboundConversation();
    $message = CommunicationMessage::create([
        'conversation_id' => $conversation->id,
        'channel' => 'WHATSAPP',
        'direction' => 'outbound',
        'body' => 'Halo',
        'provider' => 'evolution',
        'status' => 'pending',
    ]);

    $payload = [
        'id' => 'evt-fromme-before',
        'event' => 'messages.upsert',
        'instance' => 'default',
        'data' => [
            'key' => [
                'remoteJid' => '6281234567890@s.whatsapp.net',
                'fromMe' => true,
                'id' => 'provider-before',
            ],
            'message' => ['conversation' => 'Halo'],
        ],
    ];

    $event = ProviderEvent::create([
        'provider' => 'evolution',
        'event_id' => 'evt-fromme-before',
        'event_type' => 'messages.upsert',
        'payload' => $payload,
    ]);

    (new ProcessProviderEventJob($event->id))->handle();

    // Without a previously stored provider message id, the event stays
    // processed but the outbound message is not changed automatically.
    expect($event->fresh()->processed_at)->not->toBeNull()
        ->and($message->fresh()->status)->toBe('pending')
        ->and($message->fresh()->provider_message_id)->toBeNull();
});

it('keeps inbound processing working', function () {
    $payload = [
        'id' => 'evt-inbound-still',
        'event' => 'messages.upsert',
        'instance' => 'default',
        'data' => [
            'key' => [
                'remoteJid' => '628999@s.whatsapp.net',
                'fromMe' => false,
                'id' => 'msg-inbound-still',
            ],
            'message' => ['conversation' => 'Masuk'],
        ],
    ];

    $event = ProviderEvent::create([
        'provider' => 'evolution',
        'event_id' => 'evt-inbound-still',
        'event_type' => 'messages.upsert',
        'payload' => $payload,
    ]);

    (new ProcessProviderEventJob($event->id))->handle();

    expect(CommunicationMessage::where('direction', 'inbound')->count())->toBe(1);
});

it('does not auto-match two outbound messages with identical body', function () {
    $conversation = outboundConversation();
    CommunicationMessage::create([
        'conversation_id' => $conversation->id,
        'channel' => 'WHATSAPP',
        'direction' => 'outbound',
        'body' => 'Halo',
        'provider' => 'evolution',
        'status' => 'pending',
    ]);
    $second = CommunicationMessage::create([
        'conversation_id' => $conversation->id,
        'channel' => 'WHATSAPP',
        'direction' => 'outbound',
        'body' => 'Halo',
        'provider' => 'evolution',
        'status' => 'pending',
    ]);

    $payload = [
        'id' => 'evt-fromme-ambiguous',
        'event' => 'messages.upsert',
        'instance' => 'default',
        'data' => [
            'key' => [
                'remoteJid' => '6281234567890@s.whatsapp.net',
                'fromMe' => true,
                'id' => 'provider-ambiguous',
            ],
            'message' => ['conversation' => 'Halo'],
        ],
    ];

    $event = ProviderEvent::create([
        'provider' => 'evolution',
        'event_id' => 'evt-fromme-ambiguous',
        'event_type' => 'messages.upsert',
        'payload' => $payload,
    ]);

    (new ProcessProviderEventJob($event->id))->handle();

    expect($second->fresh()->provider_message_id)->toBeNull()
        ->and($second->fresh()->status)->toBe('pending');
});
