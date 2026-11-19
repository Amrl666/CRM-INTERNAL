<?php

namespace App\Domain\Leads\Services;

use App\Domain\Contacts\Models\Contact;
use App\Domain\Leads\Actions\CreateLeadAction;
use App\Domain\Leads\Models\ImportBatch;
use App\Domain\Leads\Models\ImportRow;
use App\Support\PhoneNormalizer;
use Illuminate\Support\Facades\DB;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

final class LeadImportService
{
    private const REQUIRED = ['company_name', 'contact_name'];

    public function __construct(private readonly CreateLeadAction $createLead) {}

    /** @return string[] */
    public function headers(string $path, string $extension): array
    {
        foreach ($this->iterate($path, $extension) as $row) {
            return array_map(fn ($h) => mb_strtolower(trim((string) $h)), $row);
        }

        return [];
    }

    /** @return array{headers: string[], rows: array<int, array<string, string>>} */
    public function preview(string $path, string $extension, int $limit = 20): array
    {
        $headers = [];
        $rows = [];

        foreach ($this->iterate($path, $extension) as $index => $row) {
            if ($index === 0) {
                $headers = array_map(fn ($h) => mb_strtolower(trim((string) $h)), $row);

                continue;
            }

            $rows[] = array_combine($headers, array_pad($row, count($headers), null)) ?: [];
            if (count($rows) >= $limit) {
                break;
            }
        }

        return ['headers' => $headers, 'rows' => $rows];
    }

    public function processBatch(ImportBatch $batch, string $path, string $extension): void
    {
        $headers = [];
        $dataRow = 0;
        $seenEmails = [];
        $seenPhones = [];

        foreach ($this->iterate($path, $extension) as $index => $row) {
            if ($index === 0) {
                $headers = array_map(fn ($h) => mb_strtolower(trim((string) $h)), $row);

                continue;
            }

            $dataRow++;
            $raw = array_combine($headers, array_pad($row, count($headers), null)) ?: [];
            $normalized = array_map(fn ($v) => trim((string) $v), $raw);

            $result = DB::transaction(function () use (
                $batch, $dataRow, $raw, $normalized, $headers,
                &$seenEmails, &$seenPhones
            ): ?array {
                $existingRow = ImportRow::query()
                    ->where('batch_id', $batch->id)
                    ->where('row_number', $dataRow)
                    ->lockForUpdate()
                    ->first();

                if ($existingRow !== null && in_array(
                    $existingRow->status,
                    ['success', 'failed', 'duplicate'],
                    true
                )) {
                    return null;
                }

                $error = $this->validateRow($normalized, $headers);

                if ($error !== null) {
                    $this->writeRow($batch, $dataRow, $raw, 'failed', $error);

                    return ['status' => 'failed'];
                }

                $email = Contact::normalizeEmail($normalized['email'] ?? null);
                $phone = PhoneNormalizer::toE164($normalized['phone'] ?? null);

                if (
                    ($email !== null && isset($seenEmails[$email]))
                    || ($phone !== null && isset($seenPhones[$phone]))
                ) {
                    $this->writeRow($batch, $dataRow, $raw, 'duplicate', 'Duplikat di dalam file.');

                    return ['status' => 'duplicate'];
                }

                if ($this->existsInDatabase($email, $phone)) {
                    $this->writeRow($batch, $dataRow, $raw, 'duplicate', 'Kontak sudah ada di database.');

                    return ['status' => 'duplicate'];
                }

                $lead = $this->createLead->handle([
                    'company_name' => $normalized['company_name'],
                    'contact_name' => $normalized['contact_name'],
                    'email' => $email,
                    'phone' => $normalized['phone'] ?? null,
                    'industry' => $normalized['industry'] ?? null,
                    'source' => $normalized['source'] ?? null,
                ], $batch->created_by, grantConsent: true, consentSource: 'lead import');

                $this->writeRow($batch, $dataRow, $raw, 'success', null, $lead->id);

                return [
                    'status' => 'success',
                    'email' => $email,
                    'phone' => $phone,
                ];
            });

            if (($result['status'] ?? null) === 'success') {
                if ($result['email'] !== null) {
                    $seenEmails[$result['email']] = true;
                }

                if ($result['phone'] !== null) {
                    $seenPhones[$result['phone']] = true;
                }
            }
        }

        $counts = ImportRow::query()
            ->where('batch_id', $batch->id)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $batch->update([
            'status' => 'completed',
            'total_rows' => $dataRow,
            'success_rows' => (int) ($counts['success'] ?? 0),
            'failed_rows' => (int) ($counts['failed'] ?? 0),
            'duplicate_rows' => (int) ($counts['duplicate'] ?? 0),
        ]);
    }

    /** @return \Generator<int, array<int, string|null>> */
    private function iterate(string $path, string $extension): \Generator
    {
        if (mb_strtolower($extension) === 'csv') {
            $handle = fopen($path, 'rb');

            if ($handle === false) {
                return;
            }

            while (($row = fgetcsv($handle)) !== false) {
                if ($row !== [] && $row[0] !== null) {
                    $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $row[0]);
                }

                yield $row;
            }

            fclose($handle);

            return;
        }

        $reader = new XlsxReader;
        $reader->open($path);

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                yield $row->toArray();
            }

            break;
        }

        $reader->close();
    }

    /** @param array<string, string> $row @param string[] $headers */
    private function validateRow(array $row, array $headers): ?string
    {
        foreach (self::REQUIRED as $required) {
            if (! in_array($required, $headers, true)) {
                return "Kolom wajib tidak ditemukan: {$required}";
            }

            if (($row[$required] ?? '') === '') {
                return "Kolom {$required} kosong.";
            }
        }

        $email = $row['email'] ?? '';
        $phone = $row['phone'] ?? '';

        if ($email === '' && $phone === '') {
            return 'Email atau telepon wajib diisi.';
        }

        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return 'Format email tidak valid.';
        }

        if ($phone !== '' && PhoneNormalizer::toE164($phone) === null) {
            return 'Format nomor telepon tidak valid.';
        }

        return null;
    }

    private function existsInDatabase(?string $email, ?string $phone): bool
    {
        if ($email === null && $phone === null) {
            return false;
        }

        return Contact::query()
            ->when($email !== null, fn ($q) => $q->where('normalized_email', $email))
            ->when($email === null && $phone !== null, fn ($q) => $q->where('phone_e164', $phone))
            ->when($email !== null && $phone !== null, fn ($q) => $q->orWhere('phone_e164', $phone))
            ->exists();
    }

    private function writeRow(ImportBatch $batch, int $rowNumber, array $raw, string $status, ?string $error, ?string $leadId = null): void
    {
        ImportRow::updateOrCreate(
            [
                'batch_id' => $batch->id,
                'row_number' => $rowNumber,
            ],
            [
                'raw' => $raw,
                'status' => $status,
                'error' => $error,
                'lead_id' => $leadId,
            ]
        );
    }
}
