<?php

namespace App\Domain\Communications\Fakes;

use App\Domain\Communications\Contracts\EmailProvider;
use App\Domain\Communications\DTOs\CommunicationResult;
use App\Domain\Communications\DTOs\EmailMessageData;
use Illuminate\Support\Facades\Log;

final class FakeEmailProvider implements EmailProvider
{
    public array $createdDrafts = [];

    public array $sentDrafts = [];

    public function createDraft(EmailMessageData $message): CommunicationResult
    {
        $draftId = 'fake_draft_'.bin2hex(random_bytes(8));
        $this->createdDrafts[] = $message;
        Log::info('fake:email draft created', ['draft_id' => $draftId, 'to' => $message->to]);

        return CommunicationResult::ok($draftId, 'draft');
    }

    public function sendApprovedDraft(string $draftId): CommunicationResult
    {
        $this->sentDrafts[] = $draftId;
        Log::info('fake:email draft sent', ['draft_id' => $draftId]);

        return CommunicationResult::ok($draftId, 'sent');
    }

    public function getProviderName(): string
    {
        return 'fake';
    }
}
