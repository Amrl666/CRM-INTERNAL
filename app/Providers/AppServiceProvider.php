<?php

namespace App\Providers;

use App\Domain\Communications\Contracts\EmailProvider;
use App\Domain\Communications\Contracts\WhatsAppProvider;
use App\Domain\Communications\Fakes\FakeEmailProvider;
use App\Domain\Communications\Fakes\FakeWhatsAppProvider;
use App\Domain\Communications\Providers\EvolutionClient;
use App\Domain\Communications\Providers\GmailProvider;
use App\Domain\Communications\Services\CommunicationManager;
use App\Domain\Communications\Services\OutboundModeGuard;
use App\Models\User;
use Google\Client;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Client::class, fn () => new Client);

        $this->app->singleton(EmailProvider::class, function () {
            return match (config('communications.email.default')) {
                'fake' => new FakeEmailProvider,
                'gmail' => new GmailProvider(
                    $this->app->make(Client::class)
                ),
                default => throw new \RuntimeException(
                    'Email provider ['.config('communications.email.default').'] belum dikonfigurasi. Gunakan EMAIL_PROVIDER=fake untuk lokal.'
                ),
            };
        });

        $this->app->singleton(WhatsAppProvider::class, function () {
            return match (config('communications.whatsapp.default')) {
                'fake' => new FakeWhatsAppProvider,
                'evolution' => new EvolutionClient,
                default => throw new \RuntimeException(
                    'WhatsApp provider ['.config('communications.whatsapp.default').'] belum dikonfigurasi. Gunakan WHATSAPP_PROVIDER=fake untuk lokal.'
                ),
            };
        });

        $this->app->bind(CommunicationManager::class);
    }

    public function boot(): void
    {
        $this->app->make(OutboundModeGuard::class)->boot();

        // Super Admin melewati seluruh pemeriksaan permission.
        Gate::before(function (User $user) {
            return $user->hasRole('super_admin') ? true : null;
        });
    }
}
