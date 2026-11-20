<?php

namespace App\Domain\Communications\Services;

use App\Domain\Communications\Models\CommunicationAccount;
use Google\Client as GoogleClient;
use Google\Service\Oauth2;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

final class GmailOAuthService
{
    private const REQUIRED_SCOPES = [
        'https://www.googleapis.com/auth/gmail.readonly',
        'https://www.googleapis.com/auth/gmail.send',
    ];

    public function __construct(
        private readonly GoogleClient $client,
    ) {}

    public function authorizationUrl(string $state): string
    {
        $this->configure();

        $this->client->setState($state);

        return $this->client->createAuthUrl();
    }

    public function handleCallback(string $code): CommunicationAccount
    {
        $this->configure();

        $token = $this->client->fetchAccessTokenWithAuthCode($code);

        if (isset($token['error']) || blank($token['access_token'] ?? null)) {
            throw new RuntimeException('Google tidak berhasil memberikan access token Gmail.');
        }

        $this->assertRequiredScopesGranted($token);

        $this->client->setAccessToken($token);

        $profile = (new Oauth2($this->client))->userinfo->get();
        $email = $profile->getEmail();

        if (blank($email) || $profile->getVerifiedEmail() !== true) {
            throw new RuntimeException('Alamat email Google tidak tersedia atau belum terverifikasi.');
        }

        $primaryEmail = config('communications.email.gmail.primary_email');

        if (blank($primaryEmail) || strtolower($email) !== strtolower($primaryEmail)) {
            throw new RuntimeException('Akun Google ini bukan akun Gmail utama Prospectra.');
        }

        $account = CommunicationAccount::query()
            ->where('provider', 'gmail')
            ->whereRaw('LOWER(account_email) = ?', [strtolower($email)])
            ->first();

        $refreshToken = $token['refresh_token']
            ?? $account?->refresh_token;

        if (blank($refreshToken)) {
            throw new RuntimeException(
                'Google tidak memberikan refresh token. Silakan ulangi koneksi Gmail dan pastikan akses offline diberikan.',
            );
        }

        if (! $account) {
            $account = new CommunicationAccount([
                'provider' => 'gmail',
                'account_email' => $email,
            ]);
        }

        DB::transaction(function () use ($account, $token, $refreshToken, $profile) {
            $account->fill([
                'display_name' => $profile->getName(),
                'access_token' => $token['access_token'],
                'refresh_token' => $refreshToken,
                'token_expires_at' => isset($token['expires_in'])
                    ? now()->addSeconds((int) $token['expires_in'])
                    : null,
                'connection_status' => 'connected',
            ]);

            $account->save();
        });

        return $account;
    }

    /**
     * Disconnect a Gmail account from this application.
     *
     * Local credentials are always removed. Google-side revocation is
     * best-effort: a network or configuration failure must not leave the
     * account connected locally. The account row, synced messages, and the
     * history cursor are deliberately preserved.
     */
    public function disconnect(CommunicationAccount $account): bool
    {
        if ($account->provider !== 'gmail') {
            throw new RuntimeException('Akun ini bukan akun Gmail.');
        }

        $token = filled($account->refresh_token)
            ? (string) $account->refresh_token
            : (string) $account->access_token;

        DB::transaction(function () use ($account): void {
            $account->forceFill([
                'access_token' => null,
                'refresh_token' => null,
                'token_expires_at' => null,
                'connection_status' => 'disconnected',
            ])->save();
        });

        if ($token === '') {
            return false;
        }

        try {
            $this->configure();
            $revoked = $this->client->revokeToken($token);

            if ($revoked !== true) {
                Log::warning('Google did not confirm Gmail token revocation; local credentials were still removed.', [
                    'account_id' => $account->id,
                ]);

                return false;
            }

            return true;
        } catch (Throwable $e) {
            Log::warning('Gmail token revocation failed; local credentials were still removed.', [
                'account_id' => $account->id,
                'exception' => $e::class,
            ]);

            return false;
        }
    }

    /**
     * Gmail Inbox and proposal delivery require read and send access.
     *
     * @param  array<string, mixed>  $token
     */
    private function assertRequiredScopesGranted(array $token): void
    {
        $scope = $token['scope']
            ?? $this->client->getOAuth2Service()->getGrantedScope();

        if (is_string($scope)) {
            $granted = preg_split('/\s+/', trim($scope)) ?: [];
        } elseif (is_array($scope)) {
            $granted = $scope;
        } else {
            $granted = [];
        }

        $granted = array_values(array_filter(
            $granted,
            fn ($value): bool => is_string($value) && $value !== '',
        ));

        $missing = array_values(array_diff(self::REQUIRED_SCOPES, $granted));

        if ($missing !== []) {
            Log::warning('Gmail OAuth missing required scopes.', [
                'missing_scopes' => $missing,
            ]);

            throw new RuntimeException(
                'Izin Gmail belum lengkap. Prospectra membutuhkan akses Gmail membaca dan mengirim. Hubungkan ulang dan setujui seluruh izin.',
            );
        }
    }

    private function configure(): void
    {
        $config = config('communications.email.gmail');

        if (
            blank($config['client_id'] ?? null)
            || blank($config['client_secret'] ?? null)
            || blank($config['redirect_uri'] ?? null)
        ) {
            throw new RuntimeException('Konfigurasi OAuth Gmail belum lengkap.');
        }

        $this->client->setClientId($config['client_id']);
        $this->client->setClientSecret($config['client_secret']);
        $this->client->setRedirectUri($config['redirect_uri']);
        $this->client->setScopes($config['scopes']);
        $this->client->setAccessType('offline');
        $this->client->setPrompt('consent select_account');
    }
}
