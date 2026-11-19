<?php

namespace App\Livewire\Proposals;

use App\Domain\Campaigns\Actions\CreateProposalCampaignAction;
use App\Domain\Campaigns\Enums\ProposalChannel;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Models\MessageTemplate;
use App\Domain\Campaigns\Models\ProposalTemplate;
use App\Domain\Leads\Models\Lead;
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

    public string $channel = 'EMAIL';

    /** @var string[] */
    public array $leadIds = [];

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
            ->filter(fn ($id) => $id !== '')
            ->unique()
            ->values()
            ->all();

        if ($presetIds !== []) {
            $eligiblePresets = Lead::query()
                ->visibleTo(auth()->user())
                ->active()
                ->whereNotNull('contact_id')
                ->whereIn('id', $presetIds)
                ->pluck('id')
                ->all();

            $this->leadIds = $eligiblePresets;
        }
    }

    public function updatedChannel(): void
    {
        $this->message_template_id = '';
    }

    public function selectAllEligible(): void
    {
        $this->leadIds = Lead::query()
            ->visibleTo(auth()->user())
            ->active()
            ->whereNotNull('contact_id')
            ->with('contact')
            ->latest()
            ->limit(500)
            ->get()
            ->map(fn (Lead $lead) => (string) $lead->id)
            ->all();

        $this->dispatch('notify', message: 'Semua lead dalam daftar dipilih. Konsultasikan consent sebelum mengirim.', type: 'info');
    }

    public function save(CreateProposalCampaignAction $action)
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'proposal_template_id' => ['required', 'uuid', 'exists:proposal_templates,id'],
            'message_template_id' => ['required', 'uuid', 'exists:message_templates,id'],
            'channel' => ['required', Rule::enum(ProposalChannel::class)],
            'leadIds' => ['required', 'array', 'min:1'],
            'leadIds.*' => ['uuid', 'exists:leads,id'],
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

        if ($channel === ProposalChannel::WhatsApp && ! config('proposals.whatsapp.proposal_campaign_enabled')) {
            $this->addError('channel', 'Campaign proposal WhatsApp belum diaktifkan administrator.');

            return;
        }

        $messageTemplate = MessageTemplate::findOrFail($data['message_template_id']);
        if ($messageTemplate->channel !== $channel->value) {
            $this->addError('message_template_id', 'Template pesan tidak sesuai dengan channel yang dipilih.');

            return;
        }

        $leadIds = array_values(array_unique($data['leadIds']));

        $validLeadIds = Lead::query()
            ->visibleTo(auth()->user())
            ->active()
            ->whereNotNull('contact_id')
            ->whereIn('id', $leadIds)
            ->pluck('id')
            ->all();

        if (count($validLeadIds) !== count($leadIds)) {
            $this->addError('leadIds', 'Sebagian lead yang dipilih tidak valid, sudah diarsipkan, atau tidak memiliki kontak.');

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
            ? Carbon::createFromFormat(
                'Y-m-d\TH:i',
                $data['scheduledStartAt'],
                $data['timezone']
            )->utc()
            : null;

        try {
            $campaign = $action->handle(
                name: trim($data['name']),
                proposalTemplate: ProposalTemplate::findOrFail($data['proposal_template_id']),
                messageTemplate: $messageTemplate,
                channel: $channel,
                leadIds: $validLeadIds,
                createdBy: (int) auth()->id(),
                settings: [
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

            session()->flash('status', 'Proposal campaign berhasil dibuat sebagai draft.');

            return redirect()->route('proposals.show', $campaign);
        } catch (\Throwable $e) {
            report($e);
            $this->addError('form', 'Proposal campaign tidak dapat dibuat. Silakan periksa kembali data dan coba lagi.');
        }
    }

    public function render()
    {
        return view('livewire.proposals.form', [
            'proposalTemplates' => ProposalTemplate::active()->orderBy('name')->get(),
            'messageTemplates' => MessageTemplate::active()->where('channel', $this->channel)->orderBy('name')->get(),
            'leads' => Lead::query()
                ->visibleTo(auth()->user())
                ->active()
                ->whereNotNull('contact_id')
                ->with(['company', 'contact'])
                ->latest()
                ->limit(500)
                ->get(),
            'selectedCount' => count($this->leadIds),
            'whatsAppEnabled' => (bool) config('proposals.whatsapp.proposal_campaign_enabled'),
        ]);
    }
}
