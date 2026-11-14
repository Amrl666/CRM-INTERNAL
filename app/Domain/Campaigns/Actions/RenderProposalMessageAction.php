<?php

namespace App\Domain\Campaigns\Actions;

use App\Domain\Campaigns\Enums\ProposalChannel;
use App\Domain\Campaigns\Models\CampaignRecipient;
use App\Domain\Campaigns\Services\MessagePersonalization;
use App\Domain\Campaigns\Services\ProposalTemplateRenderer;

final class RenderProposalMessageAction
{
    public function __construct(
        private readonly ProposalTemplateRenderer $templates,
        private readonly MessagePersonalization $personalization,
    ) {}

    public function handle(CampaignRecipient $recipient): CampaignRecipient
    {
        $recipient->loadMissing(['campaign', 'contact.company', 'lead.source']);
        $campaign = $recipient->campaign;
        $variables = $this->templates->variables($campaign, $recipient);

        foreach (['subject_template', 'body_html_template', 'body_text_template'] as $field) {
            $unknown = $this->personalization->unknownVariables((string) $campaign->{$field});
            if ($unknown !== []) {
                throw new \RuntimeException('Variabel template tidak dikenal: '.implode(', ', $unknown));
            }
        }

        $subject = $this->personalization->subject($campaign->subject_template, $variables);
        $html = $this->personalization->html($campaign->body_html_template, $variables);
        $text = $this->personalization->text($campaign->body_text_template, $variables);
        $whatsapp = $this->personalization->text($campaign->whatsapp_template, $variables);

        if ($campaign->channel === ProposalChannel::Email && ($subject === '' || $html === '' || $text === '')) {
            throw new \RuntimeException('Template email proposal belum lengkap (subject, HTML, dan teks wajib diisi).');
        }

        if ($campaign->channel === ProposalChannel::WhatsApp && $whatsapp === '') {
            throw new \RuntimeException('Template WhatsApp proposal belum diisi.');
        }

        $recipient->update([
            'rendered_subject' => $subject,
            'rendered_body_html' => $html,
            'rendered_body_text' => $text,
            'rendered_whatsapp' => $whatsapp,
            'snapshot' => array_merge($recipient->snapshot ?? [], [
                'message_variables' => $variables,
                'rendered_at' => now()->toIso8601String(),
            ]),
        ]);

        return $recipient->refresh();
    }
}
