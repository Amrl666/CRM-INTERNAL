<?php

namespace App\Domain\Communications\Actions;

use App\Domain\Communications\Models\CommunicationMessage;
use App\Domain\Communications\Models\Conversation;
use App\Jobs\SendWhatsAppTextJob;
use App\Models\User;
use App\Support\PhoneNormalizer;
use Illuminate\Validation\ValidationException;

final class SendWhatsAppTextAction
{
    public function handle(User $user, Conversation $conversation, string $text): CommunicationMessage
    {
        if (! $user->can('inbox.manage')) {
            abort(403);
        }

        if (! $user->can('inbox.view')) {
            abort(403);
        }

        $text = trim($text);

        if ($text === '') {
            throw ValidationException::withMessages([
                'message' => 'Pesan tidak boleh kosong.',
            ]);
        }

        if (mb_strlen($text) > 4096) {
            throw ValidationException::withMessages([
                'message' => 'Pesan terlalu panjang.',
            ]);
        }

        $number = $conversation->phone_e164;

        if ($number === null) {
            $raw = explode('@', (string) $conversation->remote_jid)[0];
            $number = PhoneNormalizer::toE164($raw);
        }

        if ($number === null) {
            throw ValidationException::withMessages([
                'message' => 'Nomor tujuan tidak valid.',
            ]);
        }

        $message = CommunicationMessage::create([
            'conversation_id' => $conversation->id,
            'contact_id' => $conversation->contact_id,
            'channel' => 'WHATSAPP',
            'direction' => 'outbound',
            'body' => $text,
            'provider' => 'evolution',
            'status' => 'pending',
        ]);

        SendWhatsAppTextJob::dispatch($message->id);

        return $message;
    }
}
