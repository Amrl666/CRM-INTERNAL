<?php

namespace App\Livewire\Settings;

use App\Domain\Communications\Contracts\EmailProvider;
use App\Domain\Communications\Contracts\WhatsAppProvider;
use App\Domain\Communications\Models\CommunicationAccount;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.sidebar', ['heading' => 'Pengaturan'])]
class Index extends Component
{
    public function mount(): void
    {
        $this->authorize('settings.manage');
    }

    public function render()
    {
        return view('livewire.settings.index', [
            'emailProvider' => app(EmailProvider::class)->getProviderName(),
            'whatsappProvider' => app(WhatsAppProvider::class)->getProviderName(),
            'outboundMode' => config('communications.whatsapp.outbound_mode'),
            'gmailAccounts' => CommunicationAccount::where('provider', 'gmail')->get(),
        ]);
    }
}
