<?php

namespace App\Domain\Communications\Actions;

use App\Domain\Audit\Actions\RecordAuditAction;
use App\Domain\Communications\Models\CommunicationMessage;
use App\Domain\Communications\Services\CommunicationManager;
use App\Domain\Consent\Enums\Channel;
use App\Domain\Consent\Services\CommunicationEligibility;

final class SendApprovedEmailAction
{
    public function __construct(
        private readonly CommunicationManager $manager,
        private readonly CommunicationEligibility $eligibility,
        private readonly RecordAuditAction $audit,
    ) {}

    public function handle(CommunicationMessage $message, int $byUserId): CommunicationMessage
    {
        $contact = $message->contact;

        if ($contact === null) {
            throw new \InvalidArgumentException('Tidak ada kontak terkait untuk pesan ini.');
        }

        $reasons = $this->eligibility->check($contact, Channel::Email);

        if ($reasons !== []) {
            $message->update(['status' => 'cancelled']);

            $this->audit->handle(user: $byUserId, action: 'communication.send_cancelled', subject: $message, after: [
                'reasons' => $reasons,
            ]);

            return $message;
        }

        $result = $this->manager->sendApprovedEmail($message->provider_message_id);

        $message->update([
            'status' => $result->success ? 'sent' : $result->status ?? 'failed',
            'provider_message_id' => $result->providerMessageId ?? $message->provider_message_id,
            'sent_at' => $result->success ? now() : null,
            'sent_by' => $byUserId,
        ]);

        $this->audit->handle(user: $byUserId, action: 'communication.sent', subject: $message, after: [
            'success' => $result->success,
            'channel' => $message->channel,
            'to' => $contact->normalized_email,
        ]);

        return $message;
    }
}
