<?php

use App\Domain\Communications\Actions\DispatchGmailSyncAction;
use App\Jobs\ReconcileProposalDeliveriesJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function (): void {
    app(ReconcileProposalDeliveriesJob::class)->dispatch();
})
    ->everyMinute()
    ->name('outreach_reconcile')
    ->withoutOverlapping();

Schedule::call(function (): void {
    app(DispatchGmailSyncAction::class)->handle(respectInterval: true);
})
    ->everyMinute()
    ->name('gmail_inbox_sync')
    ->withoutOverlapping(10)
    ->when(fn (): bool => (bool) config(
        'communications.email.gmail.sync.enabled',
    ));
