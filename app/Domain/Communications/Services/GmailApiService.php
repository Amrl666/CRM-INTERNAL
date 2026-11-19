<?php

namespace App\Domain\Communications\Services;

use Closure;
use Google\Service\Exception as GoogleServiceException;
use Google\Service\Gmail;
use Google\Service\Gmail\ListHistoryResponse;
use Google\Service\Gmail\ListMessagesResponse;
use Google\Service\Gmail\Message;
use Google\Service\Gmail\Profile;
use Google\Service\Gmail\Thread;

final class GmailApiService
{
    /**
     * @var array<int, string>
     */
    private const HISTORY_TYPES = [
        'messageAdded',
        'messageDeleted',
        'labelAdded',
        'labelRemoved',
    ];

    public function __construct(
        private readonly GmailAccountAuthenticator $authenticator,
        private readonly GmailApiErrorClassifier $classifier = new GmailApiErrorClassifier,
    ) {}

    /**
     * List Gmail messages with pagination.
     *
     * @param  array<int, string>  $labelIds
     */
    public function listMessages(
        array $labelIds = ['INBOX'],
        ?string $pageToken = null,
        int $maxResults = 50,
        ?string $query = null,
    ): ListMessagesResponse {
        if ($maxResults < 1 || $maxResults > 100) {
            throw new \InvalidArgumentException(
                'Gmail maxResults harus antara 1 dan 100.'
            );
        }

        $params = [
            'maxResults' => $maxResults,
        ];

        if ($labelIds !== []) {
            $params['labelIds'] = array_values(array_unique($labelIds));
        }

        if (filled($pageToken)) {
            $params['pageToken'] = $pageToken;
        }

        if (filled($query)) {
            $params['q'] = $query;
        }

        return $this->call(
            fn (Gmail $gmail) => $gmail->users_messages->listUsersMessages(
                'me',
                $params,
            ),
        );
    }

    /**
     * Retrieve a complete Gmail message.
     */
    public function getMessage(string $messageId): Message
    {
        $this->validateResourceId($messageId, 'message');

        return $this->call(
            fn (Gmail $gmail) => $gmail->users_messages->get(
                'me',
                $messageId,
                ['format' => 'full'],
            ),
        );
    }

    /**
     * Retrieve all messages belonging to a Gmail thread.
     */
    public function getThread(string $threadId): Thread
    {
        $this->validateResourceId($threadId, 'thread');

        return $this->call(
            fn (Gmail $gmail) => $gmail->users_threads->get(
                'me',
                $threadId,
                ['format' => 'full'],
            ),
        );
    }

    /**
     * Return the current mailbox history id, used as a full-sync checkpoint.
     */
    public function getProfile(): Profile
    {
        return $this->call(
            fn (Gmail $gmail) => $gmail->users->getProfile('me'),
        );
    }

    /**
     * List mailbox changes since a history id.
     *
     * A 404 means the startHistoryId is no longer available and the caller
     * must perform a safe full resync without deleting CRM data.
     */
    public function listHistory(
        string $startHistoryId,
        ?string $pageToken = null,
        int $maxResults = 100,
    ): ListHistoryResponse {
        if (trim($startHistoryId) === '') {
            throw new \InvalidArgumentException(
                'Gmail startHistoryId tidak boleh kosong.'
            );
        }

        $params = [
            'startHistoryId' => $startHistoryId,
            'historyTypes' => self::HISTORY_TYPES,
            'maxResults' => max(1, min(500, $maxResults)),
        ];

        if (filled($pageToken)) {
            $params['pageToken'] = $pageToken;
        }

        try {
            return $this->call(
                fn (Gmail $gmail) => $gmail->users_history->listUsersHistory(
                    'me',
                    $params,
                ),
            );
        } catch (GmailApiException $e) {
            if ($e->httpStatus === 404) {
                throw new GmailHistoryExpiredException(previous: $e);
            }

            throw $e;
        }
    }

    /**
     * Run a Gmail request and refresh the token once after HTTP 401.
     *
     * A second 401 is treated as an authorization failure that requires
     * reconnection. Refresh failures are classified by the authenticator so
     * invalid_grant stops retries while temporary network errors can retry.
     */
    private function call(Closure $operation): mixed
    {
        $account = $this->authenticator->connectedAccount();

        if ($account === null) {
            throw new GmailAuthenticationException(
                'Akun Gmail utama belum terhubung.',
                true,
                GmailAuthenticationException::REASON_DISCONNECTED,
            );
        }

        try {
            return $operation($this->authenticator->gmailServiceFor($account));
        } catch (GoogleServiceException $e) {
            if ((int) $e->getCode() !== 401) {
                $this->handleGoogleApiException($e);
            }
        }

        $this->authenticator->refresh($account);

        try {
            return $operation($this->authenticator->gmailServiceFor($account));
        } catch (GoogleServiceException $e) {
            if ((int) $e->getCode() === 401) {
                $account->update(['connection_status' => 'disconnected']);

                throw new GmailAuthenticationException(
                    'Google menolak kredensial Gmail setelah pembaruan token. Hubungkan ulang akun Gmail utama.',
                    true,
                    GmailAuthenticationException::REASON_UNAUTHORIZED,
                );
            }

            $this->handleGoogleApiException($e);
        }
    }

    private function handleGoogleApiException(
        GoogleServiceException $exception,
    ): never {
        if ($this->classifier->requiresReconnect($exception)) {
            $account = $this->authenticator->connectedAccount();

            $account?->update(['connection_status' => 'disconnected']);

            throw new GmailAuthenticationException(
                'Izin baca Gmail (gmail.readonly) tidak lagi tersedia. Hubungkan ulang akun Gmail utama.',
                true,
                GmailAuthenticationException::REASON_INSUFFICIENT_PERMISSIONS,
            );
        }

        if ($this->classifier->isRetryable($exception)) {
            throw $exception;
        }

        $status = (int) $exception->getCode();

        throw new GmailApiException(
            'Gmail menolak permintaan sinkronisasi (HTTP '.$status.').',
            retryable: false,
            httpStatus: $status,
        );
    }

    private function validateResourceId(
        string $id,
        string $type,
    ): void {
        if (trim($id) === '') {
            throw new \InvalidArgumentException(
                "Gmail {$type} ID tidak boleh kosong."
            );
        }
    }
}
