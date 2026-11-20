<?php

namespace App\Livewire\Proposals;

use App\Domain\Campaigns\Actions\CreateProposalCampaignAction;
use App\Domain\Campaigns\Enums\ProposalChannel;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Models\MessageTemplate;
use App\Domain\Campaigns\Models\ProposalTemplate;
use App\Domain\Campaigns\Services\AudiencePreview;
use App\Domain\Campaigns\Support\AudienceFilter;
use App\Domain\Companies\Models\Company;
use App\Domain\Leads\Enums\LeadStatus;
use App\Domain\Leads\Models\ImportBatch;
use App\Domain\Leads\Models\Lead;
use App\Domain\Leads\Models\LeadSource;
use App\Domain\Leads\Models\Tag;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.sidebar', ['heading' => 'Outreach'])]
class Form extends Component
{
    public string $name = '';

    public string $proposal_template_id = '';

    public string $message_template_id = '';

    public string $whatsapp_message_template_id = '';

    public string $channel = 'EMAIL';

    public string $audienceMode = 'filter';

    /** @var string[] */
    public array $leadStatuses = [];

    /** @var string[] */
    public array $sourceIds = [];

    /** @var string[] */
    public array $importBatchIds = [];

    /** @var string[] */
    public array $industries = [];

    /** @var string[] */
    public array $cities = [];

    /** @var string[] */
    public array $countryCodes = [];

    /** @var string[] */
    public array $tagIds = [];

    public string $tagMatch = 'any';

    public string $emailAvailability = 'any';

    public string $whatsappAvailability = 'any';

    public string $priorOutreach = 'any';

    /** @var string[] */
    public array $manualLeadIds = [];

    /** @var array<string, mixed>|null */
    public ?array $audiencePreview = null;

    public string $timezone = 'Asia/Jakarta';

    /** @var int[] */
    public array $businessDays = [1, 2, 3, 4, 5];

    public string $businessStart = '09:00';

    public string $businessEnd = '17:00';

    public string $excludedDates = '';

    public int $rateLimitPerHour = 10;

    public int $minSpacingSeconds = 120;

    public string $scheduledStartAt = '';

    public function mount(): void
    {
        $this->authorize('create', Campaign::class);
        $this->timezone = (string) config('proposals.defaults.timezone', 'Asia/Jakarta');

        $presets = (string) request()->query('leads', '');
        $presetIds = collect(explode(',', $presets))
            ->map(fn ($id) => trim((string) $id))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($presetIds !== []) {
            $this->audienceMode = 'manual';
            $this->manualLeadIds = Lead::query()
                ->visibleTo(auth()->user())
                ->active()
                ->whereNotNull('contact_id')
                ->whereIn('id', $presetIds)
                ->pluck('id')
                ->all();
        }
    }

    public function updatedChannel(): void
    {
        $this->message_template_id = '';
        $this->whatsapp_message_template_id = '';
    }

    public function filterArray(): array
    {
        return [
            'version' => 1,
            'mode' => $this->audienceMode,
            'lead_statuses' => $this->leadStatuses,
            'source_ids' => $this->sourceIds,
            'import_batch_ids' => $this->importBatchIds,
            'industries' => $this->industries,
            'cities' => $this->cities,
            'country_codes' => $this->countryCodes,
            'tag_ids' => $this->tagIds,
            'tag_match' => $this->tagMatch,
            'email_availability' => $this->emailAvailability,
            'whatsapp_availability' => $this->whatsappAvailability,
            'prior_outreach' => $this->priorOutreach,
            'manual_lead_ids' => $this->manualLeadIds,
        ];
    }

    public function calculatePreview(AudiencePreview $preview): void
    {
        $campaign = new Campaign([
            'channel' => ProposalChannel::tryFrom($this->channel) ?? ProposalChannel::Email,
            'timezone' => $this->timezone,
            'rate_limit_per_hour' => $this->rateLimitPerHour,
            'min_spacing_seconds' => $this->minSpacingSeconds,
            'business_start' => $this->businessStart,
            'business_end' => $this->businessEnd,
        ]);

        $this->audiencePreview = $preview->estimate(
            $campaign,
            AudienceFilter::fromArray($this->filterArray()),
            fresh: true,
        );
    }

    public function save(CreateProposalCampaignAction $action)
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'proposal_template_id' => ['required', 'uuid', 'exists:proposal_templates,id'],
            'message_template_id' => ['required', 'uuid', 'exists:message_templates,id'],
            'channel' => ['required', Rule::enum(ProposalChannel::class)],
            'audienceMode' => ['required', Rule::in(['filter', 'manual'])],
            'timezone' => ['required', 'timezone'],
            'businessDays' => ['required', 'array', 'min:1'],
            'businessDays.*' => ['integer', 'between:1,7'],
            'businessStart' => ['required', 'date_format:H:i'],
            'businessEnd' => ['required', 'date_format:H:i', 'after:businessStart'],
            'excludedDates' => ['nullable', 'string'],
            'rateLimitPerHour' => ['required', 'integer', 'min:1', 'max:120'],
            'minSpacingSeconds' => ['required', 'integer', 'min:0', 'max:3600'],
            'scheduledStartAt' => ['nullable', 'date_format:Y-m-d\TH:i'],
        ]);

        $channel = ProposalChannel::from($data['channel']);

        if ($channel->includesWhatsApp() && ! config('proposals.whatsapp.proposal_campaign_enabled')) {
            $this->addError('channel', 'Campaign proposal WhatsApp belum diaktifkan administrator.');

            return;
        }

        $messageTemplate = MessageTemplate::findOrFail($data['message_template_id']);
        $whatsappTemplate = null;

        if ($channel === ProposalChannel::Both) {
            if ($this->whatsapp_message_template_id === '') {
                $this->addError('whatsapp_message_template_id', 'Pilih template WhatsApp untuk campaign BOTH.');

                return;
            }

            $whatsappTemplate = MessageTemplate::findOrFail($this->whatsapp_message_template_id);
            if ($whatsappTemplate->channel !== 'WHATSAPP') {
                $this->addError('whatsapp_message_template_id', 'Template WhatsApp tidak valid.');

                return;
            }

            if ($messageTemplate->channel !== 'EMAIL') {
                $this->addError('message_template_id', 'Template Email tidak valid.');

                return;
            }
        } elseif ($channel === ProposalChannel::WhatsApp && $messageTemplate->channel !== 'WHATSAPP') {
            $this->addError('message_template_id', 'Template pesan tidak sesuai channel yang dipilih.');

            return;
        } elseif ($channel === ProposalChannel::Email && $messageTemplate->channel !== 'EMAIL') {
            $this->addError('message_template_id', 'Template pesan tidak sesuai channel yang dipilih.');

            return;
        }

        $excluded = collect(preg_split('/\r\n|\r|\n/', $data['excludedDates'] ?? ''))
            ->map(fn ($date) => trim((string) $date))
            ->filter()
            ->values()
            ->all();

        foreach ($excluded as $date) {
            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                $this->addError('excludedDates', 'Format tanggal libur harus YYYY-MM-DD, satu per baris.');

                return;
            }

            $parsed = Carbon::createFromFormat('Y-m-d', $date);
            if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
                $this->addError('excludedDates', "Tanggal libur {$date} tidak valid.");

                return;
            }
        }

        $scheduledStartAt = filled($data['scheduledStartAt'] ?? null)
            ? Carbon::createFromFormat('Y-m-d\TH:i', $data['scheduledStartAt'], $data['timezone'])->utc()
            : null;

        try {
            $campaign = $action->handle(
                name: trim($data['name']),
                proposalTemplate: ProposalTemplate::findOrFail($data['proposal_template_id']),
                messageTemplate: $messageTemplate,
                channel: $channel,
                audience: $this->filterArray(),
                createdBy: (int) auth()->id(),
                settings: [
                    'whatsapp_message_template_id' => $whatsappTemplate?->id,
                    'whatsapp_message_template' => $whatsappTemplate,
                    'timezone' => $data['timezone'],
                    'business_days' => array_map('intval', $data['businessDays']),
                    'business_start' => $data['businessStart'],
                    'business_end' => $data['businessEnd'],
                    'excluded_dates' => $excluded,
                    'rate_limit_per_hour' => $data['rateLimitPerHour'],
                    'min_spacing_seconds' => $data['minSpacingSeconds'],
                    'scheduled_start_at' => $scheduledStartAt,
                ],
            );

            session()->flash('status', 'Proposal campaign dibuat sebagai draft. Audiens akan ditentukan saat campaign dimulai.');

            return redirect()->route('proposals.show', $campaign);
        } catch (\Throwable $e) {
            report($e);
            $this->addError('form', 'Proposal campaign tidak dapat dibuat. Silakan periksa kembali data dan coba lagi.');
        }
    }

    public function render()
    {
        $channel = ProposalChannel::tryFrom($this->channel) ?? ProposalChannel::Email;

        return view('livewire.proposals.form', [
            'proposalTemplates' => ProposalTemplate::active()->orderBy('name')->get(),
            'messageTemplates' => MessageTemplate::active()->where('channel', $channel->includesEmail() ? 'EMAIL' : 'WHATSAPP')->orderBy('name')->get(),
            'whatsappTemplates' => MessageTemplate::active()->where('channel', 'WHATSAPP')->orderBy('name')->get(),
            'leadStatuses' => LeadStatus::cases(),
            'sources' => LeadSource::orderBy('name')->get(),
            'importBatches' => ImportBatch::orderByDesc('created_at')->limit(100)->get(),
            'tags' => Tag::orderBy('name')->get(),
            'industries' => Company::query()
                ->whereNotNull('industry')->distinct()->orderBy('industry')->limit(200)->pluck('industry'),
            'cities' => Company::query()
                ->whereNotNull('city')->distinct()->orderBy('city')->limit(200)->pluck('city'),
            'channels' => ProposalChannel::cases(),
            'selectedCount' => count($this->manualLeadIds),
            'whatsAppEnabled' => (bool) config('proposals.whatsapp.proposal_campaign_enabled'),
        ]);
    }
}
