<?php

namespace Database\Factories;

use App\Domain\Companies\Models\Company;
use App\Domain\Contacts\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContactFactory extends Factory
{
    protected $model = Contact::class;

    public function definition(): array
    {
        $email = $this->faker->unique()->safeEmail();

        return [
            'company_id' => Company::factory(),
            'full_name' => $this->faker->name(),
            'job_title' => $this->faker->jobTitle(),
            'email' => $email,
            'normalized_email' => Contact::normalizeEmail($email),
            'phone_e164' => null,
            'is_primary' => true,
        ];
    }
}
