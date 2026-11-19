<?php

namespace Database\Factories;

use App\Domain\Communications\Models\Conversation;
use Illuminate\Database\Eloquent\Factories\Factory;

class ConversationFactory extends Factory
{
    protected $model = Conversation::class;

    public function definition(): array
    {
        return [
            'provider' => 'evolution',
            'instance' => 'default',
            'remote_jid' => $this->faker->unique()->numerify('628##########').'@s.whatsapp.net',
            'phone_e164' => null,
            'display_name' => $this->faker->name(),
            'status' => 'open',
            'last_message_at' => null,
            'last_message_preview' => null,
            'last_read_at' => null,
        ];
    }
}
