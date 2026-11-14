<?php

namespace App\Domain\Communications\Providers;

use App\Domain\Communications\Contracts\ProposalEmailSender;
use App\Domain\Communications\DTOs\CommunicationResult;
use App\Domain\Communications\DTOs\ProposalEmailData;
use App\Domain\Communications\Models\CommunicationAccount;
use Google\Client as GoogleClient;
use Google\Service\Exception as GoogleServiceException;
use Google\Service\Gmail;
use Google\Service\Gmail\Message;

final class GmailProposalSender implements ProposalEmailSender
{
    public function __construct(private readonly GoogleClient $client) {}

    public function sendProposal(ProposalEmailData $message): CommunicationResult
    {
        $account = CommunicationAccount::query()
            ->where('provider', 'gmail')
            ->where('connection_status', 'connected')
            ->latest('updated_at')
            ->first();

        if ($account === null) {
            return CommunicationResult::fail('gmail_not_connected');
        }

        try {
            $this->authenticate($account);
        } catch (\Throwable $e) {
            $account->update(['connection_status' => 'disconnected']);

            return CommunicationResult::fail('gmail_token_invalid');
        }

        $raw = rtrim(strtr(base64_encode($this->mimeMessage($message, $account)), '+/', '-_'), '=');

        try {
            $sent = (new Gmail($this->client))->users_messages->send('me', new Message(['raw' => $raw]));

            return CommunicationResult::ok((string) $sent->getId(), 'accepted');
        } catch (GoogleServiceException $e) {
            $code = (int) $e->getCode();

            if ($code === 401 || $code === 403) {
                $account->update(['connection_status' => 'disconnected']);

                return CommunicationResult::fail('gmail_authorization_failed');
            }

            if ($code === 429) {
                return CommunicationResult::fail('gmail_rate_limited', 'failed');
            }

            if ($code >= 500 || $code === 0) {
                return CommunicationResult::unknown('gmail_transient_error');
            }

            return CommunicationResult::fail('gmail_send_failed');
        } catch (\Throwable $e) {
            // A transport timeout may mean Gmail accepted the message. Do not retry blindly.
            return CommunicationResult::unknown('gmail_outcome_unknown');
        }
    }

    public function getProviderName(): string
    {
        return 'gmail';
    }

    private function authenticate(CommunicationAccount $account): void
    {
        $config = config('communications.email.gmail');
        $this->client->setClientId((string) $config['client_id']);
        $this->client->setClientSecret((string) $config['client_secret']);
        $this->client->setAccessToken($account->access_token);

        $expired = $account->token_expires_at !== null && now()->greaterThanOrEqualTo($account->token_expires_at);

        if ($expired || $account->access_token === null) {
            if ($account->refresh_token === null) {
                throw new \RuntimeException('Refresh token tidak tersedia.');
            }

            $token = $this->client->fetchAccessTokenWithRefreshToken($account->refresh_token);

            if (isset($token['error']) || ! isset($token['access_token'])) {
                throw new \RuntimeException('Refresh token Gmail gagal.');
            }

            $account->update([
                'access_token' => $token['access_token'],
                'token_expires_at' => isset($token['expires_in']) ? now()->addSeconds((int) $token['expires_in']) : null,
                'connection_status' => 'connected',
            ]);

            $this->client->setAccessToken($token);
        }
    }

    private function mimeMessage(ProposalEmailData $message, CommunicationAccount $account): string
    {
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
            $headers[] = 'From: '.mb_encode_mimeheader((string) $fromName, 'UTF-8', 'B', "\r\n").' <'.$account->account_email.'>';
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
