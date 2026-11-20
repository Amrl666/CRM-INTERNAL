<?php

namespace App\Domain\Campaigns\Actions;

use App\Domain\Campaigns\Enums\CampaignStatus;
use App\Domain\Campaigns\Enums\DeliveryStatus;
use App\Domain\Campaigns\Enums\EligibilityStatus;
use App\Domain\Campaigns\Enums\PdfStatus;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Models\CampaignRecipient;
use App\Domain\Campaigns\Queries\ProposalAudienceQuery;
use App\Domain\Campaigns\Services\ChannelEligibilityEvaluator;
use App\Domain\Campaigns\Support\AudienceFilter;
use App\Jobs\FinalizeProposalCampaignPreparationJob;
use App\Jobs\GenerateProposalDocumentJob;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class ResolveProposalAudienceAction
{
    public function __construct(
        private readonly ChannelEligibilityEvaluator $evaluator,
    ) {}

    /**
     * Process all remaining chunks synchronously. Used by tests and the
     * compatibility schedule wrapper; HTTP flow uses the queued chunk job.
     */
    public function handle(Campaign $campaign, int $limit = 500): void
    {
        do {
            $hasMore = $this->handleChunk($campaign, $limit);
        } while ($hasMore);

        $campaign->refresh();
    }

    /** Process one chunk. Returns true when another chunk is available. */
    public function handleChunk(Campaign $campaign, int $limit = 500): bool
    {
        $locked = Campaign::query()->lockForUpdate()->findOrFail($campaign->id);

        if (! in_array($locked->status, [CampaignStatus::Draft, CampaignStatus::Preparing], true)) {
            return false;
        }

        if ($locked->status === CampaignStatus::Preparing && ! $locked->isApprovedForCurrentRevision()) {
            throw new InvalidArgumentException('Approval campaign tidak berlaku saat resolusi audiens.');
        }

        if ($locked->audienceIsResolved()) {
            FinalizeProposalCampaignPreparationJob::dispatch($locked->id)
                ->onQueue('campaigns')
                ->afterCommit();

            return false;
        }

        $filter = AudienceFilter::fromArray($locked->audience_filters ?? []);
        $query = new ProposalAudienceQuery($locked, $filter);
        $cursor = $locked->audience_resolution_cursor;

        $pairs = $query->pairs($cursor, $limit);

        if ($pairs->isEmpty()) {
            $locked->update([
                'audience_resolved_at' => now(),
                'audience_resolution_cursor' => null,
            ]);

            FinalizeProposalCampaignPreparationJob::dispatch($locked->id)
                ->onQueue('campaigns')
                ->afterCommit();

            return false;
        }

        $channels = $locked->selectedChannels();
        $results = $this->evaluator->checkMany($pairs, $channels, $locked->id);

        $now = now()->format('Y-m-d H:i:s');
        $recipientRows = [];

        foreach ($pairs as $pair) {
            $recipientRows[] = [
                Str::uuid()->toString(),
                $locked->id,
                (string) $pair->contact_id,
                (string) $pair->lead_id,
                'pending',
                EligibilityStatus::Pending->value,
                PdfStatus::Pending->value,
                DeliveryStatus::Pending->value,
                'proposal:'.$locked->id.':'.$pair->contact_id,
                $now,
                $now,
            ];
        }

        $leadByContact = [];
        foreach ($pairs as $pair) {
            $leadByContact[(string) $pair->contact_id] = (string) $pair->lead_id;
        }

        $inserted = $this->insertRecipients($recipientRows);

        $channelRows = [];
        $pdfRecipients = [];

        foreach ($inserted as $row) {
            $contactId = (string) $row->contact_id;
            $hasEligible = false;

            foreach ($channels as $channel) {
                $result = $results[$contactId.'|'.$channel->value] ?? ['eligible' => false, 'reasons' => ['Channel tidak dapat dievaluasi.']];
                $eligible = (bool) $result['eligible'];
                $reason = $eligible ? null : implode(' ', $result['reasons']);

                $channelRows[] = [
                    'id' => Str::uuid()->toString(),
                    'campaign_recipient_id' => (string) $row->id,
                    'campaign_id' => $locked->id,
                    'contact_id' => $contactId,
                    'lead_id' => $leadByContact[$contactId] ?? null,
                    'channel' => $channel->value,
                    'eligibility_status' => $eligible ? EligibilityStatus::Eligible->value : EligibilityStatus::Ineligible->value,
                    'eligibility_reason' => $reason,
                    'delivery_status' => $eligible ? DeliveryStatus::Pending->value : DeliveryStatus::Skipped->value,
                    'skip_reason' => $eligible ? null : $reason,
                    'idempotency_key' => 'proposal:'.$locked->id.':'.$contactId.':'.$channel->value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if ($eligible) {
                    $hasEligible = true;
                }
            }

            $allReasons = [];
            foreach ($channels as $channel) {
                $result = $results[$contactId.'|'.$channel->value] ?? null;
                if ($result !== null && ! $result['eligible']) {
                    $allReasons = array_merge($allReasons, $result['reasons']);
                }
            }

            CampaignRecipient::query()
                ->whereKey($row->id)
                ->update([
                    'eligibility_status' => $hasEligible
                        ? EligibilityStatus::Eligible->value
                        : EligibilityStatus::Ineligible->value,
                    'eligibility_reason' => $hasEligible ? null : implode(' ', array_unique($allReasons)),
                    'delivery_status' => $hasEligible
                        ? DeliveryStatus::Pending->value
                        : DeliveryStatus::Skipped->value,
                ]);

            if ($hasEligible) {
                $pdfRecipients[] = (string) $row->id;
            }
        }

        if ($channelRows !== []) {
            DB::table('campaign_recipient_channels')->insertOrIgnore($channelRows);
        }

        foreach ($pdfRecipients as $recipientId) {
            GenerateProposalDocumentJob::dispatch($recipientId)
                ->onQueue('documents')
                ->afterCommit();
        }

        $locked->update([
            'audience_resolution_cursor' => (string) $pairs->last()->contact_id,
        ]);

        if ($pairs->count() < $limit) {
            $locked->update([
                'audience_resolved_at' => now(),
                'audience_resolution_cursor' => null,
            ]);

            FinalizeProposalCampaignPreparationJob::dispatch($locked->id)
                ->onQueue('campaigns')
                ->afterCommit();

            return false;
        }

        return true;
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     * @return Collection<int, object>
     */
    private function insertRecipients(array $rows): Collection
    {
        if ($rows === []) {
            return collect();
        }

        $placeholders = implode(', ', array_fill(0, count($rows), '(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'));
        $bindings = [];
        foreach ($rows as $row) {
            foreach ($row as $value) {
                $bindings[] = $value;
            }
        }

        return collect(DB::select(<<<SQL
            INSERT INTO campaign_recipients
                (id, campaign_id, contact_id, lead_id, status, eligibility_status,
                 pdf_status, delivery_status, idempotency_key, created_at, updated_at)
            VALUES {$placeholders}
            ON CONFLICT (campaign_id, contact_id) DO NOTHING
            RETURNING id, contact_id
            SQL, $bindings));
    }
}
