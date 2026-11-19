<?php

namespace App\Domain\Campaigns\Actions;

use App\Domain\Audit\Actions\RecordAuditAction;
use App\Domain\Campaigns\Enums\CampaignStatus;
use App\Domain\Campaigns\Enums\DeliveryStatus;
use App\Domain\Campaigns\Enums\EligibilityStatus;
use App\Domain\Campaigns\Enums\PdfStatus;
use App\Domain\Campaigns\Enums\ProposalChannel;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Models\CampaignRecipient;
use App\Domain\Campaigns\Models\MessageTemplate;
use App\Domain\Campaigns\Models\ProposalTemplate;
use App\Domain\Campaigns\Services\ProposalNumber;
use App\Domain\Leads\Models\Lead;
use App\Jobs\GenerateProposalDocumentJob;
use Illuminate\Support\Facades\DB;

final class CreateProposalCampaignAction
{
    public function __construct(
        private readonly RecordAuditAction $audit,
        private readonly RefreshProposalEligibilityAction $eligibility,
    ) {}

    /**
     * @param  string[]  $leadIds
     * @param  array<string, mixed>  $settings
     */
    public function handle(
        string $name,
        ProposalTemplate $proposalTemplate,
        MessageTemplate $messageTemplate,
        ProposalChannel $channel,
        array $leadIds,
        int $createdBy,
        array $settings = [],
    ): Campaign {
        if ($leadIds === []) {
            throw new \InvalidArgumentException('Pilih minimal satu penerima.');
        }

        if ($channel === ProposalChannel::WhatsApp && ! config('proposals.whatsapp.proposal_campaign_enabled')) {
            throw new \InvalidArgumentException('Campaign proposal WhatsApp belum diaktifkan administrator.');
        }

        $defaults = config('proposals.defaults');

        $campaign = DB::transaction(function () use (
            $name, $proposalTemplate, $messageTemplate, $channel, $leadIds, $createdBy, $settings, $defaults
        ) {
            $campaign = Campaign::create([
                'name' => $name,
                'type' => 'proposal',
                'channel' => $channel,
                'template_id' => null,
                'proposal_template_id' => $proposalTemplate->id,
                'message_template_id' => $messageTemplate->id,
                'proposal_template_version' => $proposalTemplate->version,
                'subject_template' => $messageTemplate->subject,
                'body_html_template' => $messageTemplate->body_html,
                'body_text_template' => $messageTemplate->body_text,
                'whatsapp_template' => $messageTemplate->whatsapp_body,
                'status' => CampaignStatus::Draft,
                'created_by' => $createdBy,
                'timezone' => $settings['timezone'] ?? $defaults['timezone'],
                'business_days' => $settings['business_days'] ?? $defaults['business_days'],
                'business_start' => $settings['business_start'] ?? $defaults['business_start'],
                'business_end' => $settings['business_end'] ?? $defaults['business_end'],
                'excluded_dates' => $settings['excluded_dates'] ?? [],
                'rate_limit_per_hour' => (int) ($settings['rate_limit_per_hour'] ?? $defaults['rate_limit_per_hour']),
                'min_spacing_seconds' => (int) ($settings['min_spacing_seconds'] ?? $defaults['min_spacing_seconds']),
                'scheduled_start_at' => $settings['scheduled_start_at'] ?? null,
                'revision' => 1,
            ]);

            $leads = Lead::query()
                ->whereIn('id', $leadIds)
                ->whereNull('archived_at')
                ->whereNotNull('contact_id')
                ->with('contact')
                ->get();

            foreach ($leads as $lead) {
                CampaignRecipient::firstOrCreate([
                    'campaign_id' => $campaign->id,
                    'contact_id' => $lead->contact_id,
                ], [
                    'lead_id' => $lead->id,
                    'status' => 'pending',
                    'eligibility_status' => EligibilityStatus::Pending,
                    'delivery_status' => DeliveryStatus::Pending,
                    'pdf_status' => PdfStatus::Pending,
                    'proposal_number' => ProposalNumber::next(),
                    'proposal_date' => now($campaign->timezone)->toDateString(),
                    'idempotency_key' => 'proposal:'.$campaign->id.':'.$lead->contact_id,
                ]);
            }

            if ($campaign->recipients()->count() === 0) {
                throw new \InvalidArgumentException('Tidak ada lead aktif dengan kontak yang dapat dijadikan penerima.');
            }

            $this->eligibility->handle($campaign, $createdBy);

            return $campaign;
        });

        $campaign->recipients()->pluck('id')->each(
            fn (string $id) => GenerateProposalDocumentJob::dispatch($id)->afterCommit()
        );

        $this->audit->handle(user: $createdBy, action: 'proposal_campaign.created', subject: $campaign, after: [
            'name' => $campaign->name,
            'channel' => $campaign->channel?->value,
            'recipients' => $campaign->recipients()->count(),
            'revision' => $campaign->revision,
        ]);

        return $campaign;
    }
}
