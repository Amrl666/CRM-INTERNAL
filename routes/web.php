<?php

use App\Http\Controllers\GmailOAuthController;
use App\Http\Controllers\ProposalDocumentController;
use App\Livewire\Analytics\Overview;
use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\ResetPassword;
use App\Livewire\Dashboard\Index;
use App\Livewire\Deals\Board;
use App\Livewire\Inbox\Index as InboxIndex;
use App\Livewire\Leads\Import;
use App\Livewire\Leads\Show;
use App\Livewire\Settings\Suppression;
use App\Livewire\Settings\Templates;
use App\Livewire\Tasks\Index as TaskIndex;
use App\Livewire\Users\Form;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Publik: halaman tanpa autentikasi (login, reset password).
| Auth:   seluruh rute lain dilindungi middleware 'auth'.
|
*/

// --- Publik ---------------------------------------------------------------
Route::get('/', fn () => redirect()->route('login'))->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', Login::class)->name('login');
    Route::get('/forgot-password', ForgotPassword::class)->name('password.request');
    Route::get('/reset-password/{token}', ResetPassword::class)->name('password.reset');
});

// --- Dashboard & modul utama ----------------------------------------------
Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', Index::class)
        ->name('dashboard');

    Route::get('/inbox', InboxIndex::class)
        ->middleware('can:viewAny,App\Domain\Communications\Models\Conversation')
        ->name('inbox.index');

    Route::prefix('leads')->name('leads.')->group(function () {
        Route::get('/', App\Livewire\Leads\Index::class)
            ->middleware('can:viewAny,App\Domain\Leads\Models\Lead')
            ->name('index');
        Route::get('/create', App\Livewire\Leads\Form::class)
            ->middleware('can:create,App\Domain\Leads\Models\Lead')
            ->name('create');
        Route::get('/import', Import::class)
            ->middleware('can:import,App\Domain\Leads\Models\Lead')
            ->name('import');
        Route::get('/{lead}', Show::class)->name('show');
        Route::get('/{lead}/edit', App\Livewire\Leads\Form::class)->name('edit');
    });
    Route::prefix('deals')->name('deals.')->group(function () {
        Route::get('/', Board::class)
            ->middleware('can:viewAny,App\Domain\Deals\Models\Deal')
            ->name('index');
        Route::get('/create', App\Livewire\Deals\Form::class)
            ->middleware('can:create,App\Domain\Deals\Models\Deal')
            ->name('create');
        Route::get('/{deal}/edit', App\Livewire\Deals\Form::class)->name('edit');
    });
    Route::prefix('campaigns')->name('campaigns.')->group(function () {
        Route::get('/', App\Livewire\Campaigns\Index::class)
            ->middleware('can:viewAny,App\Domain\Campaigns\Models\Campaign')
            ->name('index');
        Route::get('/create', App\Livewire\Campaigns\Form::class)
            ->middleware('can:create,App\Domain\Campaigns\Models\Campaign')
            ->name('create');
        Route::get('/{campaign}', App\Livewire\Campaigns\Show::class)->name('show');
    });

    Route::prefix('proposals')->name('proposals.')->group(function () {
        Route::get('/', App\Livewire\Proposals\Index::class)
            ->middleware('can:viewAny,App\Domain\Campaigns\Models\Campaign')
            ->name('index');
        Route::get('/create', App\Livewire\Proposals\Form::class)
            ->middleware('can:create,App\Domain\Campaigns\Models\Campaign')
            ->name('create');
        Route::get('/{campaign}/recipients/{recipient}/preview', [ProposalDocumentController::class, 'preview'])->name('recipients.preview');
        Route::get('/{campaign}/recipients/{recipient}/download', [ProposalDocumentController::class, 'download'])->name('recipients.download');
        Route::get('/{campaign}', App\Livewire\Proposals\Show::class)->name('show');
    });
    Route::get('/analytics/overview', Overview::class)
        ->middleware('can:analytics.view')
        ->name('analytics.overview');

    Route::get('/tasks', TaskIndex::class)
        ->middleware('can:tasks.manage')
        ->name('tasks.index');

    // --- Pengaturan & pengguna -------------------------------------------
    Route::get('/settings', App\Livewire\Settings\Index::class)
        ->middleware('can:settings.manage')
        ->name('settings.index');
    Route::get('/settings/suppression', Suppression::class)
        ->middleware('can:settings.manage')
        ->name('settings.suppression');

    Route::get('/settings/templates', Templates::class)
        ->middleware('can:settings.manage')
        ->name('settings.templates');

    Route::get('/settings/gmail/connect', [GmailOAuthController::class, 'connect'])
        ->middleware('can:settings.manage')
        ->name('settings.gmail.connect');
    Route::get('/settings/gmail/callback', [GmailOAuthController::class, 'callback'])
        ->middleware('can:settings.manage')
        ->name('settings.gmail.callback');

    Route::prefix('settings/users')->name('users.')->group(function () {
        Route::get('/', App\Livewire\Users\Index::class)
            ->middleware('can:viewAny,App\Models\User')
            ->name('index');

        Route::get('/create', Form::class)
            ->middleware('can:create,App\Models\User')
            ->name('create');

        Route::get('/{user}/edit', Form::class)
            ->middleware('can:update,user')
            ->name('edit');
    });
});

// --- Logout ---------------------------------------------------------------
Route::post('/logout', function () {
    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect()->route('login');
})->name('logout')->middleware('auth');
