<?php

namespace App\Domain\Leads\Actions;

use App\Domain\Audit\Actions\RecordAuditAction;
use App\Domain\Companies\Models\Company;
use App\Domain\Consent\Actions\AutoGrantLeadConsentAction;
use App\Domain\Contacts\Models\Contact;
use App\Domain\Leads\Enums\LeadStatus;
use App\Domain\Leads\Models\Activity;
use App\Domain\Leads\Models\Lead;
use App\Domain\Leads\Models\LeadSource;
use App\Support\PhoneNormalizer;
use Illuminate\Support\Facades\DB;

final class CreateLeadAction
{
    public function __construct(
        private readonly RecordAuditAction $audit,
        private readonly AutoGrantLeadConsentAction $autoGrantConsent,
    ) {}

    /**
     * @param  array{
     *     company_name: string,
     *     domain?: string|null,
     *     contact_name: string,
     *     email?: string|null,
     *     phone?: string|null,
     *     industry?: string|null,
     *     source?: string|null,
     *     owner_id?: int|null,
     *     priority?: string,
     * }  $data
     * @param  bool  $grantConsent  Enable for trusted lead-entry flows; addresses are still validated per channel.
     * @param  string  $consentSource  Audit label stored on auto-granted consent records.
     */
    public function handle(array $data, ?int $createdBy = null, bool $grantConsent = false, string $consentSource = 'lead form'): Lead
    {
        return DB::transaction(function () use ($data, $createdBy, $grantConsent, $consentSource): Lead {
            $company = Company::firstOrCreate(
                [
                    'normalized_name' => Company::normalizeName($data['company_name']),
                    'domain' => ($data['domain'] ?? null) ?: null,
                ],
                [
                    'name' => trim($data['company_name']),
                    'industry' => $data['industry'] ?? null,
                ]
            );

            $contact = Contact::create([
                'company_id' => $company->id,
                'full_name' => trim($data['contact_name']),
                'email' => trim((string) ($data['email'] ?? '')) ?: null,
                'normalized_email' => Contact::normalizeEmail($data['email'] ?? null),
                'phone_e164' => PhoneNormalizer::toE164($data['phone'] ?? null),
                'is_primary' => true,
            ]);

            $sourceId = null;

            if (! empty($data['source'])) {
                $sourceId = LeadSource::firstOrCreate(['name' => trim($data['source'])])->id;
            }

            $lead = Lead::create([
                'company_id' => $company->id,
                'contact_id' => $contact->id,
                'source_id' => $sourceId,
                'owner_id' => $data['owner_id'] ?? $createdBy,
                'status' => LeadStatus::New,
                'priority' => $data['priority'] ?? 'medium',
            ]);

            Activity::create([
                'lead_id' => $lead->id,
                'user_id' => $createdBy,
                'type' => 'lead.created',
                'description' => 'Lead dibuat.',
                'occurred_at' => now(),
            ]);

            $this->audit->handle(user: $createdBy, action: 'lead.created', subject: $lead, after: [
                'company' => $company->name,
                'email' => $contact->normalized_email,
            ]);

            if ($grantConsent) {
                $this->autoGrantConsent->handle($contact, $createdBy, $consentSource);
            }

            return $lead;
        });
    }
}
