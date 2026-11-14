<?php

namespace App\Domain\Communications\Providers;

use App\Domain\Communications\Contracts\EmailProvider;
use App\Domain\Communications\DTOs\CommunicationResult;
use App\Domain\Communications\DTOs\EmailMessageData;
use App\Domain\Communications\Models\CommunicationAccount;
use Google\Client as GoogleClient;
use Google\Service\Gmail;
use Google\Service\Gmail\Draft;

final class GmailProvider implements EmailProvider
{
    public function __construct(private readonly GoogleClient $client) {}

    public function createDraft(EmailMessageData $message): CommunicationResult
    {
        $service = $this->service();

        if ($service === null) {
            return CommunicationResult::fail('gmail_not_connected');
        }

        $raw = rtrim(strtr(base64_encode($this->mimeMessage($message)), '+/', '-_'), '=');

        $draft = new Draft;
        $draft->setMessage((new Gmail\Message)->setRaw($raw));

        $created = $service->users_drafts->create('me', $draft);

        return CommunicationResult::ok((string) $created->getId(), 'draft');
    }

    public function sendApprovedDraft(string $draftId): CommunicationResult
    {
        $service = $this->service();

        if ($service === null) {
            return CommunicationResult::fail('gmail_not_connected');
        }

        $sent = $service->users_drafts->send('me', $draftId);

        return CommunicationResult::ok((string) ($sent->getId() ?? $draftId), 'sent');
    }

    public function getProviderName(): string
    {
        return 'gmail';
    }

    private function service(): ?Gmail
    {
        $account = CommunicationAccount::query()
            ->where('provider', 'gmail')
            ->where('connection_status', 'connected')
            ->first();

        if ($account === null || $account->access_token === null) {
            return null;
        }

        $this->client->setAccessToken($account->access_token);

        return new Gmail($this->client);
    }

    private function mimeMessage(EmailMessageData $message): string
    {
        $headers = [
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
