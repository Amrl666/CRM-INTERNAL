<?php

namespace App\Domain\Communications\Contracts;

interface WhatsAppProvider
{
    public function getConnectionStatus(): array;

    public function processWebhook(array $payload): void;

    public function getProviderName(): string;

    /** @return array{provider_message_id: string, status: ?string, payload: array<string, mixed>} */
    public function sendText(string $number, string $text): array;
}
