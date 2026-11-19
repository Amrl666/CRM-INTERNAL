<?php

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Companies\Models\Company;
use App\Domain\Contacts\Models\Contact;
use App\Domain\Leads\Actions\CreateLeadAction;
use App\Domain\Leads\Models\Activity;
use App\Domain\Leads\Models\Lead;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('rolls back company contact lead activity and audit when audit recording fails', function () {
    $shouldFail = true;

    DB::listen(function (QueryExecuted $query) use (&$shouldFail) {
        if (
            $shouldFail
            && str_contains(strtolower($query->sql), 'insert into "audit_logs"')
        ) {
            $shouldFail = false;

            throw new RuntimeException('Simulated audit failure.');
        }
    });

    expect(fn () => app(CreateLeadAction::class)->handle([
        'company_name' => 'PT Transaction Test',
        'contact_name' => 'Test Contact',
        'email' => 'transaction@test.example',
        'phone' => '081234567890',
    ]))->toThrow(RuntimeException::class, 'Simulated audit failure.');

    expect(Company::count())->toBe(0)
        ->and(Contact::count())->toBe(0)
        ->and(Lead::count())->toBe(0)
        ->and(Activity::count())->toBe(0)
        ->and(AuditLog::count())->toBe(0);
});
