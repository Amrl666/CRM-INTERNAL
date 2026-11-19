<?php

namespace App\Http\Controllers;

use App\Domain\Communications\Services\GmailOAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

final class GmailOAuthController
{
    public function connect(Request $request, GmailOAuthService $oauth): RedirectResponse
    {
        abort_unless($request->user()->can('settings.manage'), 403);

        $state = Str::random(40);
        $request->session()->put('gmail_oauth_state', $state);

        try {
            return redirect()->away($oauth->authorizationUrl($state));
        } catch (Throwable $e) {
            report($e);

            return redirect()
                ->route('settings.index')
                ->withErrors(['gmail' => 'Koneksi Gmail belum dapat dimulai. Periksa konfigurasi OAuth.']);
        }
    }

    public function callback(Request $request, GmailOAuthService $oauth): RedirectResponse
    {
        abort_unless($request->user()->can('settings.manage'), 403);

        $state = (string) $request->session()->pull('gmail_oauth_state');

        if ($state === '' || ! hash_equals($state, (string) $request->query('state'))) {
            return redirect()
                ->route('settings.index')
                ->withErrors(['gmail' => 'State OAuth tidak valid. Silakan ulangi koneksi Gmail.']);
        }

        if ($request->query('error') !== null) {
            return redirect()
                ->route('settings.index')
                ->withErrors(['gmail' => 'Otorisasi Gmail dibatalkan.']);
        }

        $code = $request->query('code');

        if (! is_string($code) || trim($code) === '') {
            return redirect()
                ->route('settings.index')
                ->withErrors(['gmail' => 'Authorization code Gmail tidak tersedia. Silakan ulangi koneksi.']);
        }

        try {
            $account = $oauth->handleCallback($code);
        } catch (Throwable $e) {
            Log::error('Gmail OAuth callback failed.', [
                'exception' => $e::class,
            ]);

            return redirect()
                ->route('settings.index')
                ->withErrors(['gmail' => 'Gmail gagal dihubungkan. Periksa log aplikasi untuk detailnya.']);
        }

        return redirect()
            ->route('settings.index')
            ->with('status', 'Gmail terhubung: '.$account->account_email);
    }
}
