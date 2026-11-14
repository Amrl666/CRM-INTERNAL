<?php

namespace App\Domain\Communications\Providers;

use App\Domain\Communications\Contracts\ProposalWhatsAppSender;
use App\Domain\Communications\DTOs\CommunicationResult;
use App\Domain\Communications\DTOs\ProposalWhatsAppData;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class EvolutionProposalSender implements ProposalWhatsAppSender
{
    public function isEnabled(): bool
    {
        return (bool) config('proposals.whatsapp.proposal_campaign_enabled');
    }

    public function sendProposalDocument(ProposalWhatsAppData $message): CommunicationResult
    {
        if (! $this->isEnabled()) {
            return CommunicationResult::fail('whatsapp_proposal_campaign_disabled');
        }

        $baseUrl = (string) config('communications.whatsapp.evolution.url');
        $apiKey = (string) config('communications.whatsapp.evolution.api_key');
        $instance = (string) config('communications.whatsapp.evolution.instance');
        $endpoint = (string) config('proposals.whatsapp.document_endpoint');

        if ($baseUrl === '' || $apiKey === '' || $instance === '' || $endpoint === '') {
            return CommunicationResult::fail('evolution_document_endpoint_not_configured');
        }

        $contents = file_get_contents($message->attachmentPath);
        if ($contents === false) {
            return CommunicationResult::fail('attachment_unreadable');
        }

        $endpoint = str_replace('{instance}', rawurlencode($instance), $endpoint);

        // Endpoint and payload shape must be verified against the pinned Evolution image.
        $payload = [
            'number' => $message->to,
            'mediatype' => 'document',
            'mimetype' => $message->attachmentMime,
            'fileName' => $message->attachmentFilename,
            'media' => base64_encode($contents),
            'caption' => $message->body,
        ];

        try {
            $response = Http::baseUrl(rtrim($baseUrl, '/'))
                ->withHeaders(['apikey' => $apiKey])
                ->timeout(60)
                ->acceptJson()
                ->post($endpoint, $payload);

            if ($response->successful()) {
                $id = $response->json('key.id')
                    ?? $response->json('messageId')
                    ?? $response->json('id');

                return CommunicationResult::ok((string) ($id ?? 'evolution_accepted'), 'accepted');
            }

            if ($response->status() === 429) {
                return CommunicationResult::fail('evolution_rate_limited', 'failed');
            }

            if ($response->serverError()) {
                return CommunicationResult::unknown('evolution_transient_error');
            }

            Log::warning('evolution:proposal document rejected', ['status' => $response->status()]);

            return CommunicationResult::fail('evolution_rejected');
        } catch (\Throwable $e) {
            Log::warning('evolution:proposal document outcome unknown', ['error' => $e->getMessage()]);

            return CommunicationResult::unknown('evolution_outcome_unknown');
        }
    }

    public function getProviderName(): string
    {
        return 'evolution';
    }
}
