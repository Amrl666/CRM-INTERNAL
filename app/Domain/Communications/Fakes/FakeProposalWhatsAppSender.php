<?php

namespace App\Domain\Communications\Fakes;

use App\Domain\Communications\Contracts\ProposalWhatsAppSender;
use App\Domain\Communications\DTOs\CommunicationResult;
use App\Domain\Communications\DTOs\ProposalWhatsAppData;

final class FakeProposalWhatsAppSender implements ProposalWhatsAppSender
{
    /** @var ProposalWhatsAppData[] */
    public array $sent = [];

    public function isEnabled(): bool
    {
        return (bool) config('proposals.whatsapp.proposal_campaign_enabled');
    }

    public function sendProposalDocument(ProposalWhatsAppData $message): CommunicationResult
    {
        if (! $this->isEnabled()) {
            return CommunicationResult::fail('whatsapp_proposal_campaign_disabled');
        }

        $this->sent[] = $message;

        return CommunicationResult::ok('fake_proposal_wa_'.bin2hex(random_bytes(8)), 'accepted');
    }

    public function getProviderName(): string
    {
        return 'fake';
    }
}
