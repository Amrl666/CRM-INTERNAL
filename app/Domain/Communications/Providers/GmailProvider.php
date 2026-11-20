<?php

namespace App\Domain\Communications\Providers;

use App\Domain\Communications\Contracts\EmailProvider;
use App\Domain\Communications\DTOs\CommunicationResult;
use App\Domain\Communications\DTOs\EmailMessageData;
use App\Domain\Communications\Models\CommunicationAccount;
use App\Domain\Communications\Services\GmailAccountAuthenticator;
use App\Domain\Communications\Services\GmailApiErrorClassifier;
use App\Domain\Communications\Services\GmailAuthenticationException;
use Google\Service\Exception as GoogleServiceException;
use Google\Service\Gmail;
use Google\Service\Gmail\Draft;
use Throwable;

final class GmailProvider implements EmailProvider
{
    public function __construct(
        private readonly GmailAccountAuthenticator $authenticator,
        private readonly GmailApiErrorClassifier $classifier = new GmailApiErrorClassifier,
    ) {}

    public function createDraft(EmailMessageData $message): CommunicationResult
    {
        $account = $this->authenticator->connectedAccount();

        if ($account === null) {
            return CommunicationResult::fail('gmail_not_connected');
        }

        try {
            $service = $this->authenticator->gmailServiceFor($account);
        } catch (GmailAuthenticationException $e) {
            report($e);

            return CommunicationResult::fail('gmail_authentication_failed');
        } catch (Throwable $e) {
            report($e);

            return CommunicationResult::fail('gmail_authentication_failed');
        }

        try {
            $raw = rtrim(
                strtr(base64_encode($this->mimeMessage($message)), '+/', '-_'),
                '=',
            );

            $gmailMessage = new Gmail\Message;
            $gmailMessage->setRaw($raw);

            $draft = new Draft;
            $draft->setMessage($gmailMessage);
        } catch (Throwable $e) {
            report($e);

            return CommunicationResult::fail('gmail_draft_preparation_failed');
        }

        try {
            $created = $service->users_drafts->create('me', $draft);

            if (blank($created->getId())) {
                return CommunicationResult::unknown('gmail_draft_result_missing_id');
            }

            return CommunicationResult::ok((string) $created->getId(), 'draft');
        } catch (GoogleServiceException $e) {
            return $this->handleGoogleApiException(
                $e,
                'gmail_draft_outcome_unknown',
                'gmail_draft_create_failed',
                $account,
            );
        } catch (Throwable $e) {
            report($e);

            return CommunicationResult::unknown('gmail_draft_outcome_unknown');
        }
    }

    public function sendApprovedDraft(string $draftId): CommunicationResult
    {
        $account = $this->authenticator->connectedAccount();

        if ($account === null) {
            return CommunicationResult::fail('gmail_not_connected');
        }

        try {
            $service = $this->authenticator->gmailServiceFor($account);
        } catch (GmailAuthenticationException $e) {
            report($e);

            return CommunicationResult::fail('gmail_authentication_failed');
        } catch (Throwable $e) {
            report($e);

            return CommunicationResult::fail('gmail_authentication_failed');
        }

        try {
            $draft = new Draft;
            $draft->setId($draftId);

            $sent = $service->users_drafts->send('me', $draft);

            $messageId = $sent->getId();

            if (blank($messageId)) {
                return CommunicationResult::unknown('gmail_send_result_missing_id');
            }

            return CommunicationResult::ok((string) $messageId, 'sent');
        } catch (GoogleServiceException $e) {
            return $this->handleGoogleApiException(
                $e,
                'gmail_send_outcome_unknown',
                'gmail_send_failed',
                $account,
            );
        } catch (Throwable $e) {
            report($e);

            // Gmail may have accepted the request before the transport failed.
            // Never retry blindly.
            return CommunicationResult::unknown('gmail_send_outcome_unknown');
        }
    }

    public function getProviderName(): string
    {
        return 'gmail';
    }

    private function handleGoogleApiException(
        GoogleServiceException $e,
        string $unknownReason,
        string $failureReason,
        CommunicationAccount $account,
    ): CommunicationResult {
        $code = (int) $e->getCode();

        if ($code === 401 || $this->classifier->requiresReconnect($e)) {
            $account->update(['connection_status' => 'disconnected']);

            return CommunicationResult::fail('gmail_authorization_failed');
        }

        if ($this->classifier->isRateLimit($e)) {
            return CommunicationResult::fail('gmail_rate_limited');
        }

        if ($this->classifier->isRetryable($e)) {
            return CommunicationResult::unknown($unknownReason);
        }

        return CommunicationResult::fail($failureReason);
    }

    private function mimeMessage(EmailMessageData $message): string
    {
        $fromEmail = config(
            'communications.email.gmail.from_email',
            'hello@enigmative.web.id',
        );

        $headers = [
            'From: Enigma Collective Studio <'.$fromEmail.'>',
            'To: '.$message->to,
            'Subject: '.$message->subject,
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
        ];

        if ($message->replyTo !== null) {
            $headers[] = 'Reply-To: '.$message->replyTo;
        }

        return implode("\r\n", $headers)."\r\n\r\n".$message->body;
    }
}
