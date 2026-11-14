<?php

namespace App\Domain\Campaigns\Actions;

use App\Domain\Audit\Actions\RecordAuditAction;
use App\Domain\Campaigns\Enums\CampaignStatus;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Models\CampaignRecipient;
use App\Domain\Campaigns\Models\EmailTemplate;
use App\Domain\Contacts\Models\Contact;
use App\Domain\Leads\Models\Lead;

final class CreateCampaignAction
{
    public function __construct(private readonly RecordAuditAction $audit) {}

    /** @param string[] $leadIds */
    public function handle(string $name, EmailTemplate $template, array $leadIds, ?int $createdBy = null): Campaign
    {
        $campaign = Campaign::create([
            'name' => $name,
            'template_id' => $template->id,
            'status' => CampaignStatus::Draft,
            'created_by' => $createdBy,
        ]);

        $leads = Lead::query()
            ->whereIn('id', $leadIds)
            ->whereNull('archived_at')
            ->whereNotNull('contact_id')
            ->with('contact')
            ->get();

        foreach ($leads as $lead) {
            /** @var Contact $contact */
            $contact = $lead->contact;

            CampaignRecipient::firstOrCreate([
                'campaign_id' => $campaign->id,
                'contact_id' => $contact->id,
            ], [
                'lead_id' => $lead->id,
                'status' => 'pending',
            ]);
        }

        $this->audit->handle(user: $createdBy, action: 'campaign.created', subject: $campaign, after: [
            'name' => $campaign->name,
            'recipients' => $campaign->recipients()->count(),
        ]);

        return $campaign;
    }
}
