<?php

namespace App\Providers;

use App\Domain\Campaigns\Contracts\ProposalPdfRenderer;
use App\Domain\Campaigns\Services\FakePdfRenderer;
use App\Domain\Campaigns\Services\PlaywrightPdfRenderer;
use App\Domain\Communications\Contracts\EmailProvider;
use App\Domain\Communications\Contracts\ProposalEmailSender;
use App\Domain\Communications\Contracts\ProposalWhatsAppSender;
use App\Domain\Communications\Contracts\WhatsAppProvider;
use App\Domain\Communications\Fakes\FakeEmailProvider;
use App\Domain\Communications\Fakes\FakeProposalEmailSender;
use App\Domain\Communications\Fakes\FakeProposalWhatsAppSender;
use App\Domain\Communications\Fakes\FakeWhatsAppProvider;
use App\Domain\Communications\Listeners\ReleaseGmailSyncOnUniqueJobSkipped;
use App\Domain\Communications\Providers\EvolutionClient;
use App\Domain\Communications\Providers\EvolutionProposalSender;
use App\Domain\Communications\Providers\GmailProposalSender;
use App\Domain\Communications\Providers\GmailProvider;
use App\Domain\Communications\Services\CommunicationManager;
use App\Domain\Communications\Services\GmailAccountAuthenticator;
use App\Domain\Communications\Services\OutboundModeGuard;
use App\Models\User;
use Google\Client;
use Illuminate\Queue\Events\UniqueJobSkipped;
use Illuminate\Support\Facades\Event;
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
                    $this->app->make(GmailAccountAuthenticator::class)
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

        $this->app->bind(ProposalPdfRenderer::class, function () {
            return match (config('proposals.pdf_renderer')) {
                'fake' => new FakePdfRenderer,
                'playwright' => new PlaywrightPdfRenderer,
                default => throw new \RuntimeException(
                    'Renderer PDF proposal ['.config('proposals.pdf_renderer').'] tidak dikenal.'
                ),
            };
        });

        $this->app->singleton(ProposalEmailSender::class, function () {
            return config('communications.email.default') === 'gmail'
                ? new GmailProposalSender($this->app->make(GmailAccountAuthenticator::class))
                : new FakeProposalEmailSender;
        });

        $this->app->singleton(ProposalWhatsAppSender::class, function () {
            return config('communications.whatsapp.default') === 'evolution'
                ? new EvolutionProposalSender
                : new FakeProposalWhatsAppSender;
        });
    }

    public function boot(): void
    {
        Event::listen(
            UniqueJobSkipped::class,
            ReleaseGmailSyncOnUniqueJobSkipped::class,
        );

        $this->app->make(OutboundModeGuard::class)->boot();

        // Super Admin melewati seluruh pemeriksaan permission.
        Gate::before(function (User $user) {
            return $user->hasRole('super_admin') ? true : null;
        });
    }
}
