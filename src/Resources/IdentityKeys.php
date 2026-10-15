<?php

declare(strict_types=1);

namespace HeroSignal\Resources;

use HeroSignal\Client;

/**
 * SDK JWT identity verification genel anahtarları (`admin` kapsamı). Yalnızca genel anahtar (JWK veya PEM)
 * gönderilir; özel anahtar sunucunuzda kalır ve `Client::identityToken()` ile belirteç imzalanır.
 */
final class IdentityKeys
{
    public function __construct(private readonly Client $client)
    {
    }

    private function base(?string $appId): string
    {
        return $this->client->appPath($appId) . '/identity-keys';
    }

    /** @return array<string, mixed> */
    public function list(?string $appId = null): array
    {
        return $this->client->request('GET', $this->base($appId));
    }

    /**
     * @param array{kid?:string, label?:string, public_jwk?:array<string,string>, public_key_pem?:string} $body
     * @return array<string, mixed>
     */
    public function create(array $body, ?string $appId = null): array
    {
        return $this->client->request('POST', $this->base($appId), [], $body);
    }

    /**
     * Anahtarı iptal eder: bu anahtarla imzalı belirteçler artık kabul edilmez.
     *
     * @return array<string, mixed>
     */
    public function revoke(string $keyId, ?string $appId = null): array
    {
        return $this->client->request('DELETE', $this->base($appId) . '/' . rawurlencode($keyId));
    }
}
