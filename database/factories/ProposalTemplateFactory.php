<?php

namespace Database\Factories;

use App\Domain\Campaigns\Models\ProposalTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProposalTemplateFactory extends Factory
{
    protected $model = ProposalTemplate::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->words(3, true),
            'slug' => $this->faker->unique()->slug(),
            'version' => '1.0',
            'view_path' => 'proposals.templates.website-school.v1',
            'required_variables' => ['company_name', 'contact_name', 'proposal_number', 'proposal_date'],
            'assets' => [],
            'is_active' => true,
        ];
    }
}
