<?php

namespace App\Domain\Campaigns\Services;

use App\Domain\Campaigns\Enums\DeliveryStatus;
use App\Domain\Campaigns\Models\Campaign;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class BusinessSchedule
{
    /**
     * Legacy recipient-level planner.
     *
     * @param  array<int, string>  $recipientIds
     * @return array<string, CarbonImmutable>
     */
    public function plan(Campaign $campaign, array $recipientIds, ?CarbonInterface $from = null): array
    {
        return $this->planUnits($campaign, $recipientIds, 'recipient', $from);
    }

    /**
     * Per-channel planner. All channels of a campaign share the same
     * rate/spacing window; channel rows keep independent scheduled_at.
     *
     * @param  array<int, string>  $channelRowIds
     * @return array<string, CarbonImmutable>
     */
    public function planChannelRows(Campaign $campaign, array $channelRowIds, ?CarbonInterface $from = null): array
    {
        return $this->planUnits($campaign, $channelRowIds, 'channel', $from);
    }

    /** @return array<string, CarbonImmutable> */
    private function planUnits(
        Campaign $campaign,
        array $ids,
        string $unit,
        ?CarbonInterface $from = null,
    ): array {
        $timezone = (string) (
            $campaign->timezone
            ?: config('proposals.defaults.timezone', 'Asia/Jakarta')
        );

        try {
            new DateTimeZone($timezone);
        } catch (Throwable) {
            throw new RuntimeException("Timezone campaign tidak valid: {$timezone}");
        }

        $rawDays = $campaign->business_days;
        $days = array_values(array_unique(array_map(
            'intval',
            is_array($rawDays) ? $rawDays : [1, 2, 3, 4, 5],
        )));

        sort($days);

        if ($days === [] || array_diff($days, [1, 2, 3, 4, 5, 6, 7]) !== []) {
            throw new RuntimeException('Hari aktif harus berisi minimal satu hari valid (1-7).');
        }

        $excludedDates = is_array($campaign->excluded_dates) ? $campaign->excluded_dates : [];
        $excluded = [];

        foreach ($excludedDates as $date) {
            if (! is_string($date) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || ! $this->isValidDate($date)) {
                throw new RuntimeException('Tanggal pengecualian harus menggunakan format YYYY-MM-DD yang valid.');
            }
            $excluded[$date] = true;
        }

        $rateLimit = (int) $campaign->rate_limit_per_hour;
        $userSpacing = (int) $campaign->min_spacing_seconds;

        if ($rateLimit < 1 || $rateLimit > 120) {
            throw new RuntimeException('Rate limit harus berada pada rentang 1-120 per jam.');
        }

        if ($userSpacing < 0 || $userSpacing > 3600) {
            throw new RuntimeException('Jarak minimum pengiriman tidak valid.');
        }

        $startTime = $this->normalizeTime((string) ($campaign->business_start ?: '09:00'));
        $endTime = $this->normalizeTime((string) ($campaign->business_end ?: '17:00'));

        if ($endTime <= $startTime) {
            throw new RuntimeException('Jam selesai operasional harus lebih besar dari jam mulai.');
        }

        $minimumRateSpacing = max(1, (int) floor(3600 / $rateLimit));
        $spacing = max($userSpacing, $minimumRateSpacing);

        $start = $from !== null
            ? CarbonImmutable::instance($from)->setTimezone($timezone)
            : CarbonImmutable::now($timezone);

        if ($campaign->scheduled_start_at !== null) {
            $configuredStart = CarbonImmutable::instance($campaign->scheduled_start_at)->setTimezone($timezone);
            if ($configuredStart->greaterThan($start)) {
                $start = $configuredStart;
            }
        }

        $events = $this->existingEvents($campaign, $ids, $unit, $start, $timezone);
        $hourlyCounts = [];

        foreach ($events as $event) {
            $hourKey = $event->format('Y-m-d H');
            $hourlyCounts[$hourKey] = ($hourlyCounts[$hourKey] ?? 0) + 1;
        }

        $cursor = $this->nextOpening($start, $timezone, $days, $excluded, $startTime, $endTime);
        $plan = [];

        foreach ($ids as $id) {
            $attempts = 0;

            while (true) {
                if (++$attempts > 10000) {
                    throw new RuntimeException('Gagal menemukan slot jadwal dalam batas pencarian.');
                }

                $candidate = $this->nextOpening($cursor, $timezone, $days, $excluded, $startTime, $endTime);
                $conflict = $this->conflictAt($events, $candidate, $spacing);

                if ($conflict !== null) {
                    $cursor = $conflict->addSeconds($spacing);

                    continue;
                }

                $hourKey = $candidate->format('Y-m-d H');
                if (($hourlyCounts[$hourKey] ?? 0) >= $rateLimit) {
                    $cursor = $candidate->startOfHour()->addHour();

                    continue;
                }

                break;
            }

            $plan[(string) $id] = $candidate->setTimezone('UTC');
            $hourKey = $candidate->format('Y-m-d H');
            $hourlyCounts[$hourKey] = ($hourlyCounts[$hourKey] ?? 0) + 1;
            $this->insertSorted($events, $candidate);
            $cursor = $candidate->addSeconds($spacing);
        }

        return $plan;
    }

    /**
     * Find the nearest existing event within $spacing using binary search.
     *
     * @param  array<int, CarbonImmutable>  $events
     */
    private function conflictAt(array $events, CarbonImmutable $candidate, int $spacing): ?CarbonImmutable
    {
        $low = 0;
        $high = count($events) - 1;

        while ($low <= $high) {
            $mid = intdiv($low + $high, 2);
            if ($events[$mid]->getTimestamp() < $candidate->getTimestamp()) {
                $low = $mid + 1;
            } else {
                $high = $mid - 1;
            }
        }

        foreach ([$low - 1, $low] as $index) {
            if (! isset($events[$index])) {
                continue;
            }

            if (abs($events[$index]->getTimestamp() - $candidate->getTimestamp()) < $spacing) {
                return $events[$index];
            }
        }

        return null;
    }

    /** @param array<int, CarbonImmutable> $events */
    private function insertSorted(array &$events, CarbonImmutable $event): void
    {
        $low = 0;
        $high = count($events);

        while ($low < $high) {
            $mid = intdiv($low + $high, 2);
            if ($events[$mid]->getTimestamp() < $event->getTimestamp()) {
                $low = $mid + 1;
            } else {
                $high = $mid;
            }
        }

        array_splice($events, $low, 0, [$event]);
    }

    public function isWithinBusinessWindow(Campaign $campaign, ?CarbonInterface $at = null): bool
    {
        $timezone = (string) (
            $campaign->timezone
            ?: config('proposals.defaults.timezone', 'Asia/Jakarta')
        );

        try {
            new DateTimeZone($timezone);
        } catch (Throwable) {
            throw new RuntimeException("Timezone campaign tidak valid: {$timezone}");
        }

        $rawDays = $campaign->business_days;
        $days = array_values(array_unique(array_map(
            'intval',
            is_array($rawDays) ? $rawDays : [1, 2, 3, 4, 5],
        )));

        if ($days === [] || array_diff($days, [1, 2, 3, 4, 5, 6, 7]) !== []) {
            throw new RuntimeException('Hari aktif harus berisi minimal satu hari valid (1-7).');
        }

        $excludedDates = is_array($campaign->excluded_dates) ? $campaign->excluded_dates : [];
        $excluded = [];

        foreach ($excludedDates as $date) {
            if (! is_string($date) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || ! $this->isValidDate($date)) {
                throw new RuntimeException('Tanggal pengecualian harus menggunakan format YYYY-MM-DD yang valid.');
            }
            $excluded[$date] = true;
        }

        $startTime = $this->normalizeTime((string) ($campaign->business_start ?: '09:00'));
        $endTime = $this->normalizeTime((string) ($campaign->business_end ?: '17:00'));

        if ($endTime <= $startTime) {
            throw new RuntimeException('Jam selesai operasional harus lebih besar dari jam mulai.');
        }

        $local = CarbonImmutable::instance($at ?? CarbonImmutable::now('UTC'))->setTimezone($timezone);
        $dateKey = $local->format('Y-m-d');
        $time = $local->format('H:i:s');

        return in_array((int) $local->dayOfWeekIso, $days, true)
            && ! isset($excluded[$dateKey])
            && $time >= $startTime
            && $time < $endTime;
    }

    /**
     * @param  array<int, string>  $ids
     * @return array<int, CarbonImmutable>
     */
    private function existingEvents(
        Campaign $campaign,
        array $ids,
        string $unit,
        CarbonImmutable $start,
        string $timezone,
    ): array {
        $events = [];

        if ($unit === 'channel') {
            $reservations = DB::table('campaign_recipient_channels as crc')
                ->where('crc.campaign_id', $campaign->id)
                ->whereIn('crc.delivery_status', [
                    DeliveryStatus::Scheduled->value,
                    DeliveryStatus::Processing->value,
                ])
                ->whereNotNull('crc.scheduled_at')
                ->whereNotExists(function ($query): void {
                    $query->selectRaw('1')
                        ->from('proposal_delivery_attempts as used')
                        ->whereColumn('used.campaign_recipient_channel_id', 'crc.id')
                        ->whereColumn('used.attempted_at', '>=', 'crc.scheduled_at');
                });

            if ($ids !== []) {
                $reservations->whereNotIn('crc.id', $ids);
            }

            foreach ($reservations->pluck('crc.scheduled_at') as $timestamp) {
                $events[] = CarbonImmutable::parse($timestamp, 'UTC')->setTimezone($timezone);
            }

            $attempts = DB::table('proposal_delivery_attempts as pda')
                ->join('campaign_recipient_channels as crc', 'crc.id', '=', 'pda.campaign_recipient_channel_id')
                ->where('crc.campaign_id', $campaign->id)
                ->where('pda.attempted_at', '>=', $start->startOfHour()->utc())
                ->pluck('pda.attempted_at');
        } else {
            $reservations = DB::table('campaign_recipients as cr')
                ->where('cr.campaign_id', $campaign->id)
                ->whereIn('cr.delivery_status', [
                    DeliveryStatus::Scheduled->value,
                    DeliveryStatus::Processing->value,
                ])
                ->whereNotNull('cr.scheduled_at')
                ->whereNotExists(function ($query): void {
                    $query->selectRaw('1')
                        ->from('proposal_delivery_attempts as used')
                        ->whereColumn('used.campaign_recipient_id', 'cr.id')
                        ->whereColumn('used.attempted_at', '>=', 'cr.scheduled_at');
                });

            if ($ids !== []) {
                $reservations->whereNotIn('cr.id', $ids);
            }

            foreach ($reservations->pluck('cr.scheduled_at') as $timestamp) {
                $events[] = CarbonImmutable::parse($timestamp, 'UTC')->setTimezone($timezone);
            }

            $attempts = DB::table('proposal_delivery_attempts as pda')
                ->join('campaign_recipients as cr', 'cr.id', '=', 'pda.campaign_recipient_id')
                ->where('cr.campaign_id', $campaign->id)
                ->where('pda.attempted_at', '>=', $start->startOfHour()->utc())
                ->pluck('pda.attempted_at');
        }

        foreach ($attempts as $timestamp) {
            $events[] = CarbonImmutable::parse($timestamp, 'UTC')->setTimezone($timezone);
        }

        usort($events, fn (CarbonImmutable $a, CarbonImmutable $b): int => $a->getTimestamp() <=> $b->getTimestamp());

        return $events;
    }

    /** @param array<int, int> $days @param array<string, bool> $excluded */
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

        throw new RuntimeException('Tidak ada slot pengiriman dalam rentang 800 hari.');
    }

    private function normalizeTime(string $value): string
    {
        if (! preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $value)) {
            throw new RuntimeException("Format jam tidak valid: {$value}");
        }

        return strlen($value) === 5 ? $value.':00' : $value;
    }

    private function isValidDate(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }
}
