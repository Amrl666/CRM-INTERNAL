<?php

namespace App\Domain\Communications\Contracts;

use App\Domain\Communications\DTOs\CommunicationResult;
use App\Domain\Communications\DTOs\ProposalEmailData;

interface ProposalEmailSender
{
    public function sendProposal(ProposalEmailData $message): CommunicationResult;

    public function getProviderName(): string;
}
