<?php

namespace Database\Factories;

use App\Domain\Companies\Models\Company;
use App\Domain\Contacts\Models\Contact;
use App\Domain\Leads\Enums\LeadStatus;
use App\Domain\Leads\Models\Lead;
use Illuminate\Database\Eloquent\Factories\Factory;

class LeadFactory extends Factory
{
    protected $model = Lead::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'contact_id' => Contact::factory(),
            'source_id' => null,
            'owner_id' => null,
            'status' => LeadStatus::New,
            'priority' => 'medium',
        ];
    }

    public function ownedBy(int $userId): static
    {
        return $this->state(fn () => ['owner_id' => $userId]);
    }
}
