<?php

namespace App\Domain\Communications\Services;

use App\Domain\Communications\Models\CommunicationAccount;
use Google\Client as GoogleClient;
use Google\Service\Oauth2;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class GmailOAuthService
{
    private const REQUIRED_READ_SCOPE = 'https://www.googleapis.com/auth/gmail.readonly';

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

        $this->assertReadScopeGranted($token);

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
     * Gmail Inbox requires read access. Google may grant a narrower scope than
     * requested, so never mark the account fully connected without it.
     *
     * @param  array<string, mixed>  $token
     */
    private function assertReadScopeGranted(array $token): void
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

        if (! in_array(self::REQUIRED_READ_SCOPE, $granted, true)) {
            throw new RuntimeException(
                'Izin baca Gmail (gmail.readonly) belum diberikan. Silakan hubungkan ulang dan setujui akses baca Gmail.',
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
        $this->client->setPrompt('consent');
    }
}
