<?php

namespace App\Domain\Communications\Providers;

use App\Domain\Communications\Contracts\ProposalEmailSender;
use App\Domain\Communications\DTOs\CommunicationResult;
use App\Domain\Communications\DTOs\ProposalEmailData;
use App\Domain\Communications\Models\CommunicationAccount;
use App\Domain\Communications\Services\GmailAccountAuthenticator;
use App\Domain\Communications\Services\GmailApiErrorClassifier;
use App\Domain\Communications\Services\GmailAuthenticationException;
use Google\Service\Exception as GoogleServiceException;
use Google\Service\Gmail\Message;
use Throwable;

final class GmailProposalSender implements ProposalEmailSender
{
    public function __construct(
        private readonly GmailAccountAuthenticator $authenticator,
        private readonly GmailApiErrorClassifier $classifier = new GmailApiErrorClassifier,
    ) {}

    public function sendProposal(ProposalEmailData $message): CommunicationResult
    {
        $account = $this->authenticator->connectedAccount();

        if ($account === null) {
            return CommunicationResult::fail('gmail_not_connected');
        }

        try {
            $service = $this->authenticator->gmailServiceFor($account);
        } catch (GmailAuthenticationException $e) {
            report($e);

            return CommunicationResult::fail('gmail_token_invalid');
        } catch (Throwable $e) {
            report($e);

            return CommunicationResult::fail('gmail_authentication_failed');
        }

        try {
            $mime = $this->mimeMessage($message, $account);
        } catch (Throwable $e) {
            report($e);

            return CommunicationResult::fail('gmail_message_preparation_failed');
        }

        $raw = rtrim(
            strtr(base64_encode($mime), '+/', '-_'),
            '=',
        );

        try {
            $sent = $service->users_messages->send(
                'me',
                new Message(['raw' => $raw]),
            );

            if (blank($sent->getId())) {
                return CommunicationResult::unknown('gmail_send_result_missing_id');
            }

            return CommunicationResult::ok((string) $sent->getId(), 'accepted');
        } catch (GoogleServiceException $e) {
            $code = (int) $e->getCode();

            if ($code === 401 || $this->classifier->requiresReconnect($e)) {
                $account->update(['connection_status' => 'disconnected']);

                return CommunicationResult::fail('gmail_authorization_failed');
            }

            if ($this->classifier->isRateLimit($e)) {
                return CommunicationResult::fail('gmail_rate_limited');
            }

            if ($this->classifier->isRetryable($e)) {
                return CommunicationResult::unknown('gmail_transient_error');
            }

            return CommunicationResult::fail('gmail_send_failed');
        } catch (Throwable $e) {
            report($e);

            // Gmail may have accepted the request before the transport failed.
            // Do not retry blindly.
            return CommunicationResult::unknown('gmail_outcome_unknown');
        }
    }

    public function getProviderName(): string
    {
        return 'gmail';
    }

    private function mimeMessage(
        ProposalEmailData $message,
        CommunicationAccount $account,
    ): string {
        $boundary = 'crm_'.bin2hex(random_bytes(16));
        $altBoundary = 'crm_alt_'.bin2hex(random_bytes(16));
        $subject = mb_encode_mimeheader($message->subject, 'UTF-8', 'B', "\r\n");
        $filename = str_replace(['"', "\r", "\n"], '', $message->attachmentFilename);
        $pdf = file_get_contents($message->attachmentPath);

        if ($pdf === false) {
            throw new \RuntimeException('Lampiran PDF tidak dapat dibaca.');
        }

        $headers = [
            'To: '.$message->to,
            'Subject: '.$subject,
            'MIME-Version: 1.0',
            'Content-Type: multipart/mixed; boundary="'.$boundary.'"',
        ];

        $fromName = config('proposals.gmail.from_name') ?: $account->display_name;

        if ($fromName) {
            $headers[] = 'From: '.mb_encode_mimeheader(
                (string) $fromName,
                'UTF-8',
                'B',
                "\r\n",
            ).' <'.$account->account_email.'>';
        }

        $body = implode("\r\n", $headers)."\r\n\r\n";
        $body .= '--'.$boundary."\r\n";
        $body .= 'Content-Type: multipart/alternative; boundary="'.$altBoundary.'"'."\r\n\r\n";
        $body .= '--'.$altBoundary."\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($message->textBody), 76, "\r\n");
        $body .= '--'.$altBoundary."\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($message->htmlBody), 76, "\r\n");
        $body .= '--'.$altBoundary."--\r\n";
        $body .= '--'.$boundary."\r\n";
        $body .= 'Content-Type: '.$message->attachmentMime.'; name="'.$filename.'"'."\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n";
        $body .= 'Content-Disposition: attachment; filename="'.$filename.'"'."\r\n\r\n";
        $body .= chunk_split(base64_encode($pdf), 76, "\r\n");
        $body .= '--'.$boundary."--\r\n";

        return $body;
    }
}
