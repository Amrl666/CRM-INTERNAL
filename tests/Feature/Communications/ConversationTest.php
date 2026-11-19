<?php

use App\Domain\Communications\Models\CommunicationMessage;
use App\Domain\Communications\Models\Conversation;
use App\Domain\Contacts\Models\Contact;
use App\Domain\Leads\Models\Lead;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('can create a conversation', function () {
    $conversation = Conversation::create([
        'provider' => 'evolution',
        'instance' => 'default',
        'remote_jid' => '6281234567890@s.whatsapp.net',
        'status' => 'open',
    ]);

    expect($conversation->id)->not->toBeNull()
        ->and($conversation->status)->toBe('open')
        ->and($conversation->phone_e164)->toBeNull();
});

it('enforces unique provider, instance, remote_jid', function () {
    Conversation::create([
        'provider' => 'evolution',
        'instance' => 'default',
        'remote_jid' => '6281234567890@s.whatsapp.net',
    ]);

    expect(fn () => Conversation::create([
        'provider' => 'evolution',
        'instance' => 'default',
        'remote_jid' => '6281234567890@s.whatsapp.net',
    ]))->toThrow(QueryException::class);
});

it('allows nullable phone_e164', function () {
    $conversation = Conversation::create([
        'provider' => 'evolution',
        'instance' => 'default',
        'remote_jid' => '6281234567890@s.whatsapp.net',
        'phone_e164' => null,
    ]);

    expect($conversation->phone_e164)->toBeNull();
});

it('allows nullable contact_id and lead_id', function () {
    $conversation = Conversation::create([
        'provider' => 'evolution',
        'instance' => 'default',
        'remote_jid' => '6281234567890@s.whatsapp.net',
        'contact_id' => null,
        'lead_id' => null,
    ]);

    expect($conversation->contact_id)->toBeNull()
        ->and($conversation->lead_id)->toBeNull()
        ->and($conversation->contact)->toBeNull()
        ->and($conversation->lead)->toBeNull();
});

it('can attach contact and lead to a conversation', function () {
    $contact = Contact::factory()->create();
    $lead = Lead::factory()->create();

    $conversation = Conversation::create([
        'provider' => 'evolution',
        'instance' => 'default',
        'remote_jid' => '6281234567890@s.whatsapp.net',
        'contact_id' => $contact->id,
        'lead_id' => $lead->id,
    ]);

    expect($conversation->contact->is($contact))->toBeTrue()
        ->and($conversation->lead->is($lead))->toBeTrue();
});

it('keeps legacy communication messages valid without conversation_id', function () {
    $message = CommunicationMessage::create([
        'channel' => 'EMAIL',
        'direction' => 'outbound',
        'status' => 'draft',
        'body' => 'legacy',
    ]);

    expect($message->id)->not->toBeNull()
        ->and($message->conversation_id)->toBeNull();
});

it('can link a communication message to a conversation', function () {
    $conversation = Conversation::create([
        'provider' => 'evolution',
        'instance' => 'default',
        'remote_jid' => '6281234567890@s.whatsapp.net',
    ]);

    $message = CommunicationMessage::create([
        'channel' => 'WHATSAPP',
        'direction' => 'inbound',
        'status' => 'received',
        'conversation_id' => $conversation->id,
        'provider_payload' => ['foo' => 'bar'],
    ]);

    expect($message->conversation->is($conversation))->toBeTrue()
        ->and($message->provider_payload)->toBe(['foo' => 'bar']);
});
