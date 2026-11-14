<?php

namespace Database\Factories;

use App\Domain\Campaigns\Models\MessageTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

class MessageTemplateFactory extends Factory
{
    protected $model = MessageTemplate::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->words(3, true),
            'channel' => $this->faker->randomElement(['EMAIL', 'WHATSAPP']),
            'subject' => $this->faker->sentence(5),
            'body_html' => '<p>Halo {{contact_name}},</p><p>{{company_name}}</p>',
            'body_text' => "Halo {{contact_name}},\n{{company_name}}",
            'whatsapp_body' => null,
            'version' => 1,
            'is_active' => true,
        ];
    }
}
