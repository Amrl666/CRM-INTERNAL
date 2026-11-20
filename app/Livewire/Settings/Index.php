<?php

namespace App\Livewire\Settings;

use App\Domain\Communications\Contracts\EmailProvider;
use App\Domain\Communications\Contracts\ProposalEmailSender;
use App\Domain\Communications\Contracts\WhatsAppProvider;
use App\Domain\Communications\Models\CommunicationAccount;
use App\Domain\Communications\Services\GmailOAuthService;
use App\Domain\Communications\Services\GmailSyncStatus;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.sidebar', ['heading' => 'Pengaturan'])]
class Index extends Component
{
    public string $gmailFeedback = '';

    public string $gmailFeedbackType = 'info';

    public function mount(): void
    {
        $this->authorize('settings.manage');
    }

    public function disconnectGmail(
        string $accountId,
        GmailOAuthService $oauth,
        GmailSyncStatus $status,
    ): void {
        $this->authorize('settings.manage');

        // Prospectra uses a single Gmail address. Never expose or act on any
        // other Gmail account from this screen.
        $account = $this->primaryGmailAccountQuery()
            ->whereKey($accountId)
            ->firstOrFail();

        $revoked = $oauth->disconnect($account);

        // Clear leftover sync markers so a crashed or queued run cannot keep
        // the account marked as running after it has been disconnected.
        $status->reset($account->id);

        $this->gmailFeedback = $revoked
            ? 'Gmail '.$account->account_email.' telah diputuskan dari aplikasi.'
            : 'Gmail '.$account->account_email.' telah diputuskan dari aplikasi. Pencabutan akses ke Google gagal, tetapi token lokal sudah dihapus.';
        $this->gmailFeedbackType = $revoked ? 'success' : 'warning';
    }

    public function render()
    {
        $proposalSender = app(ProposalEmailSender::class);

        return view('livewire.settings.index', [
            'emailProvider' => app(EmailProvider::class)->getProviderName(),
            'proposalEmailProvider' => $proposalSender->getProviderName(),
            'proposalSenderEmail' => $proposalSender->getSenderEmail(),
            'whatsappProvider' => app(WhatsAppProvider::class)->getProviderName(),
            'outboundMode' => config('communications.whatsapp.outbound_mode'),
            'gmailAccounts' => $this->primaryGmailAccountQuery()->get(),
        ]);
    }

    private function primaryGmailAccountQuery(): Builder
    {
        $email = mb_strtolower(trim((string) config(
            'communications.email.gmail.primary_email'
        )));

        return CommunicationAccount::query()
            ->where('provider', 'gmail')
            ->whereRaw('LOWER(account_email) = ?', [$email]);
    }
}
