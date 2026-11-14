<?php

use App\Domain\Leads\Models\ImportBatch;
use App\Domain\Leads\Models\Lead;
use App\Domain\Leads\Services\LeadImportService;
use App\Jobs\ProcessLeadImportJob;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function writeCsv(string $content): string
{
    $path = Storage::disk('local')->path('imports/test-import.csv');
    @mkdir(dirname($path), 0777, true);
    file_put_contents($path, $content);

    return $path;
}

it('imports valid rows, flags duplicates and reports missing columns', function () {
    $path = writeCsv(implode("\n", [
        'company_name,contact_name,email,phone,source',
        'PT Alfa,Budi,budi@alfa.test,081234567890,Referral',
        'PT Alfa,Budi Duplikat,budi@alfa.test,,Referral',
        ',Tanpa Perusahaan,x@y.test,,',
        'PT Beta,Siti,siti@beta.test,,',
    ]));

    $batch = ImportBatch::create([
        'file_hash' => hash_file('sha256', $path),
        'filename' => 'test-import.csv',
        'status' => 'pending',
    ]);

    app(LeadImportService::class)->processBatch($batch, $path, 'csv');

    $batch->refresh();

    expect($batch->status)->toBe('completed')
        ->and($batch->total_rows)->toBe(4)
        ->and($batch->success_rows)->toBe(2)
        ->and($batch->duplicate_rows)->toBe(1)
        ->and($batch->failed_rows)->toBe(1)
        ->and(Lead::count())->toBe(2);
});

it('enforces idempotency with a unique file hash', function () {
    $path = writeCsv("company_name,contact_name,email\nPT Gama,Dewi,dewi@gama.test\n");
    $hash = hash_file('sha256', $path);

    ImportBatch::create(['file_hash' => $hash, 'filename' => 'a.csv', 'status' => 'pending']);

    expect(ImportBatch::where('file_hash', $hash)->count())->toBe(1);

    expect(fn () => ImportBatch::create(['file_hash' => $hash, 'filename' => 'b.csv', 'status' => 'pending']))
        ->toThrow(QueryException::class);
});

it('queues the import job on the imports queue', function () {
    Queue::fake();

    ProcessLeadImportJob::dispatch('batch-id', 'imports/file.csv');

    Queue::assertPushedOn('imports', ProcessLeadImportJob::class);
});
