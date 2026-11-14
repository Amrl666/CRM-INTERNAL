<?php

use App\Domain\Communications\Contracts\WhatsAppProvider;
use App\Domain\Communications\Models\ProviderEvent;
use App\Domain\Communications\Providers\EvolutionClient;
use App\Jobs\ProcessProviderEventJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    app()->instance(WhatsAppProvider::class, new EvolutionClient);
    Queue::fake();
});

function webhookPayload(string $id = 'evt-1'): array
{
    return [
        'id' => $id,
        'event' => 'messages.upsert',
        'data' => ['key' => ['remoteJid' => '6281234567890@s.whatsapp.net']],
    ];
}

it('refuses webhooks while the secret is not configured', function () {
    config(['communications.whatsapp.evolution.webhook_secret' => '']);

    $this->postJson('/api/v1/webhooks/evolution', webhookPayload())
        ->assertStatus(503);
});

it('rejects webhooks with a wrong secret', function () {
    config(['communications.whatsapp.evolution.webhook_secret' => 'rahasia']);

    $this->postJson('/api/v1/webhooks/evolution', webhookPayload(), ['x-webhook-secret' => 'salah'])
        ->assertStatus(401);
});

it('stores provider events idempotently and queues processing once', function () {
    config(['communications.whatsapp.evolution.webhook_secret' => 'rahasia']);

    $headers = ['x-webhook-secret' => 'rahasia'];

    $this->postJson('/api/v1/webhooks/evolution', webhookPayload('evt-42'), $headers)->assertOk();
    $this->postJson('/api/v1/webhooks/evolution', webhookPayload('evt-42'), $headers)->assertOk();

    expect(ProviderEvent::count())->toBe(1);

    Queue::assertPushedOn('webhooks', ProcessProviderEventJob::class);
    Queue::assertPushed(ProcessProviderEventJob::class, 1);
});
