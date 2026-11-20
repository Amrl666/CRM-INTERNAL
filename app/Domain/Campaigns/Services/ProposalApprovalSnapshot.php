<?php

namespace App\Domain\Campaigns\Services;

use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Models\ProposalSenderSetting;

final class ProposalApprovalSnapshot
{
    /**
     * Snapshot of the approved campaign definition. The recipient snapshot is
     * created separately at start time in campaign_recipients.
     *
     * @param  array<string, mixed>|null  $estimate
     * @return array<string, mixed>
     */
    public function build(Campaign $campaign, ?array $estimate = null): array
    {
        $campaign->loadMissing(['proposalTemplate', 'messageTemplate', 'whatsappMessageTemplate']);
        $sender = ProposalSenderSetting::active();

        return [
            'snapshot_version' => 2,
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
                'whatsapp_template_id' => $campaign->whatsapp_message_template_id,
                'subject' => $campaign->subject_template,
                'body_html' => $campaign->body_html_template,
                'body_text' => $campaign->body_text_template,
                'whatsapp_body' => $campaign->whatsapp_template,
            ],
            'audience' => [
                'filters' => $campaign->audience_filters,
                'estimate' => $estimate,
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
            'recipients' => [],
        ];
    }
}
