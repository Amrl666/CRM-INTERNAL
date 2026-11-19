<?php

namespace App\Domain\Communications\Fakes;

use App\Domain\Communications\Contracts\WhatsAppProvider;
use Illuminate\Support\Facades\Log;

final class FakeWhatsAppProvider implements WhatsAppProvider
{
    public array $processedWebhooks = [];

    public function getConnectionStatus(): array
    {
        return [
            'instance' => config('communications.whatsapp.evolution.instance'),
            'status' => 'connected',
            'last_checked_at' => now()->toIso8601String(),
        ];
    }

    public function processWebhook(array $payload): void
    {
        $this->processedWebhooks[] = $payload;
        Log::info('fake:whatsapp webhook processed', ['event_type' => $payload['event'] ?? 'unknown']);
    }

    public function getProviderName(): string
    {
        return 'fake';
    }

    public function sendText(string $number, string $text): array
    {
        return [
            'provider_message_id' => 'fake-'.uniqid(),
            'status' => 'PENDING',
            'payload' => [
                'key' => ['id' => 'fake-'.uniqid(), 'fromMe' => true],
                'status' => 'PENDING',
            ],
        ];
    }
}
