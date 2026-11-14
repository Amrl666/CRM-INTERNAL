<?php

namespace App\Domain\Campaigns\Services;

use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Models\CampaignRecipient;
use App\Domain\Campaigns\Models\ProposalSenderSetting;

final class ProposalApprovalSnapshot
{
    /** @return array<string, mixed> */
    public function build(Campaign $campaign): array
    {
        $campaign->loadMissing(['proposalTemplate', 'messageTemplate', 'recipients.contact.company']);
        $sender = ProposalSenderSetting::active();

        return [
            'campaign_id' => $campaign->id,
            'name' => $campaign->name,
            'channel' => $campaign->channel?->value ?? null,
            'revision' => $campaign->revision,
            'approved_at' => now()->toIso8601String(),
            'proposal_template' => [
                'id' => $campaign->proposal_template_id,
                'slug' => $campaign->proposalTemplate?->slug,
                'version' => $campaign->proposal_template_version,
                'view_path' => $campaign->proposalTemplate?->view_path,
            ],
            'message' => [
                'template_id' => $campaign->message_template_id,
                'subject' => $campaign->subject_template,
                'body_html' => $campaign->body_html_template,
                'body_text' => $campaign->body_text_template,
                'whatsapp_body' => $campaign->whatsapp_template,
            ],
            'schedule' => [
                'timezone' => $campaign->timezone,
                'business_days' => $campaign->business_days,
                'business_start' => $campaign->business_start,
                'business_end' => $campaign->business_end,
                'excluded_dates' => $campaign->excluded_dates,
                'rate_limit_per_hour' => $campaign->rate_limit_per_hour,
                'min_spacing_seconds' => $campaign->min_spacing_seconds,
                'scheduled_start_at' => $campaign->scheduled_start_at?->toIso8601String(),
            ],
            'sender' => $sender->only([
                'company_name', 'sender_name', 'sender_email', 'sender_phone',
                'sender_website', 'footer_text',
            ]),
            'recipients' => $campaign->recipients->map(function ($recipient) {
                if (! $recipient instanceof CampaignRecipient) {
                    return [];
                }

                return [
                    'id' => $recipient->id,
                    'lead_id' => $recipient->lead_id,
                    'contact_id' => $recipient->contact_id,
                    'company_name' => $recipient->contact?->company?->name,
                    'contact_name' => $recipient->contact?->full_name,
                    'email' => $recipient->contact?->normalized_email,
                    'phone' => $recipient->contact?->phone_e164,
                    'proposal_number' => $recipient->proposal_number,
                    'eligibility_status' => $recipient->eligibility_status?->value,
                    'eligibility_reason' => $recipient->eligibility_reason,
                    'pdf_status' => $recipient->pdf_status?->value,
                    'delivery_status' => $recipient->delivery_status?->value,
                ];
            })->filter()->values()->all(),
        ];
    }
}
