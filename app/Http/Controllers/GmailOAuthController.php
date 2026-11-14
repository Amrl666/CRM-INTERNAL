<?php

namespace App\Http\Controllers;

use App\Domain\Communications\Services\GmailOAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class GmailOAuthController
{
    public function connect(Request $request, GmailOAuthService $oauth): RedirectResponse
    {
        abort_unless($request->user()->can('settings.manage'), 403);

        $state = Str::random(40);
        $request->session()->put('gmail_oauth_state', $state);

        try {
            return redirect()->away($oauth->authorizationUrl($state));
        } catch (\RuntimeException $e) {
            return redirect()->route('settings.index')->withErrors(['gmail' => $e->getMessage()]);
        }
    }

    public function callback(Request $request, GmailOAuthService $oauth): RedirectResponse
    {
        abort_unless($request->user()->can('settings.manage'), 403);

        $state = (string) $request->session()->pull('gmail_oauth_state');

        if ($state === '' || ! hash_equals($state, (string) $request->query('state'))) {
            return redirect()->route('settings.index')->withErrors(['gmail' => 'State OAuth tidak valid.']);
        }

        if ($request->query('error') !== null) {
            return redirect()->route('settings.index')->withErrors(['gmail' => 'Otorisasi Gmail dibatalkan.']);
        }

        try {
            $account = $oauth->handleCallback((string) $request->query('code'));
        } catch (\Throwable $e) {
            return redirect()->route('settings.index')->withErrors(['gmail' => $e->getMessage()]);
        }

        return redirect()->route('settings.index')->with('status', 'Gmail terhubung: '.$account->account_email);
    }
}
