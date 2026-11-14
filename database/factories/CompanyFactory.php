<?php

namespace Database\Factories;

use App\Domain\Companies\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

class CompanyFactory extends Factory
{
    protected $model = Company::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->company();

        return [
            'name' => $name,
            'normalized_name' => Company::normalizeName($name),
            'domain' => $this->faker->unique()->domainName(),
            'industry' => $this->faker->randomElement(['SaaS', 'Manufaktur', 'Retail', 'Logistik']),
        ];
    }
}
