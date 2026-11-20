<?php

namespace App\Domain\Communications\Fakes;

use App\Domain\Communications\Contracts\ProposalEmailSender;
use App\Domain\Communications\DTOs\CommunicationResult;
use App\Domain\Communications\DTOs\ProposalEmailData;

final class FakeProposalEmailSender implements ProposalEmailSender
{
    /** @var ProposalEmailData[] */
    public array $sent = [];

    public function sendProposal(ProposalEmailData $message): CommunicationResult
    {
        $this->sent[] = $message;

        return CommunicationResult::ok('fake_proposal_email_'.bin2hex(random_bytes(8)), 'accepted');
    }

    public function getProviderName(): string
    {
        return 'fake';
    }

    public function getSenderEmail(): ?string
    {
        return null;
    }

    public function getSenderName(): ?string
    {
        return null;
    }
}
