<?php

namespace App\Domain\Campaigns\Actions;

use App\Domain\Audit\Actions\RecordAuditAction;
use App\Domain\Campaigns\Enums\CampaignStatus;
use App\Domain\Campaigns\Enums\ProposalChannel;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Models\MessageTemplate;
use App\Domain\Campaigns\Models\ProposalTemplate;
use App\Domain\Campaigns\Support\AudienceFilter;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class CreateProposalCampaignAction
{
    public function __construct(
        private readonly RecordAuditAction $audit,
    ) {}

    /**
     * @param  array<int|string, mixed>  $audience  lead id list (legacy/manual) or filter array
     * @param  array<string, mixed>  $settings
     */
    public function handle(
        string $name,
        ProposalTemplate $proposalTemplate,
        MessageTemplate $messageTemplate,
        ProposalChannel $channel,
        array $audience,
        int $createdBy,
        array $settings = [],
    ): Campaign {
        $filter = array_is_list($audience)
            ? AudienceFilter::fromLeadIds($audience)
            : AudienceFilter::fromArray($audience);

        if ($filter->isManual() && $filter->manualLeadIds() === []) {
            throw new InvalidArgumentException('Pilih minimal satu penerima.');
        }

        if (! $filter->isManual() && ! $filter->hasFilters()) {
            throw new InvalidArgumentException('Tentukan minimal satu kriteria audience.');
        }

        if ($channel === ProposalChannel::Both && ! $this->whatsappEnabled()) {
            throw new InvalidArgumentException('Campaign proposal WhatsApp belum diaktifkan administrator.');
        }

        if ($channel === ProposalChannel::WhatsApp && ! $this->whatsappEnabled()) {
            throw new InvalidArgumentException('Campaign proposal WhatsApp belum diaktifkan administrator.');
        }

        $this->validateTemplates($channel, $messageTemplate, $settings);

        $defaults = config('proposals.defaults');

        $campaign = DB::transaction(function () use (
            $name, $proposalTemplate, $messageTemplate, $channel, $filter, $createdBy, $settings, $defaults
        ): Campaign {
            return Campaign::create([
                'name' => $name,
                'type' => 'proposal',
                'channel' => $channel,
                'template_id' => null,
                'proposal_template_id' => $proposalTemplate->id,
                'message_template_id' => $messageTemplate->id,
                'whatsapp_message_template_id' => $settings['whatsapp_message_template_id'] ?? null,
                'proposal_template_version' => $proposalTemplate->version,
                'subject_template' => $messageTemplate->subject,
                'body_html_template' => $messageTemplate->body_html,
                'body_text_template' => $messageTemplate->body_text,
                'whatsapp_template' => $this->whatsappContent($settings),
                'audience_filters' => $filter->toArray(),
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
        });

        $this->audit->handle(user: $createdBy, action: 'proposal_campaign.created', subject: $campaign, after: [
            'name' => $campaign->name,
            'channel' => $campaign->channel?->value,
            'audience_mode' => $filter->mode(),
            'recipients' => 0,
            'revision' => $campaign->revision,
        ]);

        return $campaign;
    }

    private function whatsappEnabled(): bool
    {
        return (bool) config('proposals.whatsapp.proposal_campaign_enabled');
    }

    /** @param array<string, mixed> $settings */
    private function validateTemplates(ProposalChannel $channel, MessageTemplate $messageTemplate, array $settings): void
    {
        if ($channel->includesEmail() && $messageTemplate->channel !== 'EMAIL') {
            throw new InvalidArgumentException('Template pesan Email tidak sesuai channel yang dipilih.');
        }

        if ($channel === ProposalChannel::WhatsApp && $messageTemplate->channel !== 'WHATSAPP') {
            throw new InvalidArgumentException('Template pesan WhatsApp tidak sesuai channel yang dipilih.');
        }

        if ($channel === ProposalChannel::Both) {
            $whatsappTemplate = $settings['whatsapp_message_template'] ?? null;
            if (! $whatsappTemplate instanceof MessageTemplate || $whatsappTemplate->channel !== 'WHATSAPP') {
                throw new InvalidArgumentException('Campaign BOTH memerlukan template pesan WhatsApp.');
            }
        }
    }

    /** @param array<string, mixed> $settings */
    private function whatsappContent(array $settings): ?string
    {
        $template = $settings['whatsapp_message_template'] ?? null;

        if ($template instanceof MessageTemplate) {
            return $template->whatsapp_body;
        }

        return $settings['whatsapp_template'] ?? null;
    }
}
