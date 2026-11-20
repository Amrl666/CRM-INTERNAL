<?php

namespace App\Domain\Campaigns\Support;

use InvalidArgumentException;

/**
 * Normalized, validated representation of campaigns.audience_filters.
 *
 * Stored as JSONB; query construction lives in ProposalAudienceQuery.
 */
final class AudienceFilter
{
    public const VERSION = 1;

    public const MAX_MANUAL_LEADS = 1000;

    private const AVAILABILITY = ['any', 'available', 'unavailable'];

    private const PRIOR_OUTREACH = ['any', 'never_contacted', 'failed_only'];

    /** @param array<string, mixed> $data */
    private function __construct(public readonly array $data) {}

    /** @param array<string, mixed> $filters */
    public static function fromArray(array $filters): self
    {
        $mode = (string) ($filters['mode'] ?? 'filter');

        if (! in_array($mode, ['filter', 'manual'], true)) {
            throw new InvalidArgumentException('Mode audience tidak valid.');
        }

        $normalizeIds = static function (mixed $value): array {
            if (! is_array($value)) {
                return [];
            }

            return array_values(array_unique(array_filter(array_map(
                static fn ($id) => is_scalar($id) ? trim((string) $id) : '',
                $value,
            ))));
        };

        $normalizeStrings = static function (mixed $value, int $length = 120): array {
            if (! is_array($value)) {
                return [];
            }

            return array_values(array_unique(array_filter(array_map(
                static fn ($item) => is_scalar($item) ? mb_substr(trim((string) $item), 0, $length) : '',
                $value,
            ))));
        };

        $manual = $normalizeIds($filters['manual_lead_ids'] ?? []);

        if ($mode === 'manual' && count($manual) > self::MAX_MANUAL_LEADS) {
            throw new InvalidArgumentException('Mode manual maksimal '.self::MAX_MANUAL_LEADS.' lead.');
        }

        $availability = static function (mixed $value): string {
            $value = is_string($value) ? $value : 'any';

            return in_array($value, self::AVAILABILITY, true) ? $value : 'any';
        };

        $prior = $filters['prior_outreach'] ?? 'any';
        $prior = is_string($prior) ? $prior : 'any';

        if (! in_array($prior, self::PRIOR_OUTREACH, true)) {
            $prior = 'any';
        }

        $tagMatch = $filters['tag_match'] ?? 'any';
        $tagMatch = is_string($tagMatch) && in_array($tagMatch, ['any', 'all'], true)
            ? $tagMatch
            : 'any';

        return new self([
            'version' => self::VERSION,
            'mode' => $mode,
            'lead_statuses' => $normalizeStrings($filters['lead_statuses'] ?? [], 30),
            'source_ids' => $normalizeIds($filters['source_ids'] ?? []),
            'import_batch_ids' => $normalizeIds($filters['import_batch_ids'] ?? []),
            'industries' => $normalizeStrings($filters['industries'] ?? []),
            'cities' => $normalizeStrings($filters['cities'] ?? []),
            'country_codes' => array_values(array_map(
                static fn (string $code) => mb_strtoupper(mb_substr($code, 0, 2)),
                $normalizeStrings($filters['country_codes'] ?? [], 2),
            )),
            'tag_ids' => $normalizeIds($filters['tag_ids'] ?? []),
            'tag_match' => $tagMatch,
            'email_availability' => $availability($filters['email_availability'] ?? 'any'),
            'whatsapp_availability' => $availability($filters['whatsapp_availability'] ?? 'any'),
            'prior_outreach' => $prior,
            'manual_lead_ids' => $manual,
        ]);
    }

    /** @param string[] $leadIds */
    public static function fromLeadIds(array $leadIds): self
    {
        return self::fromArray([
            'mode' => 'manual',
            'manual_lead_ids' => $leadIds,
        ]);
    }

    public static function empty(): self
    {
        return self::fromArray([]);
    }

    public function mode(): string
    {
        return (string) $this->data['mode'];
    }

    public function isManual(): bool
    {
        return $this->mode() === 'manual';
    }

    /** @return string[] */
    public function manualLeadIds(): array
    {
        return $this->data['manual_lead_ids'];
    }

    public function priorOutreach(): string
    {
        return (string) $this->data['prior_outreach'];
    }

    public function hasFilters(): bool
    {
        foreach ($this->data as $key => $value) {
            if (in_array($key, ['version', 'mode', 'tag_match', 'prior_outreach'], true)) {
                continue;
            }

            if (is_array($value) && $value !== []) {
                return true;
            }

            if (is_string($value) && ! in_array($value, ['any'], true)) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->data;
    }
}
