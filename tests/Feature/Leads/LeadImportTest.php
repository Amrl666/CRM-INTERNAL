<?php

use App\Domain\Consent\Enums\Channel;
use App\Domain\Consent\Enums\ConsentStatus;
use App\Domain\Consent\Models\ConsentRecord;
use App\Domain\Leads\Actions\CreateLeadAction;
use App\Domain\Leads\Models\ImportBatch;
use App\Domain\Leads\Models\Lead;
use App\Domain\Leads\Services\LeadImportService;
use App\Jobs\ProcessLeadImportJob;
use App\Livewire\Leads\Import;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

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

it('grants communication consent for imported leads with valid addresses', function () {
    $path = writeCsv(implode("\n", [
        'company_name,contact_name,email,phone',
        'PT Import Consent,Budi,budi@import.test,081234567890',
        'PT Import Email,Siti,siti@import.test,',
        'PT Import Phone,Andi,,081298765432',
    ]));

    $batch = ImportBatch::create([
        'file_hash' => hash_file('sha256', $path),
        'filename' => 'consent-import.csv',
        'status' => 'pending',
    ]);

    app(LeadImportService::class)->processBatch($batch, $path, 'csv');

    expect($batch->fresh()->success_rows)->toBe(3)
        ->and(ConsentRecord::where('status', ConsentStatus::Granted)->count())->toBe(4)
        ->and(ConsentRecord::where('source', 'lead import')->count())->toBe(4)
        ->and(ConsentRecord::where('channel', Channel::Email)->count())->toBe(2)
        ->and(ConsentRecord::where('channel', Channel::WhatsApp)->count())->toBe(2);
});

it('detects duplicate email or phone independently across companies within the same file', function () {
    $path = writeCsv(implode("\n", [
        'company_name,contact_name,email,phone',
        'PT Alfa,Budi,shared@company.test,081234567890',
        'PT Beta,Siti,shared@company.test,081298765432',
        'PT Gamma,Andi,other@company.test,081234567890',
        'PT Delta,Rina,unique@company.test,081277777777',
        'PT Epsilon,Dewi,another@company.test,081277777777',
    ]));

    $batch = ImportBatch::create([
        'file_hash' => hash_file('sha256', $path),
        'filename' => 'global-duplicates.csv',
        'status' => 'pending',
    ]);

    app(LeadImportService::class)->processBatch($batch, $path, 'csv');

    $batch->refresh();

    expect($batch->status)->toBe('completed')
        ->and($batch->total_rows)->toBe(5)
        ->and($batch->success_rows)->toBe(2)
        ->and($batch->duplicate_rows)->toBe(3)
        ->and($batch->failed_rows)->toBe(0)
        ->and(Lead::count())->toBe(2);
});

it('detects existing email or phone globally across different companies', function () {
    app(CreateLeadAction::class)->handle([
        'company_name' => 'PT Existing',
        'contact_name' => 'Existing Contact',
        'email' => 'existing@company.test',
        'phone' => '081234567890',
    ]);

    $path = writeCsv(implode("\n", [
        'company_name,contact_name,email,phone',
        'PT New A,Contact A,existing@company.test,081299999999',
        'PT New B,Contact B,new@company.test,081234567890',
        'PT New C,Contact C,unique@company.test,081277777777',
    ]));

    $batch = ImportBatch::create([
        'file_hash' => hash_file('sha256', $path),
        'filename' => 'database-duplicates.csv',
        'status' => 'pending',
    ]);

    app(LeadImportService::class)->processBatch($batch, $path, 'csv');

    $batch->refresh();

    expect($batch->status)->toBe('completed')
        ->and($batch->total_rows)->toBe(3)
        ->and($batch->success_rows)->toBe(1)
        ->and($batch->duplicate_rows)->toBe(2)
        ->and($batch->failed_rows)->toBe(0)
        ->and(Lead::count())->toBe(2);
});

it('rejects invalid phone numbers instead of creating contacts without valid identifiers', function () {
    $path = writeCsv(implode("\n", [
        'company_name,contact_name,email,phone',
        'PT Invalid A,Contact A,,not-a-phone',
        'PT Invalid B,Contact B,valid@email.test,not-a-phone',
        'PT Valid,Contact C,valid@company.test,081234567890',
    ]));

    $batch = ImportBatch::create([
        'file_hash' => hash_file('sha256', $path),
        'filename' => 'invalid-phones.csv',
        'status' => 'pending',
    ]);

    app(LeadImportService::class)->processBatch($batch, $path, 'csv');

    $batch->refresh();

    expect($batch->status)->toBe('completed')
        ->and($batch->total_rows)->toBe(3)
        ->and($batch->success_rows)->toBe(1)
        ->and($batch->failed_rows)->toBe(2)
        ->and($batch->duplicate_rows)->toBe(0)
        ->and(Lead::count())->toBe(1);
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

it('uploads a valid csv, shows a preview and queues the import on confirmation', function () {
    Queue::fake();
    Storage::fake('local');

    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $csv = implode("\n", [
        'company_name,contact_name,email,phone,source',
        'PT Delta,Ani,ani@delta.test,081234567890,Referral',
    ]);

    $file = UploadedFile::fake()->createWithContent('leads.csv', $csv);

    $component = Livewire::actingAs($admin)->test(Import::class)
        ->set('file', $file)
        ->call('uploadFile')
        ->assertHasNoErrors()
        ->assertSet('duplicateFile', false)
        ->assertSee('PT Delta');

    $batch = ImportBatch::first();

    expect($batch)->not->toBeNull()
        ->and($batch->status)->toBe('ready')
        ->and($batch->created_by)->toBe($admin->id)
        ->and($batch->filename)->toBe('leads.csv');

    $component->call('confirm')->assertHasNoErrors();

    Queue::assertPushedOn('imports', ProcessLeadImportJob::class);
    expect($batch->fresh()->status)->toBe('queued');
});

it('warns when the same file is uploaded twice', function () {
    Storage::fake('local');

    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $csv = "company_name,contact_name,email\nPT Echo,Rina,rina@echo.test\n";

    Livewire::actingAs($admin)->test(Import::class)
        ->set('file', UploadedFile::fake()->createWithContent('leads.csv', $csv))
        ->call('uploadFile')
        ->assertHasNoErrors();

    Livewire::actingAs($admin)->test(Import::class)
        ->set('file', UploadedFile::fake()->createWithContent('leads.csv', $csv))
        ->call('uploadFile')
        ->assertHasNoErrors()
        ->assertSet('duplicateFile', true);

    expect(ImportBatch::count())->toBe(1);
});

it('does not let another user confirm a batch they do not own', function () {
    Queue::fake();
    Storage::fake('local');

    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $otherAdmin = User::factory()->create();
    $otherAdmin->assignRole('super_admin');

    Livewire::actingAs($admin)->test(Import::class)
        ->set('file', UploadedFile::fake()->createWithContent('leads.csv', "company_name,contact_name,email\nPT Foxtrot,Tono,tono@foxtrot.test\n"))
        ->call('uploadFile')
        ->assertHasNoErrors();

    $batch = ImportBatch::firstOrFail();

    Livewire::actingAs($otherAdmin)->test(Import::class)
        ->set('batchId', $batch->id)
        ->call('confirm')
        ->assertHasErrors('file');

    Queue::assertNothingPushed();
    expect($batch->fresh()->status)->toBe('ready');
});

it('forbids users without leads.import from opening the import page', function () {
    $viewer = User::factory()->create();
    $viewer->assignRole('viewer');

    $this->actingAs($viewer)
        ->get('/leads/import')
        ->assertForbidden();
});

it('retries a failed import batch and dispatches the job', function () {
    Queue::fake();
    Storage::fake('local');

    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $csv = "company_name,contact_name,email\nPT Retry,Budi,retry@example.test\n";

    Storage::disk('local')->put('imports/retry.csv', $csv);

    $batch = ImportBatch::create([
        'file_hash' => hash('sha256', $csv),
        'filename' => 'retry.csv',
        'status' => 'failed',
        'created_by' => $admin->id,
    ]);

    Livewire::actingAs($admin)->test(Import::class)
        ->set('batchId', $batch->id)
        ->call('retry')
        ->assertHasNoErrors();

    expect($batch->fresh()->status)->toBe('queued');

    Queue::assertPushedOn('imports', ProcessLeadImportJob::class);
});

it('does not retry a processing batch while its redis lock is held', function () {
    Queue::fake();
    Storage::fake('local');

    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $batch = ImportBatch::create([
        'file_hash' => hash('sha256', 'active-batch'),
        'filename' => 'active.csv',
        'status' => 'processing',
        'created_by' => $admin->id,
    ]);

    $lock = Cache::lock("lead-import:batch:{$batch->id}", 660);

    expect($lock->get())->toBeTrue();

    try {
        Livewire::actingAs($admin)->test(Import::class)
            ->set('batchId', $batch->id)
            ->call('retry')
            ->assertHasErrors('file');

        expect($batch->fresh()->status)->toBe('processing');

        Queue::assertNothingPushed();
    } finally {
        $lock->release();
    }
});

it('does not retry a failed batch when its source file is missing', function () {
    Queue::fake();
    Storage::fake('local');

    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $batch = ImportBatch::create([
        'file_hash' => hash('sha256', 'missing-source'),
        'filename' => 'missing.csv',
        'status' => 'failed',
        'created_by' => $admin->id,
    ]);

    Livewire::actingAs($admin)->test(Import::class)
        ->set('batchId', $batch->id)
        ->call('retry')
        ->assertHasErrors('file');

    expect($batch->fresh()->status)->toBe('failed');

    Queue::assertNothingPushed();
});

it('does not retry a completed import batch', function () {
    Queue::fake();
    Storage::fake('local');

    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $batch = ImportBatch::create([
        'file_hash' => hash('sha256', 'completed-batch'),
        'filename' => 'completed.csv',
        'status' => 'completed',
        'created_by' => $admin->id,
    ]);

    Livewire::actingAs($admin)->test(Import::class)
        ->set('batchId', $batch->id)
        ->call('retry')
        ->assertHasErrors('file');

    expect($batch->fresh()->status)->toBe('completed');

    Queue::assertNothingPushed();
});
