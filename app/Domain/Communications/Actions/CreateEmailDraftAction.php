<?php

namespace App\Domain\Communications\Actions;

use App\Domain\Audit\Actions\RecordAuditAction;
use App\Domain\Communications\Contracts\EmailProvider;
use App\Domain\Communications\DTOs\EmailMessageData;
use App\Domain\Communications\Models\CommunicationMessage;
use App\Domain\Communications\Services\GmailAccountAuthenticator;
use App\Domain\Consent\Enums\Channel;
use App\Domain\Contacts\Models\Contact;
use App\Models\User;

final class CreateEmailDraftAction
{
    public function __construct(
        private readonly EmailProvider $emailProvider,
        private readonly RecordAuditAction $audit,
        private readonly GmailAccountAuthenticator $gmailAuthenticator,
    ) {}

    public function handle(
        Contact $contact,
        string $subject,
        string $body,
        int $createdBy,
        ?string $campaignId = null,
    ): CommunicationMessage {
        $account = $this->gmailAuthenticator->connectedAccount();
        $sender = User::find($createdBy);

        $result = $this->emailProvider->createDraft(new EmailMessageData(
            to: (string) $contact->normalized_email,
            subject: $subject,
            body: $body,
            senderName: $sender?->name,
            senderPosition: $sender?->position,
        ));

        $message = CommunicationMessage::create([
            'contact_id' => $contact->id,
            'campaign_id' => $campaignId,
            'account_id' => $account?->id,
            'channel' => Channel::Email->value,
            'direction' => 'outbound',
            'subject' => $subject,
            'body' => $body,
            'provider' => $this->emailProvider->getProviderName(),
            'provider_message_id' => $result->providerMessageId,
            'status' => $result->success ? 'draft' : 'failed',
            'sent_by' => $createdBy,
        ]);

        $this->audit->handle(user: $createdBy, action: 'communication.draft_created', subject: $message, after: [
            'channel' => $message->channel,
            'to' => $contact->normalized_email,
            'success' => $result->success,
        ]);

        return $message;
    }
}
