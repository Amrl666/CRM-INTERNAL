<?php

namespace App\Domain\Communications\Services;

use App\Domain\Communications\DTOs\GmailMessageData;
use App\Domain\Communications\Models\CommunicationAccount;
use App\Domain\Communications\Models\CommunicationMessage;
use App\Domain\Communications\Models\MessageEvent;
use Google\Service\Gmail\History;
use Google\Service\Gmail\Message;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class GmailMessageSyncService
{
    private const STATUS_DELETED = 'deleted';

    public function __construct(
        private readonly GmailApiService $gmail,
        private readonly GmailMessageMapper $mapper,
        private readonly GmailAccountAuthenticator $authenticator,
    ) {}

    /**
     * Synchronize one Gmail inbox page.
     *
     * @return array{processed: int, next_page_token: ?string}
     */
    public function syncPage(
        CommunicationAccount $account,
        ?string $pageToken = null,
    ): array {
        $this->assertSyncableAccount($account);

        $response = $this->gmail->listMessages(
            labelIds: ['INBOX'],
            pageToken: $pageToken,
            maxResults: 50,
        );

        $processed = 0;

        foreach ($response->getMessages() ?? [] as $listedMessage) {
            $messageId = trim((string) $listedMessage->getId());

            if ($messageId === '') {
                continue;
            }

            $message = $this->gmail->getMessage($messageId);
            $data = $this->mapper->map($message);

            $this->persistMessage($account, $data);
            $processed++;
        }

        return [
            'processed' => $processed,
            'next_page_token' => $response->getNextPageToken(),
        ];
    }

    /**
     * Capture the mailbox history id after a full sync has fully completed.
     *
     * This is the baseline for later incremental runs. It is never called for
     * a partial page, so no intermediate checkpoint can be committed.
     */
    public function captureCheckpoint(
        CommunicationAccount $account,
        ?string $historyId = null,
    ): ?string {
        $this->assertSyncableAccount($account);

        $historyId ??= $this->currentHistoryId($account);

        if (! is_string($historyId) || $historyId === '') {
            return null;
        }

        $account->forceFill([
            'gmail_history_id' => $historyId,
        ])->save();

        return $historyId;
    }

    /**
     * Read the mailbox history id without committing it.
     *
     * A full sync captures this before its first page so the following
     * incremental catch-up can observe messages arriving during the full sync.
     */
    public function currentHistoryId(CommunicationAccount $account): ?string
    {
        $this->assertSyncableAccount($account);

        $historyId = $this->gmail->getProfile()->getHistoryId();

        return is_string($historyId) && $historyId !== ''
            ? $historyId
            : null;
    }

    /**
     * Synchronize one page of mailbox changes since a history id.
     *
     * @return array{processed: int, next_page_token: ?string, history_id: ?string}
     */
    public function syncHistoryPage(
        CommunicationAccount $account,
        string $startHistoryId,
        ?string $pageToken = null,
    ): array {
        $this->assertSyncableAccount($account);

        if (trim($startHistoryId) === '') {
            throw new \InvalidArgumentException(
                'Gmail startHistoryId tidak boleh kosong.'
            );
        }

        $response = $this->gmail->listHistory(
            $startHistoryId,
            $pageToken,
        );

        $processed = 0;

        foreach ($response->getHistory() ?? [] as $event) {
            $processed += $this->applyHistoryEvent($account, $event);
        }

        $historyId = $response->getHistoryId();

        return [
            'processed' => $processed,
            'next_page_token' => $response->getNextPageToken(),
            'history_id' => is_string($historyId) && $historyId !== ''
                ? $historyId
                : null,
        ];
    }

    /**
     * Commit an incremental checkpoint after every page has been processed.
     */
    public function commitIncrementalCheckpoint(
        CommunicationAccount $account,
        string $historyId,
    ): bool {
        $this->assertSyncableAccount($account);

        if (trim($historyId) === '') {
            return false;
        }

        $account->forceFill([
            'gmail_history_id' => $historyId,
        ])->save();

        return true;
    }

    private function applyHistoryEvent(
        CommunicationAccount $account,
        History $event,
    ): int {
        $processed = 0;

        // Added messages are fetched in full because history records only
        // carry the message id and thread id.
        foreach ($event->getMessagesAdded() ?? [] as $added) {
            $messageId = trim((string) ($added->getMessage()?->getId() ?? ''));

            if ($messageId === '') {
                continue;
            }

            $message = $this->fetchMessageForChange($messageId);

            if ($message === null) {
                // The message vanished between history.list and getMessage.
                // Reflect that locally if we already had a record.
                $this->markMessageDeleted($account, $messageId);

                continue;
            }

            $data = $this->mapper->map($message);

            if (! in_array('INBOX', $data->labelIds, true)) {
                continue;
            }

            $this->persistMessage($account, $data);
            $processed++;
        }

        // Deleted Gmail messages are kept in CRM as an auditable soft-deleted
        // record. A hard delete would destroy local notes and history.
        foreach ($event->getMessagesDeleted() ?? [] as $deleted) {
            $messageId = trim((string) ($deleted->getMessage()?->getId() ?? ''));

            if ($messageId === '' || ! $this->markMessageDeleted($account, $messageId)) {
                continue;
            }

            $processed++;
        }

        foreach ($event->getLabelsAdded() ?? [] as $labelAdded) {
            $messageId = trim((string) ($labelAdded->getMessage()?->getId() ?? ''));

            if ($messageId === '') {
                continue;
            }

            if ($this->applyLabelChange(
                $account,
                $messageId,
                $labelAdded->getLabelIds() ?? [],
                [],
            )) {
                $processed++;
            }
        }

        foreach ($event->getLabelsRemoved() ?? [] as $labelRemoved) {
            $messageId = trim((string) ($labelRemoved->getMessage()?->getId() ?? ''));

            if ($messageId === '') {
                continue;
            }

            if ($this->applyLabelChange(
                $account,
                $messageId,
                [],
                $labelRemoved->getLabelIds() ?? [],
            )) {
                $processed++;
            }
        }

        return $processed;
    }

    /**
     * Fetch a message referenced by a history event.
     *
     * A 404 here means the message disappeared while the history page was
     * being processed. That is a benign race, not a reason to fail the page.
     */
    private function fetchMessageForChange(string $messageId): ?Message
    {
        try {
            return $this->gmail->getMessage($messageId);
        } catch (GmailApiException $e) {
            if ($e->httpStatus === 404) {
                return null;
            }

            throw $e;
        }
    }

    private function isInboxRecord(CommunicationMessage $message): bool
    {
        $labels = $message->gmail_label_ids ?? [];

        return in_array('INBOX', $labels, true)
            || $message->direction === 'inbound';
    }

    private function markMessageDeleted(
        CommunicationAccount $account,
        string $providerMessageId,
    ): bool {
        return DB::transaction(function () use ($account, $providerMessageId): bool {
            $message = $this->locateMessage($account, $providerMessageId);

            if ($message === null || ! $this->isInboxRecord($message)) {
                return false;
            }

            if ($message->status !== self::STATUS_DELETED) {
                $message->forceFill([
                    'status' => self::STATUS_DELETED,
                ])->save();

                MessageEvent::create([
                    'message_id' => $message->id,
                    'event_type' => 'gmail.message_deleted',
                    'payload' => [
                        'provider_message_id' => $providerMessageId,
                    ],
                    'occurred_at' => now(),
                ]);
            }

            return true;
        });
    }

    /**
     * @param  array<int, string>  $added
     * @param  array<int, string>  $removed
     */
    private function applyLabelChange(
        CommunicationAccount $account,
        string $providerMessageId,
        array $added,
        array $removed,
    ): bool {
        return DB::transaction(function () use (
            $account,
            $providerMessageId,
            $added,
            $removed,
        ): bool {
            $message = $this->locateMessage($account, $providerMessageId);

            if ($message !== null && ! $this->isInboxRecord($message)) {
                return false;
            }

            if ($message === null) {
                // A message moved into INBOX may not exist locally yet.
                if (! in_array('INBOX', $added, true)) {
                    return false;
                }

                $message = $this->fetchMessageForChange($providerMessageId);

                if ($message === null) {
                    return false;
                }

                $data = $this->mapper->map($message);

                if (! in_array('INBOX', $data->labelIds, true)) {
                    return false;
                }

                $this->persistMessage($account, $data);

                return true;
            }

            $current = array_values(array_filter(
                $message->gmail_label_ids ?? [],
                fn ($label): bool => is_string($label) && $label !== '',
            ));
            $labels = array_values(array_diff(
                array_unique(array_merge($current, $added)),
                $removed,
            ));

            $currentSorted = $current;
            $labelsSorted = $labels;
            sort($currentSorted);
            sort($labelsSorted);

            if ($currentSorted === $labelsSorted) {
                return true;
            }

            $updates = [
                'gmail_label_ids' => $labels,
                'is_read' => ! in_array('UNREAD', $labels, true),
                'is_starred' => in_array('STARRED', $labels, true),
            ];

            // A message moved back into the inbox is no longer deleted.
            if ($message->status === self::STATUS_DELETED && in_array('INBOX', $added, true)) {
                $updates['status'] = $message->direction === 'outbound'
                    ? 'sent'
                    : 'received';
            }

            $message->forceFill($updates)->save();

            MessageEvent::create([
                'message_id' => $message->id,
                'event_type' => 'gmail.labels_changed',
                'payload' => [
                    'provider_message_id' => $providerMessageId,
                    'added' => array_values($added),
                    'removed' => array_values($removed),
                ],
                'occurred_at' => now(),
            ]);

            return true;
        });
    }

    private function locateMessage(
        CommunicationAccount $account,
        string $providerMessageId,
    ): ?CommunicationMessage {
        return CommunicationMessage::query()
            ->where('account_id', $account->id)
            ->where('provider', 'gmail')
            ->where('provider_message_id', $providerMessageId)
            ->first();
    }

    private function assertSyncableAccount(CommunicationAccount $account): void
    {
        if (! $account->exists || $account->provider !== 'gmail') {
            throw new RuntimeException('Akun yang diberikan bukan akun Gmail valid.');
        }

        if (! $account->isConnected()) {
            throw new GmailAuthenticationException(
                'Akun Gmail tidak terhubung.',
                false,
            );
        }

        $primaryAccount = $this->authenticator->connectedAccount();

        if ($primaryAccount === null || $primaryAccount->id !== $account->id) {
            throw new RuntimeException(
                'Sinkronisasi hanya diizinkan untuk akun Gmail utama.',
            );
        }
    }

    private function persistMessage(
        CommunicationAccount $account,
        GmailMessageData $data,
    ): void {
        DB::transaction(function () use ($account, $data): void {
            $now = now();

            $sentAt = $data->direction === 'outbound'
                ? $data->receivedAt
                : null;

            // Gmail internalDate is the chronological timestamp for both
            // inbound and outbound messages.
            $receivedAt = $data->receivedAt;

            CommunicationMessage::query()->insertOrIgnore([
                'id' => (string) Str::uuid(),
                'account_id' => $account->id,
                'channel' => 'email',
                'direction' => $data->direction,
                'subject' => $data->subject,
                'body' => $data->body,
                'provider' => 'gmail',
                'provider_message_id' => $data->providerMessageId,
                'thread_id' => $data->threadId,
                'status' => $data->direction === 'outbound' ? 'sent' : 'received',
                'sent_at' => $sentAt,
                'sender_email' => $data->senderEmail,
                'sender_name' => $data->senderName,
                'to_recipients' => json_encode($data->toRecipients),
                'cc_recipients' => json_encode($data->ccRecipients),
                'bcc_recipients' => json_encode($data->bccRecipients),
                'gmail_label_ids' => json_encode($data->labelIds),
                'received_at' => $receivedAt,
                'is_read' => $data->isRead,
                'is_starred' => $data->isStarred,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $stored = CommunicationMessage::query()
                ->where('account_id', $account->id)
                ->where('provider', 'gmail')
                ->where('provider_message_id', $data->providerMessageId)
                ->first();

            if ($stored === null) {
                throw new RuntimeException(
                    'Pesan Gmail gagal ditemukan setelah proses penyimpanan.',
                );
            }

            // Update metadata from Gmail without overwriting CRM message content/status.
            $stored->forceFill([
                'thread_id' => $data->threadId,
                'sender_email' => $data->senderEmail,
                'sender_name' => $data->senderName,
                'to_recipients' => $data->toRecipients,
                'cc_recipients' => $data->ccRecipients,
                'bcc_recipients' => $data->bccRecipients,
                'gmail_label_ids' => $data->labelIds,
                'received_at' => $receivedAt ?? $stored->received_at,
                'is_read' => $data->isRead,
                'is_starred' => $data->isStarred,
            ]);

            if (blank($stored->subject) && filled($data->subject)) {
                $stored->subject = $data->subject;
            }

            if (blank($stored->body) && filled($data->body)) {
                $stored->body = $data->body;
            }

            if ($stored->sent_at === null && $sentAt !== null) {
                $stored->sent_at = $sentAt;
            }

            if ($stored->status === self::STATUS_DELETED) {
                $stored->status = $data->direction === 'outbound'
                    ? 'sent'
                    : 'received';
            }

            $stored->save();
        });
    }
}
