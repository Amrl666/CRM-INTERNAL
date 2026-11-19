<?php

use App\Domain\Communications\Models\CommunicationMessage;
use App\Domain\Communications\Models\Conversation;
use App\Domain\Contacts\Models\Contact;
use App\Livewire\Inbox\Index;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(RoleAndPermissionSeeder::class)->run();
});

function inboxUser(string $role = 'admin'): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('requires inbox.view to access inbox', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('inbox.index'))->assertForbidden();
});

it('allows an authorized user to view inbox', function () {
    $user = inboxUser();
    $this->actingAs($user);

    $this->get(route('inbox.index'))->assertOk();
});

it('lists conversations', function () {
    $user = inboxUser();
    $conversation = Conversation::create([
        'provider' => 'evolution',
        'instance' => 'default',
        'remote_jid' => '628123@s.whatsapp.net',
        'last_message_at' => now(),
        'last_message_preview' => 'Halo',
    ]);

    $this->actingAs($user);

    Livewire::test(Index::class)
        ->assertSee('Halo');
});

it('searches by phone number', function () {
    $user = inboxUser();
    Conversation::create([
        'provider' => 'evolution',
        'instance' => 'default',
        'remote_jid' => '628999@s.whatsapp.net',
        'last_message_at' => now(),
        'last_message_preview' => 'Secret',
    ]);

    $this->actingAs($user);

    Livewire::test(Index::class)
        ->set('search', '628999')
        ->assertSee('Secret');
});

it('searches by contact name', function () {
    $user = inboxUser();
    $contact = Contact::factory()->create(['full_name' => 'Budi Santoso', 'phone_e164' => '+628123']);
    Conversation::create([
        'provider' => 'evolution',
        'instance' => 'default',
        'remote_jid' => '628123@s.whatsapp.net',
        'contact_id' => $contact->id,
        'last_message_at' => now(),
        'last_message_preview' => 'Hi',
    ]);

    $this->actingAs($user);

    Livewire::test(Index::class)
        ->set('search', 'Budi')
        ->assertSee('Budi Santoso');
});

it('filters unread conversations', function () {
    $user = inboxUser();
    $unread = Conversation::create([
        'provider' => 'evolution',
        'instance' => 'default',
        'remote_jid' => '628111@s.whatsapp.net',
        'last_message_at' => now(),
        'last_read_at' => null,
    ]);
    CommunicationMessage::create([
        'conversation_id' => $unread->id,
        'channel' => 'WHATSAPP',
        'direction' => 'inbound',
        'body' => 'new',
        'provider' => 'evolution',
        'status' => 'received',
    ]);

    $read = Conversation::create([
        'provider' => 'evolution',
        'instance' => 'default',
        'remote_jid' => '628222@s.whatsapp.net',
        'last_message_at' => now(),
        'last_read_at' => now(),
    ]);

    $this->actingAs($user);

    Livewire::test(Index::class)
        ->set('filter', 'unread')
        ->assertSee($unread->remote_jid);
});

it('filters open and closed conversations', function () {
    $user = inboxUser();
    Conversation::create([
        'provider' => 'evolution',
        'instance' => 'default',
        'remote_jid' => '628open@s.whatsapp.net',
        'status' => 'open',
        'last_message_at' => now(),
    ]);
    Conversation::create([
        'provider' => 'evolution',
        'instance' => 'default',
        'remote_jid' => '628closed@s.whatsapp.net',
        'status' => 'closed',
        'last_message_at' => now(),
    ]);

    $this->actingAs($user);

    Livewire::test(Index::class)
        ->set('filter', 'open')
        ->assertSee('628open@s.whatsapp.net')
        ->assertDontSee('628closed@s.whatsapp.net');

    Livewire::test(Index::class)
        ->set('filter', 'closed')
        ->assertSee('628closed@s.whatsapp.net')
        ->assertDontSee('628open@s.whatsapp.net');
});

it('shows conversation detail and messages', function () {
    $user = inboxUser();
    $conversation = Conversation::create([
        'provider' => 'evolution',
        'instance' => 'default',
        'remote_jid' => '628123@s.whatsapp.net',
        'last_message_at' => now(),
    ]);
    CommunicationMessage::create([
        'conversation_id' => $conversation->id,
        'channel' => 'WHATSAPP',
        'direction' => 'inbound',
        'body' => 'Masuk',
        'provider' => 'evolution',
        'status' => 'received',
    ]);
    CommunicationMessage::create([
        'conversation_id' => $conversation->id,
        'channel' => 'WHATSAPP',
        'direction' => 'outbound',
        'body' => 'Keluar',
        'provider' => 'evolution',
        'status' => 'sent',
    ]);

    $this->actingAs($user);

    Livewire::test(Index::class)
        ->call('selectConversation', $conversation->id)
        ->assertSee('Masuk')
        ->assertSee('Keluar');
});

it('marks conversation as read when opened', function () {
    $user = inboxUser();
    $conversation = Conversation::create([
        'provider' => 'evolution',
        'instance' => 'default',
        'remote_jid' => '628123@s.whatsapp.net',
        'last_message_at' => now(),
        'last_read_at' => null,
    ]);

    $this->actingAs($user);

    Livewire::test(Index::class)
        ->call('selectConversation', $conversation->id);

    expect($conversation->fresh()->last_read_at)->not->toBeNull();
});

it('closes and reopens a conversation', function () {
    $user = inboxUser('admin');
    $conversation = Conversation::create([
        'provider' => 'evolution',
        'instance' => 'default',
        'remote_jid' => '628123@s.whatsapp.net',
        'status' => 'open',
        'last_message_at' => now(),
    ]);

    $this->actingAs($user);

    Livewire::test(Index::class)
        ->call('closeConversation', $conversation->id);

    expect($conversation->fresh()->status)->toBe('closed');

    Livewire::test(Index::class)
        ->call('reopenConversation', $conversation->id);

    expect($conversation->fresh()->status)->toBe('open');
});

it('prevents users without inbox.manage from changing status', function () {
    $user = inboxUser('viewer'); // has inbox.view but not inbox.manage
    $conversation = Conversation::create([
        'provider' => 'evolution',
        'instance' => 'default',
        'remote_jid' => '628123@s.whatsapp.net',
        'status' => 'open',
        'last_message_at' => now(),
    ]);

    $this->actingAs($user);

    Livewire::test(Index::class)
        ->call('closeConversation', $conversation->id)
        ->assertForbidden();
});

it('shows media placeholder for inbound media messages', function () {
    $user = inboxUser();
    $conversation = Conversation::create([
        'provider' => 'evolution',
        'instance' => 'default',
        'remote_jid' => '628123@s.whatsapp.net',
        'last_message_at' => now(),
    ]);
    CommunicationMessage::create([
        'conversation_id' => $conversation->id,
        'channel' => 'WHATSAPP',
        'direction' => 'inbound',
        'body' => null,
        'provider' => 'evolution',
        'status' => 'received',
        'provider_payload' => [
            'data' => ['message' => ['imageMessage' => ['caption' => null]]],
        ],
    ]);

    $this->actingAs($user);

    Livewire::test(Index::class)
        ->call('selectConversation', $conversation->id)
        ->assertSee('Gambar');
});
