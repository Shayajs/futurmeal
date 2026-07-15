<?php

namespace App\Socialite;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\ProviderInterface;
use Laravel\Socialite\Two\User;

class BrightShieldProvider extends AbstractProvider implements ProviderInterface
{
    protected $scopes = ['openid', 'profile', 'email'];

    protected $scopeSeparator = ' ';

    protected function getAuthUrl($state): string
    {
        // URL vue par le navigateur (shield.brightshell.test via NPM / DNS local)
        return $this->buildAuthUrlFromBase($this->publicBaseUrl().'/oauth/authorize', $state);
    }

    /**
     * @param  string|null  $state
     * @return array<string, string>
     */
    protected function getCodeFields($state = null)
    {
        $fields = parent::getCodeFields($state);

        $icon = trim((string) config('services.brightshield.app_icon', ''));
        if ($icon !== '') {
            $fields['app_icon'] = $icon;
        }

        return $fields;
    }

    protected function getTokenUrl(): string
    {
        // Appel serveur-à-serveur depuis PHP (peut être une URL interne Docker)
        return $this->apiBaseUrl().'/oauth/token';
    }

    protected function getUserByToken($token): array
    {
        $response = $this->getHttpClient()->get($this->apiBaseUrl().'/oauth/userinfo', [
            RequestOptions::HEADERS => array_filter([
                'Accept' => 'application/json',
                'Authorization' => 'Bearer '.$token,
                'Host' => $this->apiHostHeader(),
            ]),
        ]);

        return json_decode((string) $response->getBody(), true) ?? [];
    }

    /**
     * @param  string  $code
     * @return array<string, mixed>
     */
    public function getAccessTokenResponse($code)
    {
        $response = $this->getHttpClient()->post($this->getTokenUrl(), [
            RequestOptions::HEADERS => array_filter([
                'Accept' => 'application/json',
                'Host' => $this->apiHostHeader(),
            ]),
            RequestOptions::FORM_PARAMS => $this->getTokenFields($code),
        ]);

        return json_decode((string) $response->getBody(), true);
    }

    protected function mapUserToObject(array $user): User
    {
        $name = $user['name'] ?? trim(($user['given_name'] ?? '').' '.($user['family_name'] ?? ''));

        return (new User)->setRaw($user)->map([
            'id' => $user['sub'] ?? null,
            'nickname' => $user['given_name'] ?? null,
            'name' => $name !== '' ? $name : ($user['email'] ?? 'Utilisateur BrightShell'),
            'email' => $user['email'] ?? null,
            'avatar' => $user['picture'] ?? null,
        ]);
    }

    protected function getHttpClient(): Client
    {
        if ($this->httpClient !== null) {
            return $this->httpClient;
        }

        return $this->httpClient = new Client(array_merge(
            ['timeout' => 15, 'http_errors' => true],
            $this->guzzle,
        ));
    }

    private function publicBaseUrl(): string
    {
        return rtrim((string) config('services.brightshield.base_url'), '/');
    }

    /**
     * Base URL pour token/userinfo depuis le conteneur PHP.
     * En Docker, shield.brightshell.test pointe souvent vers 127.0.0.1 du
     * conteneur — on utilise alors host.docker.internal (ou une IP NPM).
     */
    private function apiBaseUrl(): string
    {
        $internal = trim((string) config('services.brightshield.api_base_url', ''));

        return rtrim($internal !== '' ? $internal : $this->publicBaseUrl(), '/');
    }

    private function apiHostHeader(): ?string
    {
        $explicit = trim((string) config('services.brightshield.api_host_header', ''));
        if ($explicit !== '') {
            return $explicit;
        }

        // Si api_base_url ≠ base publique, forcer le Host virtuel BrightShield
        if ($this->apiBaseUrl() !== $this->publicBaseUrl()) {
            $host = parse_url($this->publicBaseUrl(), PHP_URL_HOST);

            return is_string($host) && $host !== '' ? $host : null;
        }

        return null;
    }
}
