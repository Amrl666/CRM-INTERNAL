<?php

namespace App\Domain\Campaigns\Services;

use App\Domain\Campaigns\Models\Campaign;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

final class BusinessSchedule
{
    /**
     * @param  array<int, string>  $recipientIds  ordered recipient IDs
     * @return array<string, CarbonImmutable> recipient id => UTC timestamp
     */
    public function plan(Campaign $campaign, array $recipientIds, ?CarbonInterface $from = null): array
    {
        $timezone = (string) ($campaign->timezone ?: config('proposals.defaults.timezone', 'Asia/Jakarta'));
        $rawDays = $campaign->business_days;
        $days = array_map('intval', is_array($rawDays) ? $rawDays : [1, 2, 3, 4, 5]);
        $excluded = array_flip(is_array($campaign->excluded_dates) ? $campaign->excluded_dates : []);

        if ($days === []) {
            throw new \RuntimeException('Jadwal bisnis harus memiliki minimal satu hari aktif.');
        }

        $rateLimit = max(1, (int) $campaign->rate_limit_per_hour);
        $spacing = max(0, (int) $campaign->min_spacing_seconds);
        $perHour = max(1, (int) floor(3600 / max(1, $rateLimit === 0 ? 1 : $rateLimit)));
        $spacing = max($spacing, $perHour);

        $start = $from !== null
            ? CarbonImmutable::instance($from)
            : CarbonImmutable::now($timezone);

        if ($campaign->scheduled_start_at !== null) {
            $configuredStart = CarbonImmutable::instance($campaign->scheduled_start_at)->setTimezone($timezone);
            if ($configuredStart->greaterThan($start)) {
                $start = $configuredStart;
            }
        }

        $startTime = $this->time((string) ($campaign->business_start ?: '09:00'));
        $endTime = $this->time((string) ($campaign->business_end ?: '17:00'));

        $cursor = $this->nextOpening($start, $timezone, $days, $excluded, $startTime, $endTime);
        $last = null;
        $hourlyCounts = [];
        $plan = [];

        foreach ($recipientIds as $id) {
            $candidate = $cursor;

            for ($attempt = 0; $attempt < 2000; $attempt++) {
                $candidate = $this->nextOpening($candidate, $timezone, $days, $excluded, $startTime, $endTime);

                if ($last !== null && $spacing > 0) {
                    $minimum = $last->addSeconds($spacing);
                    if ($candidate->lessThan($minimum)) {
                        $candidate = $minimum;

                        continue;
                    }
                }

                $hourKey = $candidate->format('Y-m-d H');
                if (($hourlyCounts[$hourKey] ?? 0) >= $rateLimit) {
                    $candidate = $candidate->startOfHour()->addHour();

                    continue;
                }

                break;
            }

            $plan[(string) $id] = $candidate->setTimezone('UTC');
            $hourlyCounts[$candidate->format('Y-m-d H')] = ($hourlyCounts[$candidate->format('Y-m-d H')] ?? 0) + 1;
            $last = $candidate;
            $cursor = $candidate->addSeconds($spacing);
        }

        return $plan;
    }

    /** @param array<int, int> $days @param array<string, int> $excluded */
    private function nextOpening(
        CarbonImmutable $at,
        string $timezone,
        array $days,
        array $excluded,
        string $startTime,
        string $endTime,
    ): CarbonImmutable {
        $candidate = $at->setTimezone($timezone);

        for ($i = 0; $i < 800; $i++) {
            $dateKey = $candidate->format('Y-m-d');
            $weekday = (int) $candidate->dayOfWeekIso;

            if (! in_array($weekday, $days, true) || isset($excluded[$dateKey])) {
                $candidate = $candidate->addDay()->startOfDay();

                continue;
            }

            $open = CarbonImmutable::parse($dateKey.' '.$startTime, $timezone);
            $close = CarbonImmutable::parse($dateKey.' '.$endTime, $timezone);

            if ($candidate->lessThan($open)) {
                return $open;
            }

            if ($candidate->greaterThanOrEqualTo($close)) {
                $candidate = $candidate->addDay()->startOfDay();

                continue;
            }

            return $candidate;
        }

        throw new \RuntimeException('Tidak ada slot pengiriman dalam rentang jadwal yang wajar.');
    }

    private function time(string $value): string
    {
        return strlen($value) === 5 ? $value.':00' : $value;
    }
}
