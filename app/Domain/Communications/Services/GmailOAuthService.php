<?php

namespace App\Domain\Communications\Services;

use App\Domain\Communications\Models\CommunicationAccount;
use Google\Client as GoogleClient;
use Google\Service\Oauth2;

final class GmailOAuthService
{
    public function __construct(private readonly GoogleClient $client) {}

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

        if (isset($token['error'])) {
            throw new \RuntimeException('Gagal menukar kode OAuth Gmail: '.$token['error']);
        }

        $this->client->setAccessToken($token);

        $profile = (new Oauth2($this->client))->userinfo->get();

        return CommunicationAccount::updateOrCreate(
            ['provider' => 'gmail', 'account_email' => $profile->getEmail()],
            [
                'display_name' => $profile->getName(),
                'access_token' => $token['access_token'] ?? null,
                'refresh_token' => $token['refresh_token'] ?? null,
                'token_expires_at' => isset($token['expires_in']) ? now()->addSeconds((int) $token['expires_in']) : null,
                'connection_status' => 'connected',
            ]
        );
    }

    public function gmailClientFor(CommunicationAccount $account): GoogleClient
    {
        $this->configure();
        $this->client->setAccessToken($account->access_token);

        if ($this->client->isAccessTokenExpired() && $account->refresh_token !== null) {
            $token = $this->client->fetchAccessTokenWithRefreshToken($account->refresh_token);
            $account->update([
                'access_token' => $token['access_token'] ?? $account->access_token,
                'token_expires_at' => isset($token['expires_in']) ? now()->addSeconds((int) $token['expires_in']) : null,
            ]);
        }

        return $this->client;
    }

    private function configure(): void
    {
        $config = config('communications.email.gmail');

        if (empty($config['client_id']) || empty($config['client_secret'])) {
            throw new \RuntimeException('GOOGLE_CLIENT_ID / GOOGLE_CLIENT_SECRET belum diisi.');
        }

        $this->client->setClientId($config['client_id']);
        $this->client->setClientSecret($config['client_secret']);
        $this->client->setRedirectUri($config['redirect_uri']);
        $this->client->addScope($config['scopes']);
        $this->client->setAccessType('offline');
        $this->client->setPrompt('consent');
    }
}
