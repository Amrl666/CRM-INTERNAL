<?php

namespace App\Domain\Communications\Actions;

use App\Domain\Audit\Actions\RecordAuditAction;
use App\Domain\Communications\Models\CommunicationMessage;
use App\Domain\Consent\Enums\Channel;
use App\Domain\Contacts\Models\Contact;

final class RecordManualWhatsAppInteractionAction
{
    public function __construct(private readonly RecordAuditAction $audit) {}

    public function handle(Contact $contact, string $summary, int $byUserId): CommunicationMessage
    {
        $message = CommunicationMessage::create([
            'contact_id' => $contact->id,
            'channel' => Channel::WhatsApp->value,
            'direction' => 'outbound',
            'body' => $summary,
            'provider' => 'manual',
            'status' => 'sent',
            'sent_by' => $byUserId,
            'sent_at' => now(),
        ]);

        $this->audit->handle(user: $byUserId, action: 'whatsapp.interaction_recorded', subject: $message, after: [
            'summary' => mb_substr($summary, 0, 200),
        ]);

        return $message;
    }
}
