<?php

namespace App\Domain\Communications\Contracts;

use App\Domain\Communications\DTOs\CommunicationResult;
use App\Domain\Communications\DTOs\ProposalWhatsAppData;

interface ProposalWhatsAppSender
{
    public function isEnabled(): bool;

    public function sendProposalDocument(ProposalWhatsAppData $message): CommunicationResult;

    public function getProviderName(): string;
}
