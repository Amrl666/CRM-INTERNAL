<?php

namespace App\Domain\Leads\Services;

use App\Domain\Contacts\Models\Contact;
use App\Domain\Leads\Actions\CreateLeadAction;
use App\Domain\Leads\Models\ImportBatch;
use App\Domain\Leads\Models\ImportRow;
use App\Support\PhoneNormalizer;
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
        $success = 0;
        $failed = 0;
        $duplicate = 0;
        $seen = [];

        foreach ($this->iterate($path, $extension) as $index => $row) {
            if ($index === 0) {
                $headers = array_map(fn ($h) => mb_strtolower(trim((string) $h)), $row);

                continue;
            }

            $dataRow++;
            $raw = array_combine($headers, array_pad($row, count($headers), null)) ?: [];
            $normalized = array_map(fn ($v) => trim((string) $v), $raw);
            $error = $this->validateRow($normalized, $headers);

            if ($error !== null) {
                $failed++;
                $this->writeRow($batch, $dataRow, $raw, 'failed', $error);

                continue;
            }

            $email = Contact::normalizeEmail($normalized['email'] ?? null);
            $phone = PhoneNormalizer::toE164($normalized['phone'] ?? null);
            $key = ($email ?: '').'|'.($phone ?: '');

            if (($email || $phone) && isset($seen[$key])) {
                $duplicate++;
                $this->writeRow($batch, $dataRow, $raw, 'duplicate', 'Duplikat di dalam file.');

                continue;
            }

            if ($this->existsInDatabase($email, $phone)) {
                $duplicate++;
                $this->writeRow($batch, $dataRow, $raw, 'duplicate', 'Kontak sudah ada di database.');

                continue;
            }

            $seen[$key] = true;

            $lead = $this->createLead->handle([
                'company_name' => $normalized['company_name'],
                'contact_name' => $normalized['contact_name'],
                'email' => $email,
                'phone' => $normalized['phone'] ?? null,
                'industry' => $normalized['industry'] ?? null,
                'source' => $normalized['source'] ?? null,
            ], $batch->created_by);

            $success++;
            $this->writeRow($batch, $dataRow, $raw, 'success', null, $lead->id);
        }

        $batch->update([
            'status' => 'completed',
            'total_rows' => $dataRow,
            'success_rows' => $success,
            'failed_rows' => $failed,
            'duplicate_rows' => $duplicate,
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
        ImportRow::create([
            'batch_id' => $batch->id,
            'row_number' => $rowNumber,
            'raw' => $raw,
            'status' => $status,
            'error' => $error,
            'lead_id' => $leadId,
        ]);
    }
}
