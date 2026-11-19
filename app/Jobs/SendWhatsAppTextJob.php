<?php

namespace App\Jobs;

use App\Domain\Communications\Contracts\WhatsAppProvider;
use App\Domain\Communications\Models\CommunicationMessage;
use App\Domain\Communications\Models\Conversation;
use App\Support\PhoneNormalizer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;

class SendWhatsAppTextJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public readonly string $messageId)
    {
        $this->onQueue('default');
    }

    public function handle(WhatsAppProvider $provider): void
    {
        $message = CommunicationMessage::find($this->messageId);

        if ($message === null || $message->status !== 'pending') {
            return;
        }

        $transitioned = CommunicationMessage::query()
            ->where('id', $message->id)
            ->where('status', 'pending')
            ->update(['status' => 'sending']);

        if ($transitioned === 0) {
            return;
        }

        $message->refresh();

        $conversation = Conversation::find($message->conversation_id);

        if ($conversation === null) {
            $message->update(['status' => 'failed']);

            return;
        }

        $number = $conversation->phone_e164;

        if ($number === null) {
            $raw = explode('@', (string) $conversation->remote_jid)[0];
            $number = PhoneNormalizer::toE164($raw);
        }

        if ($number === null) {
            $message->update(['status' => 'failed']);

            return;
        }

        $digits = ltrim($number, '+');

        try {
            $result = $provider->sendText($digits, (string) $message->body);
        } catch (ConnectionException $e) {
            // Request may already have reached the provider; do not retry automatically.
            $message->update([
                'status' => 'unknown',
                'provider_payload' => ['error' => $e->getMessage()],
            ]);

            return;
        } catch (\Throwable $e) {
            $message->update([
                'status' => 'failed',
                'provider_payload' => ['error' => $e->getMessage()],
            ]);

            return;
        }

        $message->update([
            'status' => 'sent',
            'provider_message_id' => $result['provider_message_id'],
            'sent_at' => now(),
            'provider_payload' => $result['payload'],
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        $message = CommunicationMessage::find($this->messageId);

        if ($message !== null && in_array($message->status, ['pending', 'sending'], true)) {
            $message->update([
                'status' => 'unknown',
                'provider_payload' => ['error' => $exception->getMessage()],
            ]);
        }
    }
}
