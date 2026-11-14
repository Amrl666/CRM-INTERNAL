<?php

namespace App\Domain\Communications\Contracts;

use App\Domain\Communications\DTOs\CommunicationResult;
use App\Domain\Communications\DTOs\EmailMessageData;

interface EmailProvider
{
    public function createDraft(EmailMessageData $message): CommunicationResult;

    public function sendApprovedDraft(string $draftId): CommunicationResult;

    public function getProviderName(): string;
}
