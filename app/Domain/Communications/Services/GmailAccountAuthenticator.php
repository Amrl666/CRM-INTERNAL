<?php

namespace App\Domain\Communications\Services;

use App\Domain\Communications\Models\CommunicationAccount;
use Google\Client as GoogleClient;
use Google\Service\Gmail;
use Throwable;

final class GmailAccountAuthenticator
{
    public function __construct(
        private readonly GoogleClient $client,
    ) {}

    /**
     * Select the primary connected Gmail account consistently.
     */
    public function connectedAccount(): ?CommunicationAccount
    {
        $primaryEmail = config('communications.email.gmail.primary_email');

        if (blank($primaryEmail)) {
            return null;
        }

        return CommunicationAccount::query()
            ->where('provider', 'gmail')
            ->where('connection_status', 'connected')
            ->whereRaw('LOWER(account_email) = ?', [strtolower($primaryEmail)])
            ->first();
    }

    /**
     * Return an authenticated Google client for the selected account.
     *
     * Transient refresh failures do not disconnect the account.
     * Only Google's explicit invalid_grant response does.
     */
    public function clientFor(CommunicationAccount $account): GoogleClient
    {
        $this->configureClient();
        $this->assertPrimaryAccount($account);
        $this->assertConnected($account);

        $accessToken = $account->access_token;

        $this->client->setAccessToken(
            is_array($accessToken) ? $accessToken : [
                'access_token' => $accessToken,
            ],
        );

        $expired = $account->token_expires_at === null
            || now()->greaterThanOrEqualTo($account->token_expires_at);

        if (! $expired && filled($accessToken)) {
            return $this->client;
        }

        return $this->refreshToken($account);
    }

    /**
     * Force a refresh even when the stored expiry still looks valid.
     *
     * This is used after Gmail answers HTTP 401, which can happen before the
     * local token expiry timestamp has passed.
     */
    public function refresh(CommunicationAccount $account): GoogleClient
    {
        $this->configureClient();
        $this->assertPrimaryAccount($account);
        $this->assertConnected($account);

        return $this->refreshToken($account);
    }

    public function gmailServiceFor(CommunicationAccount $account): Gmail
    {
        return new Gmail($this->clientFor($account));
    }

    private function refreshToken(CommunicationAccount $account): GoogleClient
    {
        if (blank($account->refresh_token)) {
            $account->update(['connection_status' => 'disconnected']);

            throw new GmailAuthenticationException(
                'Refresh token Gmail tidak tersedia. Hubungkan ulang akun Gmail utama.',
                true,
                GmailAuthenticationException::REASON_MISSING_REFRESH_TOKEN,
            );
        }

        try {
            $token = $this->client->fetchAccessTokenWithRefreshToken(
                $account->refresh_token,
            );
        } catch (Throwable $e) {
            // Guzzle may raise the OAuth error before Google's client can
            // return it as a token array. Treat an explicit invalid_grant
            // response as a permanent reconnect requirement in both cases.
            if ($this->oauthErrorCode($e) === 'invalid_grant') {
                $account->update(['connection_status' => 'disconnected']);

                throw new GmailAuthenticationException(
                    'Refresh token Gmail sudah tidak berlaku. Hubungkan ulang akun.',
                    true,
                    GmailAuthenticationException::REASON_INVALID_GRANT,
                );
            }

            throw new GmailAuthenticationException(
                'Tidak dapat menghubungi layanan autentikasi Google.',
                false,
                GmailAuthenticationException::REASON_TRANSIENT,
            );
        }

        if (isset($token['error'])) {
            $invalidGrant = $token['error'] === 'invalid_grant';

            if ($invalidGrant) {
                $account->update(['connection_status' => 'disconnected']);
            }

            throw new GmailAuthenticationException(
                $invalidGrant
                    ? 'Refresh token Gmail sudah tidak berlaku. Hubungkan ulang akun.'
                    : 'Google menolak pembaruan access token Gmail.',
                $invalidGrant,
                $invalidGrant
                    ? GmailAuthenticationException::REASON_INVALID_GRANT
                    : GmailAuthenticationException::REASON_TRANSIENT,
            );
        }

        if (blank($token['access_token'] ?? null)) {
            throw new GmailAuthenticationException(
                'Google tidak mengembalikan access token baru.',
                false,
                GmailAuthenticationException::REASON_TRANSIENT,
            );
        }

        $updates = [
            'access_token' => $token['access_token'],
            'token_expires_at' => isset($token['expires_in'])
                ? now()->addSeconds((int) $token['expires_in'])
                : null,
        ];

        // Google may omit refresh_token during token refresh.
        // Never overwrite an existing refresh token with null.
        if (filled($token['refresh_token'] ?? null)) {
            $updates['refresh_token'] = $token['refresh_token'];
        }

        $account->update($updates);

        $this->client->setAccessToken($token);

        return $this->client;
    }

    private function oauthErrorCode(Throwable $exception): ?string
    {
        if (method_exists($exception, 'getResponse')) {
            $response = $exception->getResponse();

            if ($response !== null) {
                $body = json_decode((string) $response->getBody(), true);

                if (
                    is_array($body)
                    && isset($body['error'])
                    && is_string($body['error'])
                ) {
                    return $body['error'];
                }
            }
        }

        return str_contains($exception->getMessage(), 'invalid_grant')
            ? 'invalid_grant'
            : null;
    }

    private function assertPrimaryAccount(CommunicationAccount $account): void
    {
        $primaryEmail = config('communications.email.gmail.primary_email');

        if (
            blank($primaryEmail)
            || strtolower((string) $account->account_email) !== strtolower($primaryEmail)
        ) {
            throw new GmailAuthenticationException(
                'Akun Gmail bukan akun utama Prospectra.',
                false,
                GmailAuthenticationException::REASON_NOT_PRIMARY,
            );
        }
    }

    private function assertConnected(CommunicationAccount $account): void
    {
        if (! $account->isConnected()) {
            throw new GmailAuthenticationException(
                'Akun Gmail tidak terhubung.',
                true,
                GmailAuthenticationException::REASON_DISCONNECTED,
            );
        }
    }

    private function configureClient(): void
    {
        $config = config('communications.email.gmail');

        if (
            blank($config['client_id'] ?? null)
            || blank($config['client_secret'] ?? null)
        ) {
            throw new GmailAuthenticationException(
                'Konfigurasi OAuth Gmail belum lengkap.',
                false,
                GmailAuthenticationException::REASON_CONFIGURATION,
            );
        }

        $this->client->setClientId($config['client_id']);
        $this->client->setClientSecret($config['client_secret']);

        if (filled($config['redirect_uri'] ?? null)) {
            $this->client->setRedirectUri($config['redirect_uri']);
        }
    }
}
