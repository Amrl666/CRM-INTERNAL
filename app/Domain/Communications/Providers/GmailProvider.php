<?php

namespace App\Domain\Communications\Providers;

use App\Domain\Communications\Contracts\EmailProvider;
use App\Domain\Communications\DTOs\CommunicationResult;
use App\Domain\Communications\DTOs\EmailMessageData;
use App\Domain\Communications\Models\CommunicationAccount;
use App\Domain\Communications\Services\EmailBannerRenderer;
use App\Domain\Communications\Services\EmailMessageRenderer;
use App\Domain\Communications\Services\GmailAccountAuthenticator;
use App\Domain\Communications\Services\GmailApiErrorClassifier;
use App\Domain\Communications\Services\GmailAuthenticationException;
use Google\Service\Exception as GoogleServiceException;
use Google\Service\Gmail;
use Google\Service\Gmail\Draft;
use Illuminate\Support\Facades\Log;
use Throwable;

final class GmailProvider implements EmailProvider
{
    public function __construct(
        private readonly GmailAccountAuthenticator $authenticator,
        private readonly GmailApiErrorClassifier $classifier = new GmailApiErrorClassifier,
        private readonly EmailMessageRenderer $renderer = new EmailMessageRenderer,
        private readonly EmailBannerRenderer $bannerRenderer = new EmailBannerRenderer,
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
        $boundary = 'crm_'.bin2hex(random_bytes(16));
        $altBoundary = 'crm_alt_'.bin2hex(random_bytes(16));
        $cid = (string) config('communications.email_banner.cid', 'email-footer-banner');
        $banner = $this->bannerRenderer->jpeg($message->senderName, $message->senderPosition);

        if ($banner === '') {
            Log::warning('gmail: email footer banner tidak ditemukan');
        }

        $headers = [
            'To: '.$message->to,
            'Subject: '.$message->subject,
            'MIME-Version: 1.0',
            'Content-Type: multipart/related; type="multipart/alternative"; boundary="'.$boundary.'"',
        ];

        if ($message->replyTo !== null) {
            $headers[] = 'Reply-To: '.$message->replyTo;
        }

        $mime = implode("\r\n", $headers)."\r\n\r\n";
        $mime .= '--'.$boundary."\r\n";
        $mime .= 'Content-Type: multipart/alternative; boundary="'.$altBoundary.'"'."\r\n\r\n";
        $mime .= '--'.$altBoundary."\r\n";
        $mime .= "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
        $mime .= chunk_split(base64_encode($this->renderer->plainText($message)), 76, "\r\n");
        $mime .= '--'.$altBoundary."\r\n";
        $mime .= "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
        $mime .= chunk_split(base64_encode($this->renderer->html(
            $message,
            $banner === '' ? '' : 'cid:'.$cid,
        )), 76, "\r\n");
        $mime .= '--'.$altBoundary."--\r\n";

        if ($banner !== '') {
            $mime .= '--'.$boundary."\r\n";
            $mime .= 'Content-Type: image/jpeg; name="'.$cid.'.jpg"'."\r\n";
            $mime .= "Content-Transfer-Encoding: base64\r\n";
            $mime .= 'Content-ID: <'.$cid.'>'."\r\n";
            $mime .= 'Content-Disposition: inline; filename="'.$cid.'.jpg"'."\r\n\r\n";
            $mime .= chunk_split(base64_encode($banner), 76, "\r\n");
        }

        $mime .= '--'.$boundary."--\r\n";

        return $mime;
    }
}
