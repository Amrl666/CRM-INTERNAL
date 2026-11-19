<?php

use App\Domain\Communications\Models\CommunicationAccount;
use App\Domain\Communications\Models\CommunicationMessage;
use App\Livewire\Inbox\Gmail;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(RoleAndPermissionSeeder::class)->run();

    config()->set(
        'communications.email.gmail.primary_email',
        'enigmativebusiness@gmail.com'
    );
});

function gmailInboxTestUser(string $role = 'admin'): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

function gmailInboxTestAccount(
    string $email = 'enigmativebusiness@gmail.com'
): CommunicationAccount {
    return CommunicationAccount::create([
        'provider' => 'gmail',
        'account_email' => $email,
        'display_name' => 'Enigma Collective',
        'access_token' => 'test-access-token',
        'refresh_token' => 'test-refresh-token',
        'token_expires_at' => now()->addHour(),
        'connection_status' => 'connected',
    ]);
}

function gmailInboxTestMessage(
    CommunicationAccount $account,
    array $attributes = []
): CommunicationMessage {
    static $sequence = 0;
    $sequence++;

    return CommunicationMessage::create(array_merge([
        'account_id' => $account->id,
        'channel' => 'email',
        'direction' => 'inbound',
        'subject' => 'Test Subject '.$sequence,
        'body' => 'Test email body '.$sequence,
        'provider' => 'gmail',
        'provider_message_id' => 'gmail-test-'.$sequence,
        'thread_id' => 'thread-'.$sequence,
        'status' => 'received',
        'sender_email' => 'sender@example.test',
        'sender_name' => 'Test Sender',
        'to_recipients' => [['email' => $account->account_email]],
        'gmail_label_ids' => ['INBOX'],
        'received_at' => now(),
        'is_read' => false,
        'is_starred' => false,
    ], $attributes));
}

it('requires inbox.view permission', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test(Gmail::class)->assertForbidden();
});

it('lists messages from the configured primary Gmail account only', function () {
    $user = gmailInboxTestUser();
    $primary = gmailInboxTestAccount();
    $secondary = gmailInboxTestAccount('dimasauliaaa@gmail.com');

    gmailInboxTestMessage($primary, [
        'subject' => 'Primary Gmail Message',
    ]);

    gmailInboxTestMessage($secondary, [
        'subject' => 'Secondary Gmail Secret',
    ]);

    $this->actingAs($user);

    Livewire::test(Gmail::class)
        ->assertSee('Primary Gmail Message')
        ->assertDontSee('Secondary Gmail Secret');
});

it('searches Gmail messages by subject sender and body', function () {
    $user = gmailInboxTestUser();
    $account = gmailInboxTestAccount();

    gmailInboxTestMessage($account, [
        'subject' => 'Invoice November',
        'sender_email' => 'billing@example.test',
        'body' => 'Invoice reference INV-2026-001',
    ]);

    $this->actingAs($user);

    Livewire::test(Gmail::class)
        ->set('search', 'Invoice November')
        ->assertSee('Invoice November')
        ->set('search', 'billing@example.test')
        ->assertSee('Invoice November')
        ->set('search', 'INV-2026-001')
        ->assertSee('Invoice November');
});

it('filters unread and starred Gmail messages', function () {
    $user = gmailInboxTestUser();
    $account = gmailInboxTestAccount();

    gmailInboxTestMessage($account, [
        'subject' => 'Unread Email',
        'is_read' => false,
        'is_starred' => false,
    ]);

    gmailInboxTestMessage($account, [
        'subject' => 'Read Email',
        'is_read' => true,
        'is_starred' => false,
    ]);

    gmailInboxTestMessage($account, [
        'subject' => 'Starred Email',
        'is_read' => true,
        'is_starred' => true,
    ]);

    $this->actingAs($user);

    Livewire::test(Gmail::class)
        ->set('filter', 'unread')
        ->assertSee('Unread Email')
        ->assertDontSee('Read Email')
        ->assertDontSee('Starred Email');

    Livewire::test(Gmail::class)
        ->set('filter', 'starred')
        ->assertSee('Starred Email')
        ->assertDontSee('Unread Email')
        ->assertDontSee('Read Email');
});

it('opens a Gmail message and displays its entire thread', function () {
    $user = gmailInboxTestUser();
    $account = gmailInboxTestAccount();

    $first = gmailInboxTestMessage($account, [
        'subject' => 'Thread Conversation',
        'thread_id' => 'thread-shared-001',
        'body' => 'First email in thread',
    ]);

    $second = gmailInboxTestMessage($account, [
        'subject' => 'Re: Thread Conversation',
        'thread_id' => 'thread-shared-001',
        'body' => 'Second email in thread',
    ]);

    $this->actingAs($user);

    Livewire::test(Gmail::class)
        ->call('selectMessage', $first->id)
        ->assertSee('Thread Conversation')
        ->assertSee('First email in thread')
        ->assertSee('Second email in thread');
});

it('does not allow selecting a message belonging to another Gmail account', function () {
    $user = gmailInboxTestUser();
    gmailInboxTestAccount();

    $secondary = gmailInboxTestAccount('dimasauliaaa@gmail.com');

    $message = gmailInboxTestMessage($secondary, [
        'subject' => 'Private Secondary Email',
    ]);

    $this->actingAs($user);

    Livewire::test(Gmail::class)
        ->call('selectMessage', $message->id)
        ->assertNotFound();
});

it('shows an empty state when the primary Gmail account is disconnected', function () {
    $user = gmailInboxTestUser();

    gmailInboxTestAccount()->update([
        'connection_status' => 'disconnected',
    ]);

    $this->actingAs($user);

    Livewire::test(Gmail::class)
        ->assertSee('Akun Gmail utama belum terhubung.')
        ->assertSee('Koneksi Gmail terputus. Hubungkan ulang akun utama di Pengaturan.');
});

it('hides soft-deleted and archived Gmail messages from the inbox', function () {
    $user = gmailInboxTestUser();
    $account = gmailInboxTestAccount();

    gmailInboxTestMessage($account, [
        'subject' => 'Deleted Gmail Message',
    ])->update(['status' => 'deleted']);

    gmailInboxTestMessage($account, [
        'subject' => 'Archived Gmail Message',
        'gmail_label_ids' => ['STARRED'],
    ]);

    $this->actingAs($user);

    Livewire::test(Gmail::class)
        ->assertDontSee('Deleted Gmail Message')
        ->assertDontSee('Archived Gmail Message');
});

it('does not change Gmail read or starred state when opening a message', function () {
    $user = gmailInboxTestUser();
    $account = gmailInboxTestAccount();

    $message = gmailInboxTestMessage($account, [
        'is_read' => false,
        'is_starred' => true,
    ]);

    $this->actingAs($user);

    Livewire::test(Gmail::class)
        ->call('selectMessage', $message->id)
        ->assertSee('Belum dibaca')
        ->assertSee('Berbintang');

    expect($message->fresh()->is_read)->toBeFalse()
        ->and($message->fresh()->is_starred)->toBeTrue();
});
