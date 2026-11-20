<?php

namespace App\Domain\Campaigns\Queries;

use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Support\AudienceFilter;
use App\Domain\Campaigns\Enums\DeliveryStatus;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Builds the lead/contact audience for a campaign without loading all rows.
 * Returns one row per contact, matching campaign_recipients uniqueness.
 */
final class ProposalAudienceQuery
{
    private readonly array $successStatuses;

    public function __construct(
        private readonly Campaign $campaign,
        private readonly AudienceFilter $filter,
    ) {
        $this->successStatuses = [
            DeliveryStatus::Sent->value,
            DeliveryStatus::Accepted->value,
            DeliveryStatus::Delivered->value,
            DeliveryStatus::Unknown->value,
        ];
    }

    private function base(): Builder
    {
        $data = $this->filter->toArray();

        $query = DB::table('leads')
            ->join('contacts', 'contacts.id', '=', 'leads.contact_id')
            ->join('companies', 'companies.id', '=', 'leads.company_id')
            ->whereNull('leads.archived_at')
            ->whereNotNull('leads.contact_id');

        if ($this->campaign->audience_snapshot_at !== null) {
            $query->where('leads.created_at', '<=', $this->campaign->audience_snapshot_at);
        }

        if ($this->filter->isManual()) {
            $ids = $this->filter->manualLeadIds();
            if ($ids === []) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('leads.id', $ids);
            }
        } else {
            if ($data['lead_statuses'] !== []) {
                $query->whereIn('leads.status', $data['lead_statuses']);
            }

            if ($data['source_ids'] !== []) {
                $query->whereIn('leads.source_id', $data['source_ids']);
            }

            if ($data['import_batch_ids'] !== []) {
                $batches = $data['import_batch_ids'];
                $query->whereExists(function ($sub) use ($batches): void {
                    $sub->selectRaw('1')
                        ->from('import_rows')
                        ->whereColumn('import_rows.lead_id', 'leads.id')
                        ->whereIn('import_rows.batch_id', $batches);
                });
            }

            if ($data['industries'] !== []) {
                $query->whereIn('companies.industry', $data['industries']);
            }

            if ($data['cities'] !== []) {
                $query->whereIn('companies.city', $data['cities']);
            }

            if ($data['country_codes'] !== []) {
                $query->whereIn('companies.country_code', $data['country_codes']);
            }

            if ($data['tag_ids'] !== []) {
                $tags = $data['tag_ids'];

                if ($data['tag_match'] === 'all') {
                    foreach ($tags as $tagId) {
                        $query->whereExists(function ($sub) use ($tagId): void {
                            $sub->selectRaw('1')
                                ->from('lead_tags')
                                ->whereColumn('lead_tags.lead_id', 'leads.id')
                                ->where('lead_tags.tag_id', $tagId);
                        });
                    }
                } else {
                    $query->whereExists(function ($sub) use ($tags): void {
                        $sub->selectRaw('1')
                            ->from('lead_tags')
                            ->whereColumn('lead_tags.lead_id', 'leads.id')
                            ->whereIn('lead_tags.tag_id', $tags);
                    });
                }
            }

            $this->applyAvailability($query, 'email', $data['email_availability']);
            $this->applyAvailability($query, 'whatsapp', $data['whatsapp_availability']);
            $this->applyPriorOutreach($query, $data['prior_outreach']);
        }

        return $query;
    }

    private function applyAvailability(Builder $query, string $column, string $mode): void
    {
        if ($mode === 'any') {
            return;
        }

        $field = $column === 'email' ? 'contacts.normalized_email' : 'contacts.phone_e164';

        if ($mode === 'available') {
            $query->whereNotNull($field)->where($field, '<>', '');
        } else {
            $query->where(static function ($q) use ($field): void {
                $q->whereNull($field)->orWhere($field, '=', '');
            });
        }
    }

    private function applyPriorOutreach(Builder $query, string $mode): void
    {
        if ($mode === 'any') {
            return;
        }

        $channels = $this->campaign->selectedChannels();
        $channelValues = array_map(static fn ($channel) => $channel->value, $channels);

        if ($channelValues === []) {
            return;
        }

        $history = static function ($sub) use ($channelValues): void {
            $sub->selectRaw('1')
                ->from('campaign_recipient_channels as crc')
                ->whereColumn('crc.contact_id', 'contacts.id')
                ->whereIn('crc.channel', $channelValues);
        };

        if ($mode === 'never_contacted') {
            $query->whereNotExists(function ($sub) use ($history, $channelValues): void {
                $history($sub)->whereIn('crc.delivery_status', $this->successStatuses);
            });

            return;
        }

        // failed_only
        $query->whereExists(function ($sub): void {
            $sub->selectRaw('1')
                ->from('campaign_recipient_channels as crc')
                ->whereColumn('crc.contact_id', 'contacts.id')
                ->where('crc.delivery_status', DeliveryStatus::Failed->value);
        })->whereNotExists(function ($sub) use ($history): void {
            $history($sub)->whereIn('crc.delivery_status', $this->successStatuses);
        });
    }

    /**
     * Distinct-contact subquery ordered by contact_id for keyset pagination.
     *
     * @return Builder<\stdClass>
     */
    private function distinct(): Builder
    {
        return $this->base()
            ->selectRaw('DISTINCT ON (leads.contact_id) leads.contact_id as contact_id, leads.id as lead_id, leads.created_at as lead_created_at')
            ->orderByRaw('leads.contact_id ASC, leads.created_at ASC, leads.id ASC');
    }

    /** @return Collection<int, object> */
    public function pairs(?string $cursor, int $limit): Collection
    {
        $query = DB::query()
            ->fromSub($this->distinct(), 'audience')
            ->select('audience.contact_id', 'audience.lead_id')
            ->orderBy('audience.contact_id');

        if ($cursor !== null && $cursor !== '') {
            $query->where('audience.contact_id', '>', $cursor);
        }

        return $query->limit($limit)->get();
    }

    public function count(): int
    {
        return (int) DB::query()
            ->fromSub($this->distinct(), 'audience')
            ->count();
    }
}
