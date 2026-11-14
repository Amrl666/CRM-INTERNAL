<?php

namespace App\Domain\Communications\Contracts;

interface WhatsAppProvider
{
    public function getConnectionStatus(): array;

    public function processWebhook(array $payload): void;

    public function getProviderName(): string;
}
