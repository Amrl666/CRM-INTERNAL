<?php

namespace App\Domain\Campaigns\Services;

use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Queries\ProposalAudienceQuery;
use App\Domain\Campaigns\Support\AudienceFilter;
use Illuminate\Support\Facades\Cache;

/**
 * Estimated audience preview. Never writes recipient rows.
 */
final class AudiencePreview
{
    public function __construct(
        private readonly ChannelEligibilityEvaluator $evaluator,
    ) {}

    /** @return array<string, mixed> */
    public function estimate(Campaign $campaign, AudienceFilter $filter, bool $fresh = false): array
    {
        $key = 'audience-preview:'.($campaign->id ?: 'new').':'.md5(json_encode($filter->toArray()));

        if (! $fresh) {
            $cached = Cache::get($key);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $query = new ProposalAudienceQuery($campaign, $filter);
        $channels = $campaign->selectedChannels();

        $total = 0;
        $perChannel = [];
        $reasons = [];

        foreach ($channels as $channel) {
            $perChannel[$channel->value] = ['eligible' => 0, 'ineligible' => 0];
        }

        $cursor = null;
        do {
            $pairs = $query->pairs($cursor, max(1, (int) config('proposals.audience.chunk_size', 500)));
            if ($pairs->isEmpty()) {
                break;
            }

            $total += $pairs->count();
            $cursor = (string) $pairs->last()->contact_id;

            $results = $this->evaluator->checkMany($pairs, $channels, $campaign->id);

            foreach ($pairs as $pair) {
                foreach ($channels as $channel) {
                    $result = $results[(string) $pair->contact_id.'|'.$channel->value] ?? null;
                    if ($result === null) {
                        continue;
                    }

                    $label = $result['eligible'] ? 'eligible' : 'ineligible';
                    $perChannel[$channel->value][$label]++;

                    if (! $result['eligible']) {
                        $message = implode(' ', $result['reasons']);
                        $reasons[$channel->value][$message] = ($reasons[$channel->value][$message] ?? 0) + 1;
                    }
                }
            }
        } while (true);

        $sends = array_sum(array_column($perChannel, 'eligible'));
        $estimate = $this->deliveryEstimate($campaign, $sends);

        $result = [
            'estimated' => true,
            'total_contacts' => $total,
            'channels' => $perChannel,
            'reasons' => $reasons,
            'delivery' => $estimate,
            'calculated_at' => now()->toIso8601String(),
        ];

        Cache::put($key, $result, now()->addSeconds(max(1, (int) config('proposals.audience.preview_cache_seconds', 60))));

        return $result;
    }

    /** @return array<string, mixed> */
    private function deliveryEstimate(Campaign $campaign, int $sends): array
    {
        $rate = max(1, (int) $campaign->rate_limit_per_hour);
        $spacing = max(0, (int) $campaign->min_spacing_seconds);
        $effective = min($rate, (int) floor(3600 / max($spacing, 1)));
        $hours = $effective > 0 ? $sends / $effective : 0;

        return [
            'total_sends' => $sends,
            'effective_per_hour' => $effective,
            'sending_hours' => round($hours, 1),
        ];
    }
}
