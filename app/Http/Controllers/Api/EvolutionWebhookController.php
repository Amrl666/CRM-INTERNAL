<?php

namespace App\Http\Controllers\Api;

use App\Domain\Communications\Contracts\WhatsAppProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class EvolutionWebhookController
{
    public function __invoke(Request $request, WhatsAppProvider $provider): JsonResponse
    {
        $secret = (string) config('communications.whatsapp.evolution.webhook_secret');

        // ponytail: fail closed until secret diisi; tidak ada jalur tanpa autentikasi.
        if ($secret === '') {
            return response()->json(['message' => 'Webhook secret belum dikonfigurasi.'], 503);
        }

        if (! hash_equals($secret, (string) $request->header('x-webhook-secret'))) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $provider->processWebhook($request->all());

        return response()->json(['status' => 'ok']);
    }
}
