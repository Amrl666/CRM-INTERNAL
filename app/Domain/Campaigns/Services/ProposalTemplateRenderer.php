<?php

namespace App\Domain\Campaigns\Services;

use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Models\CampaignRecipient;
use App\Domain\Campaigns\Models\ProposalSenderSetting;
use App\Domain\Companies\Models\Company;
use App\Domain\Contacts\Models\Contact;
use Illuminate\Support\Carbon;

final class ProposalTemplateRenderer
{
    private ?string $fontCss = null;

    /** @return array<string, string> */
    public function variables(Campaign $campaign, CampaignRecipient $recipient): array
    {
        $recipient->loadMissing(['contact.company', 'lead.source']);

        /** @var Contact $contact */
        $contact = $recipient->contact;
        /** @var Company $company */
        $company = $contact->company;
        $sender = ProposalSenderSetting::active();

        $date = Carbon::now($campaign->timezone);

        return [
            'company_name' => (string) $company->name,
            'contact_name' => (string) $contact->full_name,
            'email' => (string) ($contact->normalized_email ?? ''),
            'phone' => (string) ($contact->phone_e164 ?? ''),
            'industry' => (string) ($company->industry ?? ''),
            'source' => (string) ($recipient->lead?->source?->getAttribute('name') ?? ''),
            'proposal_number' => (string) $recipient->proposal_number,
            'proposal_date' => $date->format('d/m/Y'),
            'year' => $date->format('Y'),
            'sender_name' => (string) $sender->sender_name,
            'sender_email' => (string) $sender->sender_email,
            'sender_phone' => (string) ($sender->sender_phone ?? ''),
            'sender_website' => (string) ($sender->sender_website ?? ''),
            'sender_company' => (string) $sender->company_name,
            'footer_text' => (string) ($sender->footer_text ?? 'Dokumen ini disiapkan oleh '.$sender->company_name.'.'),
        ];
    }

    /** @param array<string, string> $variables */
    public function render(Campaign $campaign, array $variables): string
    {
        $view = $campaign->proposalTemplate?->view_path;

        if ($view === null || ! view()->exists($view)) {
            throw new \RuntimeException('Template proposal tidak ditemukan atau tidak aktif.');
        }

        foreach (['company_name', 'contact_name', 'proposal_number', 'proposal_date', 'sender_company', 'sender_name', 'sender_email'] as $required) {
            if (($variables[$required] ?? '') === '') {
                throw new \RuntimeException("Variabel proposal wajib kosong: {$required}.");
            }
        }

        $explicitCamel = [
            'companyName' => $variables['company_name'],
            'contactName' => $variables['contact_name'],
            'proposalNumber' => $variables['proposal_number'],
            'proposalDate' => $variables['proposal_date'],
            'senderCompany' => $variables['sender_company'],
            'senderName' => $variables['sender_name'],
            'senderEmail' => $variables['sender_email'],
            'senderPhone' => $variables['sender_phone'],
            'senderWebsite' => $variables['sender_website'],
            'footerText' => $variables['footer_text'],
            'industry' => $variables['industry'],
            'year' => $variables['year'],
            'fontCss' => $this->fontCss(),
        ];

        return view($view, array_merge($variables, $explicitCamel))->render();
    }

    private function fontCss(): string
    {
        if ($this->fontCss !== null) {
            return $this->fontCss;
        }

        $css = '';
        foreach ([400, 500, 600, 700] as $weight) {
            $path = resource_path('fonts/PlusJakartaSans-'.$weight.'.ttf');
            if (! is_file($path)) {
                continue;
            }

            $css .= "@font-face{font-family:'Plus Jakarta Sans';font-style:normal;font-weight:{$weight};font-display:swap;src:url(data:font/ttf;base64,".base64_encode((string) file_get_contents($path)).") format('truetype');}";
        }

        return $this->fontCss = $css;
    }
}
