<?php

namespace App\Domain\Communications\Providers;

use App\Domain\Communications\Contracts\WhatsAppProvider;
use App\Domain\Communications\Models\ProviderEvent;
use App\Jobs\ProcessProviderEventJob;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class EvolutionClient implements WhatsAppProvider
{
    public function getConnectionStatus(): array
    {
        $baseUrl = config('communications.whatsapp.evolution.url');
        $apiKey = config('communications.whatsapp.evolution.api_key');
        $instance = config('communications.whatsapp.evolution.instance');

        if (empty($baseUrl) || empty($apiKey)) {
            return [
                'instance' => $instance,
                'status' => 'not_configured',
                'last_checked_at' => now()->toIso8601String(),
            ];
        }

        try {
            $response = Http::baseUrl(rtrim($baseUrl, '/'))
                ->withHeaders(['apikey' => $apiKey])
                ->timeout(10)
                ->get('/instance/connectionState/'.$instance);

            return [
                'instance' => $instance,
                'status' => $response->json('instance.state', $response->successful() ? 'unknown' : 'error'),
                'last_checked_at' => now()->toIso8601String(),
            ];
        } catch (\Throwable $e) {
            Log::warning('evolution:connection check failed', ['error' => $e->getMessage()]);

            return [
                'instance' => $instance,
                'status' => 'unreachable',
                'last_checked_at' => now()->toIso8601String(),
            ];
        }
    }

    public function processWebhook(array $payload): void
    {
        $eventId = (string) ($payload['id'] ?? $payload['event_id'] ?? hash('sha256', json_encode($payload)));

        $event = ProviderEvent::firstOrCreate(
            ['provider' => 'evolution', 'event_id' => $eventId],
            [
                'event_type' => $payload['event'] ?? 'unknown',
                'payload' => $payload,
            ]
        );

        if ($event->wasRecentlyCreated) {
            ProcessProviderEventJob::dispatch($event->id);
        }
    }

    public function getProviderName(): string
    {
        return 'evolution';
    }
}
