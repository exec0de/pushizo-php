<?php

declare(strict_types=1);

namespace Pushizo\Resources;

use Pushizo\Client;

/**
 * App API anahtarları (`admin` kapsamı). Düz anahtar yalnızca oluşturma ve rotasyon yanıtında bir kez
 * döner; güvenli bir yerde saklayın ve loglamayın.
 */
final class ApiKeys
{
    public function __construct(private readonly Client $client)
    {
    }

    private function base(?string $appId): string
    {
        return $this->client->appPath($appId) . '/api-keys';
    }

    /** @return array<string, mixed> */
    public function list(?string $appId = null): array
    {
        return $this->client->request('GET', $this->base($appId));
    }

    /**
     * @param array{name:string, scopes:array<int,string>, environment?:'live'|'test', expires_at?:?string, ip_allowlist?:array<int,string>} $body
     * @return array<string, mixed>
     */
    public function create(array $body, ?string $appId = null): array
    {
        return $this->client->request('POST', $this->base($appId), [], $body);
    }

    /**
     * @param array{name?:string, scopes?:array<int,string>, ip_allowlist?:array<int,string>, expires_at?:?string} $body
     * @return array<string, mixed>
     */
    public function update(string $keyId, array $body, ?string $appId = null): array
    {
        return $this->client->request('PATCH', $this->base($appId) . '/' . rawurlencode($keyId), [], $body);
    }

    /**
     * Anahtarı iptal eder (geri alınamaz).
     *
     * @return array<string, mixed>
     */
    public function revoke(string $keyId, ?string $appId = null): array
    {
        return $this->client->request('DELETE', $this->base($appId) . '/' . rawurlencode($keyId));
    }

    /**
     * Yeni anahtar değeri üretir; eski değer anında geçersiz olur.
     *
     * @return array<string, mixed>
     */
    public function rotate(string $keyId, ?string $appId = null): array
    {
        return $this->client->request('POST', $this->base($appId) . '/' . rawurlencode($keyId) . '/rotate');
    }
}
