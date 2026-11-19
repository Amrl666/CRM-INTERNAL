<?php

namespace App\Domain\Communications\Providers;

use App\Domain\Communications\Contracts\WhatsAppProvider;
use App\Domain\Communications\Models\ProviderEvent;
use App\Jobs\ProcessProviderEventJob;
use Illuminate\Database\UniqueConstraintViolationException;
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

        try {
            $event = ProviderEvent::firstOrCreate(
                ['provider' => 'evolution', 'event_id' => $eventId],
                [
                    'event_type' => $payload['event'] ?? 'unknown',
                    'payload' => $payload,
                ]
            );
        } catch (UniqueConstraintViolationException $e) {
            $event = ProviderEvent::where('provider', 'evolution')
                ->where('event_id', $eventId)
                ->firstOrFail();
        }

        if ($event->wasRecentlyCreated) {
            ProcessProviderEventJob::dispatch($event->id);
        }
    }

    public function getProviderName(): string
    {
        return 'evolution';
    }

    public function sendText(string $number, string $text): array
    {
        $baseUrl = config('communications.whatsapp.evolution.url');
        $apiKey = config('communications.whatsapp.evolution.api_key');
        $instance = config('communications.whatsapp.evolution.instance');

        if (empty($baseUrl) || empty($apiKey) || empty($instance)) {
            throw new \RuntimeException('Evolution WhatsApp provider is not configured.');
        }

        $response = Http::baseUrl(rtrim($baseUrl, '/'))
            ->withHeaders(['apikey' => $apiKey])
            ->timeout(10)
            ->post('/message/sendText/'.rawurlencode((string) $instance), [
                'number' => $number,
                'text' => $text,
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Evolution sendText failed with HTTP '.$response->status().'.');
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new \RuntimeException('Evolution sendText returned an invalid response.');
        }

        $providerMessageId = (string) (data_get($payload, 'key.id') ?? '');

        if ($providerMessageId === '') {
            throw new \RuntimeException('Evolution sendText response did not include a message id.');
        }

        return [
            'provider_message_id' => $providerMessageId,
            'status' => data_get($payload, 'status'),
            'payload' => $payload,
        ];
    }
}
