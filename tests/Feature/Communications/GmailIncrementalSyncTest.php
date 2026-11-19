<?php

use App\Domain\Communications\Actions\DispatchGmailSyncAction;
use App\Domain\Communications\Models\CommunicationAccount;
use App\Domain\Communications\Models\CommunicationMessage;
use App\Domain\Communications\Models\Conversation;
use App\Domain\Communications\Models\MessageEvent;
use App\Domain\Communications\Services\GmailAccountAuthenticator;
use App\Domain\Communications\Services\GmailApiService;
use App\Domain\Communications\Services\GmailHistoryExpiredException;
use App\Domain\Communications\Services\GmailMessageMapper;
use App\Domain\Communications\Services\GmailMessageSyncService;
use App\Domain\Communications\Services\GmailSyncStatus;
use App\Jobs\SyncGmailMessagesJob;
use App\Livewire\Inbox\Gmail as GmailInbox;
use App\Livewire\Inbox\Index as WhatsAppInbox;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Google\Client as GoogleClient;
use Google\Service\Exception as GoogleServiceException;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'communications.email.gmail.client_id' => 'test-client-id',
        'communications.email.gmail.client_secret' => 'test-client-secret',
        'communications.email.gmail.redirect_uri' => 'https://example.test/callback',
        'communications.email.gmail.primary_email' => 'hello@example.test',
    ]);
});

function gmailFTestAccount(array $attributes = []): CommunicationAccount
{
    return CommunicationAccount::create(array_merge([
        'provider' => 'gmail',
        'account_email' => 'hello@example.test',
        'display_name' => 'Test Gmail',
        'access_token' => 'test-access-token',
        'refresh_token' => 'test-refresh-token',
        'token_expires_at' => now()->addHour(),
        'connection_status' => 'connected',
    ], $attributes));
}

function gmailFTestResponse(int $status, array $body): Response
{
    return new Response(
        $status,
        ['Content-Type' => 'application/json'],
        json_encode($body, JSON_THROW_ON_ERROR),
    );
}

function gmailFTestService(array $responses): GmailMessageSyncService
{
    $client = new GoogleClient;

    $client->setHttpClient(new HttpClient([
        'handler' => HandlerStack::create(new MockHandler($responses)),
    ]));

    $authenticator = new GmailAccountAuthenticator($client);

    return new GmailMessageSyncService(
        new GmailApiService($authenticator),
        new GmailMessageMapper,
        $authenticator,
    );
}

function gmailFTestMessage(string $id, array $labels = ['INBOX']): array
{
    $body = 'Incremental Gmail body for '.$id;
    $encoded = rtrim(strtr(base64_encode($body), '+/', '-_'), '=');

    return [
        'id' => $id,
        'threadId' => 'thread-'.$id,
        'labelIds' => $labels,
        'internalDate' => '1731931200000',
        'snippet' => $body,
        'payload' => [
            'mimeType' => 'text/plain',
            'headers' => [
                ['name' => 'From', 'value' => 'Sender <sender@example.test>'],
                ['name' => 'To', 'value' => 'Recipient <hello@example.test>'],
                ['name' => 'Subject', 'value' => 'Incremental '.$id],
            ],
            'body' => [
                'size' => strlen($body),
                'data' => $encoded,
            ],
        ],
    ];
}

function gmailFTestRef(string $id): array
{
    return ['id' => $id, 'threadId' => 'thread-'.$id];
}

function gmailFTestStoredMessage(
    CommunicationAccount $account,
    string $providerId,
    array $labels = ['INBOX'],
    string $status = 'received',
): CommunicationMessage {
    return CommunicationMessage::create([
        'account_id' => $account->id,
        'channel' => 'email',
        'direction' => 'inbound',
        'subject' => 'Stored '.$providerId,
        'body' => 'Stored body',
        'provider' => 'gmail',
        'provider_message_id' => $providerId,
        'thread_id' => 'thread-'.$providerId,
        'status' => $status,
        'sender_email' => 'sender@example.test',
        'gmail_label_ids' => $labels,
        'is_read' => ! in_array('UNREAD', $labels, true),
        'is_starred' => in_array('STARRED', $labels, true),
        'received_at' => now(),
    ]);
}

it('keeps the full sync paginated and stores the history id only after completion', function () {
    $account = gmailFTestAccount();

    $service = gmailFTestService([
        gmailFTestResponse(200, [
            'messages' => [],
            'nextPageToken' => 'page-2',
            'resultSizeEstimate' => 50,
        ]),
        gmailFTestResponse(200, [
            'messages' => [],
            'resultSizeEstimate' => 0,
        ]),
        gmailFTestResponse(200, [
            'historyId' => '12345',
            'emailAddress' => 'hello@example.test',
        ]),
    ]);

    $first = $service->syncPage($account);

    expect($first['next_page_token'])->toBe('page-2')
        ->and($account->fresh()->gmail_history_id)->toBeNull();

    $last = $service->syncPage($account, 'page-2');

    expect($last['next_page_token'])->toBeNull()
        ->and($account->fresh()->gmail_history_id)->toBeNull();

    $checkpoint = $service->captureCheckpoint($account);

    expect($checkpoint)->toBe('12345')
        ->and($account->fresh()->gmail_history_id)->toBe('12345');
});

it('captures a pre-sync baseline and catches up changes arriving during full sync', function () {
    $account = gmailFTestAccount();
    $status = app(GmailSyncStatus::class);
    $runId = (string) Str::uuid();

    $status->start($account->id, $runId);

    $service = gmailFTestService([
        gmailFTestResponse(200, [
            'historyId' => '100',
            'emailAddress' => 'hello@example.test',
        ]),
        gmailFTestResponse(200, [
            'messages' => [],
            'resultSizeEstimate' => 0,
        ]),
        gmailFTestResponse(200, [
            'history' => [[
                'id' => '101',
                'messagesAdded' => [
                    ['message' => gmailFTestRef('gmail-during-full')],
                ],
            ]],
            'historyId' => '200',
        ]),
        gmailFTestResponse(200, gmailFTestMessage('gmail-during-full')),
    ]);

    Queue::fake();

    (new SyncGmailMessagesJob($account->id, null, 1, $runId))
        ->handle($service, $status);

    $catchUp = Queue::pushed(SyncGmailMessagesJob::class)->first();

    expect($catchUp)->not->toBeNull()
        ->and($catchUp->mode)->toBe(SyncGmailMessagesJob::MODE_INCREMENTAL)
        ->and($catchUp->historyId)->toBe('100')
        ->and($catchUp->runId)->toBe($runId)
        ->and($status->isRunning($account->id))->toBeTrue()
        ->and($account->fresh()->gmail_history_id)->toBeNull()
        ->and($account->fresh()->last_synced_at)->toBeNull();

    $catchUp->handle($service, $status);

    expect(CommunicationMessage::query()
        ->where('provider_message_id', 'gmail-during-full')
        ->count())->toBe(1)
        ->and($account->fresh()->gmail_history_id)->toBe('200')
        ->and($status->isRunning($account->id))->toBeFalse()
        ->and($account->fresh()->last_synced_at)->not->toBeNull();
});

it('processes only changes from the history API', function () {
    $account = gmailFTestAccount(['gmail_history_id' => '100']);
    gmailFTestStoredMessage($account, 'gmail-old-1');

    $service = gmailFTestService([
        gmailFTestResponse(200, [
            'history' => [[
                'id' => '101',
                'messagesAdded' => [
                    ['message' => gmailFTestRef('gmail-new-1')],
                ],
            ]],
            'historyId' => '200',
        ]),
        gmailFTestResponse(200, gmailFTestMessage('gmail-new-1')),
    ]);

    $result = $service->syncHistoryPage($account, '100');

    expect($result['processed'])->toBe(1)
        ->and($result['next_page_token'])->toBeNull()
        ->and($result['history_id'])->toBe('200')
        ->and(CommunicationMessage::query()
            ->where('provider_message_id', 'gmail-new-1')
            ->count())->toBe(1)
        ->and(CommunicationMessage::query()
            ->where('provider_message_id', 'gmail-old-1')
            ->count())->toBe(1);
});

it('persists messagesAdded idempotently', function () {
    $account = gmailFTestAccount(['gmail_history_id' => '100']);

    $history = [
        'history' => [[
            'id' => '101',
            'messagesAdded' => [
                ['message' => gmailFTestRef('gmail-added-1')],
            ],
        ]],
        'historyId' => '200',
    ];

    $service = gmailFTestService([
        gmailFTestResponse(200, $history),
        gmailFTestResponse(200, gmailFTestMessage('gmail-added-1')),
        gmailFTestResponse(200, $history),
        gmailFTestResponse(200, gmailFTestMessage('gmail-added-1')),
    ]);

    $service->syncHistoryPage($account, '100');
    $second = $service->syncHistoryPage($account, '100');

    expect($second['processed'])->toBe(1)
        ->and(CommunicationMessage::query()
            ->where('account_id', $account->id)
            ->where('provider_message_id', 'gmail-added-1')
            ->count())->toBe(1);
});

it('soft deletes messagesDeleted and records an audit event', function () {
    $account = gmailFTestAccount(['gmail_history_id' => '100']);
    $stored = gmailFTestStoredMessage($account, 'gmail-deleted-1');

    $service = gmailFTestService([
        gmailFTestResponse(200, [
            'history' => [[
                'id' => '101',
                'messagesDeleted' => [
                    ['message' => gmailFTestRef('gmail-deleted-1')],
                ],
            ]],
            'historyId' => '200',
        ]),
    ]);

    $result = $service->syncHistoryPage($account, '100');

    expect($result['processed'])->toBe(1)
        ->and($stored->fresh()->status)->toBe('deleted')
        ->and(CommunicationMessage::query()->whereKey($stored->id)->exists())->toBeTrue()
        ->and(MessageEvent::query()
            ->where('message_id', $stored->id)
            ->where('event_type', 'gmail.message_deleted')
            ->count())->toBe(1);
});

it('applies labelsAdded from history', function () {
    $account = gmailFTestAccount(['gmail_history_id' => '100']);
    $stored = gmailFTestStoredMessage($account, 'gmail-label-add-1', ['INBOX', 'UNREAD']);

    $service = gmailFTestService([
        gmailFTestResponse(200, [
            'history' => [[
                'id' => '101',
                'labelsAdded' => [[
                    'labelIds' => ['STARRED'],
                    'message' => gmailFTestRef('gmail-label-add-1'),
                ]],
            ]],
            'historyId' => '200',
        ]),
    ]);

    $result = $service->syncHistoryPage($account, '100');

    expect($result['processed'])->toBe(1)
        ->and($stored->fresh()->is_starred)->toBeTrue()
        ->and($stored->fresh()->is_read)->toBeFalse()
        ->and($stored->fresh()->gmail_label_ids)->toContain('STARRED')
        ->and(MessageEvent::query()
            ->where('event_type', 'gmail.labels_changed')
            ->count())->toBe(1);
});

it('applies labelsRemoved from history', function () {
    $account = gmailFTestAccount(['gmail_history_id' => '100']);
    $stored = gmailFTestStoredMessage(
        $account,
        'gmail-label-remove-1',
        ['INBOX', 'UNREAD', 'STARRED'],
    );

    $service = gmailFTestService([
        gmailFTestResponse(200, [
            'history' => [[
                'id' => '101',
                'labelsRemoved' => [[
                    'labelIds' => ['UNREAD', 'STARRED'],
                    'message' => gmailFTestRef('gmail-label-remove-1'),
                ]],
            ]],
            'historyId' => '200',
        ]),
    ]);

    $result = $service->syncHistoryPage($account, '100');

    expect($result['processed'])->toBe(1)
        ->and($stored->fresh()->is_read)->toBeTrue()
        ->and($stored->fresh()->is_starred)->toBeFalse()
        ->and($stored->fresh()->gmail_label_ids)->not->toContain('UNREAD');
});

it('processes multi-page history while carrying the latest history id', function () {
    $account = gmailFTestAccount(['gmail_history_id' => '100']);
    $stored = gmailFTestStoredMessage($account, 'gmail-page-1', ['INBOX']);

    $service = gmailFTestService([
        gmailFTestResponse(200, [
            'history' => [[
                'id' => '101',
                'labelsAdded' => [[
                    'labelIds' => ['STARRED'],
                    'message' => gmailFTestRef('gmail-page-1'),
                ]],
            ]],
            'historyId' => '201',
            'nextPageToken' => 'history-page-2',
        ]),
        gmailFTestResponse(200, [
            'history' => [[
                'id' => '102',
                'messagesAdded' => [
                    ['message' => gmailFTestRef('gmail-page-2')],
                ],
            ]],
            'historyId' => '202',
        ]),
        gmailFTestResponse(200, gmailFTestMessage('gmail-page-2')),
    ]);

    $first = $service->syncHistoryPage($account, '100');

    expect($first['next_page_token'])->toBe('history-page-2')
        ->and($first['history_id'])->toBe('201')
        ->and($stored->fresh()->is_starred)->toBeTrue();

    $second = $service->syncHistoryPage($account, '100', 'history-page-2');

    expect($second['next_page_token'])->toBeNull()
        ->and($second['history_id'])->toBe('202')
        ->and(CommunicationMessage::query()
            ->where('provider_message_id', 'gmail-page-2')
            ->count())->toBe(1);
});

it('does not advance the checkpoint when a history page fails', function () {
    $account = gmailFTestAccount(['gmail_history_id' => '100']);
    $status = app(GmailSyncStatus::class);
    $runId = (string) Str::uuid();

    $status->start($account->id, $runId);

    $service = Mockery::mock(GmailMessageSyncService::class);
    $service->shouldReceive('syncHistoryPage')
        ->once()
        ->andThrow(new GoogleServiceException('Backend Error', 503));

    Queue::fake();

    $job = new SyncGmailMessagesJob(
        $account->id,
        'history-page-2',
        2,
        $runId,
        mode: SyncGmailMessagesJob::MODE_INCREMENTAL,
        historyId: '100',
        checkpointHistoryId: '201',
    );

    expect(fn () => $job->handle($service, $status))
        ->toThrow(GoogleServiceException::class);

    expect($account->fresh()->gmail_history_id)->toBe('100')
        ->and($status->isRunning($account->id))->toBeTrue()
        ->and($status->lastFailure($account->id))->toBeNull();

    Queue::assertNothingPushed();
});

it('does not advance the checkpoint after the run loses ownership', function () {
    $account = gmailFTestAccount(['gmail_history_id' => '100']);
    $status = app(GmailSyncStatus::class);
    $oldRunId = (string) Str::uuid();
    $newRunId = (string) Str::uuid();

    $status->start($account->id, $oldRunId);

    $service = Mockery::mock(GmailMessageSyncService::class);
    $service->shouldReceive('syncHistoryPage')
        ->once()
        ->andReturnUsing(function () use ($status, $account, $oldRunId, $newRunId): array {
            $status->markFailed($account->id, 'Run lost ownership.', $oldRunId);
            $status->start($account->id, $newRunId);

            return [
                'processed' => 0,
                'next_page_token' => null,
                'history_id' => '999',
            ];
        });
    $service->shouldNotReceive('commitIncrementalCheckpoint');

    Queue::fake();

    (new SyncGmailMessagesJob(
        $account->id,
        null,
        1,
        $oldRunId,
        mode: SyncGmailMessagesJob::MODE_INCREMENTAL,
        historyId: '100',
    ))->handle($service, $status);

    expect($account->fresh()->gmail_history_id)->toBe('100')
        ->and($status->currentRunId($account->id))->toBe($newRunId)
        ->and($status->isRunning($account->id))->toBeTrue();
});

it('treats a 404 while fetching a history-added message as benign', function () {
    $account = gmailFTestAccount(['gmail_history_id' => '100']);
    $stored = gmailFTestStoredMessage($account, 'gmail-vanished');

    $service = gmailFTestService([
        gmailFTestResponse(200, [
            'history' => [[
                'id' => '101',
                'messagesAdded' => [
                    ['message' => gmailFTestRef('gmail-vanished')],
                ],
            ]],
            'historyId' => '200',
        ]),
        gmailFTestResponse(404, [
            'error' => [
                'code' => 404,
                'message' => 'Message not found',
                'errors' => [[
                    'domain' => 'global',
                    'reason' => 'notFound',
                    'message' => 'Message not found',
                ]],
            ],
        ]),
    ]);

    $result = $service->syncHistoryPage($account, '100');

    expect($result['processed'])->toBe(0)
        ->and($result['history_id'])->toBe('200')
        ->and($stored->fresh()->status)->toBe('deleted')
        ->and($account->fresh()->gmail_history_id)->toBe('100');
});

it('does not mutate non-inbox Gmail records from history events', function () {
    $account = gmailFTestAccount(['gmail_history_id' => '100']);

    $draft = CommunicationMessage::create([
        'account_id' => $account->id,
        'channel' => 'email',
        'direction' => 'outbound',
        'subject' => 'Local draft',
        'body' => 'Draft body',
        'provider' => 'gmail',
        'provider_message_id' => 'gmail-draft-1',
        'status' => 'draft',
        'gmail_label_ids' => null,
    ]);

    $service = gmailFTestService([
        gmailFTestResponse(200, [
            'history' => [[
                'id' => '101',
                'messagesDeleted' => [
                    ['message' => gmailFTestRef('gmail-draft-1')],
                ],
            ]],
            'historyId' => '200',
        ]),
    ]);

    $result = $service->syncHistoryPage($account, '100');

    expect($result['processed'])->toBe(0)
        ->and($draft->fresh()->status)->toBe('draft')
        ->and(MessageEvent::query()->where('message_id', $draft->id)->count())->toBe(0);
});

it('raises a dedicated exception when Gmail reports expired history', function () {
    gmailFTestAccount(['gmail_history_id' => '100']);

    $service = gmailFTestService([
        gmailFTestResponse(404, [
            'error' => [
                'code' => 404,
                'message' => 'startHistoryId not found',
                'errors' => [[
                    'domain' => 'global',
                    'reason' => 'notFound',
                    'message' => 'startHistoryId not found',
                ]],
            ],
        ]),
    ]);

    expect(fn () => $service->syncHistoryPage(
        CommunicationAccount::query()->first(),
        '100',
    ))->toThrow(GmailHistoryExpiredException::class);
});

it('schedules a safe full resync when history expires', function () {
    $account = gmailFTestAccount(['gmail_history_id' => '100']);
    gmailFTestStoredMessage($account, 'gmail-keep-1');

    $status = app(GmailSyncStatus::class);
    $runId = (string) Str::uuid();

    $status->start($account->id, $runId);

    $service = Mockery::mock(GmailMessageSyncService::class);
    $service->shouldReceive('syncHistoryPage')
        ->once()
        ->andThrow(new GmailHistoryExpiredException);

    Queue::fake();

    (new SyncGmailMessagesJob(
        $account->id,
        null,
        1,
        $runId,
        mode: SyncGmailMessagesJob::MODE_INCREMENTAL,
        historyId: '100',
    ))->handle($service, $status);

    expect($account->fresh()->gmail_history_id)->toBeNull()
        ->and($status->isRunning($account->id))->toBeTrue()
        ->and($status->historyExpired($account->id))->not->toBeNull()
        ->and(CommunicationMessage::query()
            ->where('provider_message_id', 'gmail-keep-1')
            ->exists())->toBeTrue();

    Queue::assertPushedOn(
        'communications',
        SyncGmailMessagesJob::class,
        fn (SyncGmailMessagesJob $job): bool => $job->runId === $runId
            && $job->mode === SyncGmailMessagesJob::MODE_RESYNC
            && $job->pageNumber === 1,
    );
});

it('keeps run ownership and distinct unique keys for incremental sync', function () {
    $account = gmailFTestAccount(['gmail_history_id' => '100']);
    $status = app(GmailSyncStatus::class);
    $ownerRunId = (string) Str::uuid();
    $otherRunId = (string) Str::uuid();

    $status->start($account->id, $ownerRunId);

    $service = Mockery::mock(GmailMessageSyncService::class);
    $service->shouldNotReceive('syncHistoryPage');

    Queue::fake();

    (new SyncGmailMessagesJob(
        $account->id,
        null,
        1,
        $otherRunId,
        mode: SyncGmailMessagesJob::MODE_INCREMENTAL,
        historyId: '100',
    ))->handle($service, $status);

    $incremental = new SyncGmailMessagesJob(
        $account->id,
        null,
        1,
        $ownerRunId,
        mode: SyncGmailMessagesJob::MODE_INCREMENTAL,
        historyId: '100',
    );
    $full = new SyncGmailMessagesJob($account->id);

    expect($account->fresh()->gmail_history_id)->toBe('100')
        ->and($status->currentRunId($account->id))->toBe($ownerRunId)
        ->and($incremental->uniqueId())->not->toBe($full->uniqueId());

    Queue::assertNothingPushed();
});

it('never syncs a secondary Gmail account', function () {
    gmailFTestAccount();
    $secondary = gmailFTestAccount([
        'account_email' => 'dimasauliaaa@gmail.com',
        'display_name' => 'Dimas Aulia',
    ]);

    $service = gmailFTestService([]);

    expect(fn () => $service->syncHistoryPage($secondary, '100'))
        ->toThrow(RuntimeException::class);

    expect($secondary->fresh()->connection_status)->toBe('connected');
});

it('shows a clear resync status in the Gmail inbox', function () {
    app(RoleAndPermissionSeeder::class)->run();

    $account = gmailFTestAccount(['gmail_history_id' => '100']);
    $status = app(GmailSyncStatus::class);
    $runId = (string) Str::uuid();

    $status->start($account->id, $runId);
    $status->markHistoryExpired($account->id, $runId, 'History expired');

    $user = User::factory()->create();
    $user->assignRole('admin');

    $this->actingAs($user);

    Livewire::test(GmailInbox::class)
        ->assertSee('Riwayat Gmail kedaluwarsa.')
        ->assertDontSee('test-access-token')
        ->assertDontSee('test-refresh-token');
});

it('clears the resync status after a successful full resync', function () {
    $account = gmailFTestAccount();
    $status = app(GmailSyncStatus::class);
    $runId = (string) Str::uuid();

    $status->start($account->id, $runId);
    $status->markHistoryExpired($account->id, $runId, 'History expired');

    expect($status->historyExpired($account->id))->not->toBeNull()
        ->and($status->markCompleted($account->id, $runId))->toBeTrue()
        ->and($status->historyExpired($account->id))->toBeNull();
});

it('commits the final history checkpoint only after the last page', function () {
    $account = gmailFTestAccount(['gmail_history_id' => '100']);
    $status = app(GmailSyncStatus::class);
    $runId = (string) Str::uuid();

    $status->start($account->id, $runId);

    $service = gmailFTestService([
        gmailFTestResponse(200, [
            'history' => [],
            'historyId' => '202',
        ]),
    ]);

    Queue::fake();

    (new SyncGmailMessagesJob(
        $account->id,
        null,
        1,
        $runId,
        mode: SyncGmailMessagesJob::MODE_INCREMENTAL,
        historyId: '100',
        checkpointHistoryId: '201',
    ))->handle($service, $status);

    expect($account->fresh()->gmail_history_id)->toBe('202')
        ->and($status->isRunning($account->id))->toBeFalse()
        ->and($status->lastFailure($account->id))->toBeNull();

    Queue::assertNothingPushed();
});

it('dispatches incremental jobs when a history checkpoint exists', function () {
    $account = gmailFTestAccount(['gmail_history_id' => '100']);

    Queue::fake();

    $result = app(DispatchGmailSyncAction::class)->handle();

    expect($result->isDispatched())->toBeTrue();

    Queue::assertPushedOn(
        'communications',
        SyncGmailMessagesJob::class,
        fn (SyncGmailMessagesJob $job): bool => $job->mode === SyncGmailMessagesJob::MODE_INCREMENTAL
            && $job->historyId === '100',
    );
});

it('dispatches a full job when no history checkpoint exists', function () {
    gmailFTestAccount();

    Queue::fake();

    $result = app(DispatchGmailSyncAction::class)->handle();

    expect($result->isDispatched())->toBeTrue();

    Queue::assertPushedOn(
        'communications',
        SyncGmailMessagesJob::class,
        fn (SyncGmailMessagesJob $job): bool => $job->mode === SyncGmailMessagesJob::MODE_FULL
            && $job->historyId === null,
    );
});

it('keeps the WhatsApp inbox working while Gmail needs a resync', function () {
    app(RoleAndPermissionSeeder::class)->run();

    $account = gmailFTestAccount(['gmail_history_id' => '100']);
    $status = app(GmailSyncStatus::class);
    $runId = (string) Str::uuid();

    $status->start($account->id, $runId);
    $status->markHistoryExpired($account->id, $runId, 'History expired');

    Conversation::create([
        'provider' => 'evolution',
        'instance' => 'default',
        'remote_jid' => '628123@s.whatsapp.net',
        'last_message_at' => now(),
        'last_message_preview' => 'WhatsApp tetap tampil',
    ]);

    $user = User::factory()->create();
    $user->assignRole('admin');

    $this->actingAs($user);

    Livewire::test(WhatsAppInbox::class)
        ->assertSee('WhatsApp tetap tampil');
});
