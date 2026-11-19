<?php

namespace App\Domain\Communications\Services;

use App\Domain\Communications\Contracts\EmailProvider;
use App\Domain\Communications\Contracts\WhatsAppProvider;
use App\Domain\Communications\DTOs\CommunicationResult;
use App\Domain\Communications\DTOs\EmailMessageData;

final class CommunicationManager
{
    public function __construct(
        protected readonly EmailProvider $emailProvider,
        protected readonly WhatsAppProvider $whatsappProvider,
        protected readonly GmailAccountAuthenticator $gmailAuthenticator,
    ) {}

    /**
     * Create a draft email for a contact.
     */
    public function createEmailDraft(EmailMessageData $message, int $createdBy): CommunicationResult
    {
        $account = $this->gmailAuthenticator->connectedAccount();

        if ($account === null && $this->emailProvider->getProviderName() !== 'fake') {
            return CommunicationResult::fail('no_connected_email_account');
        }

        return $this->emailProvider->createDraft($message);
    }

    /**
     * Send an approved email draft.
     */
    public function sendApprovedEmail(string $draftId): CommunicationResult
    {
        return $this->emailProvider->sendApprovedDraft($draftId);
    }

    /**
     * Process an incoming WhatsApp webhook payload.
     */
    public function processWhatsAppWebhook(array $payload, int $receivedBy): void
    {
        $this->whatsappProvider->processWebhook($payload);
    }

    /**
     * Get the WhatsApp connection status.
     */
    public function getWhatsAppStatus(): array
    {
        return $this->whatsappProvider->getConnectionStatus();
    }
}
