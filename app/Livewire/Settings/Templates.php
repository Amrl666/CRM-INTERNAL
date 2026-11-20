<?php

namespace App\Livewire\Settings;

use App\Domain\Campaigns\Models\EmailTemplate;
use App\Domain\Campaigns\Models\MessageTemplate;
use App\Domain\Campaigns\Services\MessagePersonalization;
use App\Domain\Communications\DTOs\EmailMessageData;
use App\Domain\Communications\Services\EmailBannerRenderer;
use App\Domain\Communications\Services\EmailMessageRenderer;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.sidebar', ['heading' => 'Template'])]
class Templates extends Component
{
    public string $tab = 'email';

    public ?string $editingMessageId = null;

    public string $messageName = '';

    public string $messageChannel = 'EMAIL';

    public string $messageSubject = '';

    public string $messageBodyHtml = '';

    public string $messageBodyText = '';

    public string $messageWhatsappBody = '';

    public ?string $editingEmailId = null;

    public string $emailName = '';

    public string $emailSubject = '';

    public string $emailBody = '';

    public ?string $previewSubject = null;

    public string $previewBody = '';

    public string $previewBannerUrl = '';

    public ?string $previewWhatsappBody = null;

    /** @var string[] */
    public array $previewUnknownVariables = [];

    public function mount(): void
    {
        $this->authorize('settings.manage');
    }

    private function personalization(): MessagePersonalization
    {
        return app(MessagePersonalization::class);
    }

    private function sampleVariables(): array
    {
        return [
            'company_name' => 'PT Contoh Korporasi',
            'contact_name' => 'Budi Santoso',
            'email' => 'budi.santoso@example.com',
            'phone' => '+6280000000000',
            'industry' => 'Manufaktur',
            'source' => 'Referral',
            'proposal_number' => 'PROP-0001',
            'proposal_date' => '16/11/2026',
            'sender_name' => 'Operator Prospecta',
            'sender_email' => 'operator@enigmative.web.id',
            'sender_phone' => '',
            'sender_website' => '',
            'sender_company' => 'Enigma',
        ];
    }

    public function previewMessage(): void
    {
        $this->previewSubject = $this->personalization()->subject($this->messageSubject, $this->sampleVariables());
        $this->previewBody = $this->personalization()->html($this->messageBodyHtml, $this->sampleVariables());
        $this->previewWhatsappBody = $this->personalization()->text($this->messageWhatsappBody, $this->sampleVariables());
        $this->previewUnknownVariables = $this->personalization()->unknownVariables($this->messageBodyHtml);
    }

    public function resetPreview(): void
    {
        $this->previewSubject = null;
        $this->previewBody = '';
        $this->previewBannerUrl = '';
        $this->previewWhatsappBody = null;
        $this->previewUnknownVariables = [];
    }

    public function editMessage(string $id): void
    {
        $template = MessageTemplate::findOrFail($id);
        $this->editingMessageId = $template->id;
        $this->messageName = $template->name;
        $this->messageChannel = $template->channel;
        $this->messageSubject = (string) $template->subject;
        $this->messageBodyHtml = (string) $template->body_html;
        $this->messageBodyText = (string) $template->body_text;
        $this->messageWhatsappBody = (string) $template->whatsapp_body;
        $this->resetPreview();
    }

    public function saveMessage(): void
    {
        $this->authorize('settings.manage');
        $this->validate([
            'messageName' => ['required', 'string', 'max:120'],
            'messageChannel' => ['required', 'in:EMAIL,WHATSAPP'],
            'messageSubject' => ['nullable', 'string', 'max:500'],
        ]);

        if ($this->editingMessageId === null) {
            MessageTemplate::create([
                'name' => trim($this->messageName),
                'channel' => $this->messageChannel,
                'subject' => trim($this->messageSubject) ?: null,
                'body_html' => $this->messageBodyHtml,
                'body_text' => $this->messageBodyText,
                'whatsapp_body' => $this->messageWhatsappBody,
                'is_active' => true,
                'created_by' => auth()->id(),
            ]);
        } else {
            MessageTemplate::whereKey($this->editingMessageId)->update([
                'name' => trim($this->messageName),
                'channel' => $this->messageChannel,
                'subject' => trim($this->messageSubject) ?: null,
                'body_html' => $this->messageBodyHtml,
                'body_text' => $this->messageBodyText,
                'whatsapp_body' => $this->messageWhatsappBody,
            ]);
        }

        $this->resetMessageForm();
        $this->dispatch('notify', message: 'Template pesan berhasil disimpan.', type: 'success');
    }

    public function resetMessageForm(): void
    {
        $this->editingMessageId = null;
        $this->messageName = '';
        $this->messageChannel = 'EMAIL';
        $this->messageSubject = '';
        $this->messageBodyHtml = '';
        $this->messageBodyText = '';
        $this->messageWhatsappBody = '';
    }

    public function deleteMessage(string $id): void
    {
        $this->authorize('settings.manage');
        MessageTemplate::findOrFail($id)->delete();
        $this->resetMessageForm();
        $this->dispatch('notify', message: 'Template pesan berhasil dihapus.', type: 'success');
    }

    public function editEmail(string $id): void
    {
        $template = EmailTemplate::findOrFail($id);
        $this->editingEmailId = $template->id;
        $this->emailName = $template->name;
        $this->emailSubject = $template->subject;
        $this->emailBody = $template->body;
        $this->resetPreview();
    }

    public function saveEmail(): void
    {
        $this->authorize('settings.manage');
        $this->validate([
            'emailName' => ['required', 'string', 'max:100'],
            'emailSubject' => ['required', 'string', 'max:500'],
            'emailBody' => ['required', 'string'],
        ]);

        if ($this->editingEmailId === null) {
            EmailTemplate::create([
                'name' => trim($this->emailName),
                'subject' => trim($this->emailSubject),
                'body' => $this->emailBody,
                'created_by' => auth()->id(),
            ]);
        } else {
            EmailTemplate::whereKey($this->editingEmailId)->update([
                'name' => trim($this->emailName),
                'subject' => trim($this->emailSubject),
                'body' => $this->emailBody,
            ]);
        }

        $this->resetEmailForm();
        $this->dispatch('notify', message: 'Template email berhasil disimpan.', type: 'success');
    }

    public function resetEmailForm(): void
    {
        $this->editingEmailId = null;
        $this->emailName = '';
        $this->emailSubject = '';
        $this->emailBody = '';
    }

    public function deleteEmail(string $id): void
    {
        $this->authorize('settings.manage');
        EmailTemplate::findOrFail($id)->delete();
        $this->resetEmailForm();
        $this->dispatch('notify', message: 'Template email berhasil dihapus.', type: 'success');
    }

    public function previewEmail(): void
    {
        $variables = $this->sampleVariables();
        $this->previewSubject = $this->personalization()->subject($this->emailSubject, $variables);
        $this->previewUnknownVariables = $this->personalization()->unknownVariables($this->emailBody);

        $this->previewBannerUrl = app(EmailBannerRenderer::class)->url(
            auth()->user()?->name,
            auth()->user()?->position,
        );

        $this->previewBody = app(EmailMessageRenderer::class)->preview(
            new EmailMessageData(
                to: '',
                subject: $this->previewSubject,
                body: $this->personalization()->text($this->emailBody, $variables),
            ),
            '',
        );
    }

    public function render()
    {
        return view('livewire.settings.templates', [
            'messageTemplates' => MessageTemplate::orderBy('name')->get(),
            'emailTemplates' => EmailTemplate::orderBy('name')->get(),
        ]);
    }
}
