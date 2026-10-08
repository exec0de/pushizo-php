<?php

declare(strict_types=1);

namespace HeroSignal\Resources;

use HeroSignal\Client;

/**
 * App API anahtarları: /v1/apps/{app}/api-keys… (`admin` kapsamı). Oluşturma ve rotasyonda dönen `key` yalnızca o
 * yanıtta görünür; çağıran anahtarın kapsamından geniş anahtar oluşturulamaz.
 */
final class ApiKeys
{
    public function __construct(private readonly Client $client)
    {
    }

    private function base(?string $appId, ?string $keyId = null): string
    {
        $path = '/v1/apps/' . rawurlencode($this->client->appId($appId)) . '/api-keys';
        return $keyId === null ? $path : $path . '/' . rawurlencode($keyId);
    }

    /** @return array<string, mixed> */
    public function list(?string $appId = null): array
    {
        return $this->client->request('GET', $this->base($appId));
    }

    /**
     * @param array{name:string, scopes:array<int,string>, environment?:string, expires_at?:?string, ip_allowlist?:array<int,string>} $body
     * @return array<string, mixed>
     */
    public function create(array $body, ?string $appId = null): array
    {
        return $this->client->request('POST', $this->base($appId), [], $body);
    }

    /**
     * Ad, kapsam, bitiş veya IP/CIDR izin listesi (`ip_allowlist`; boş dizi kısıtı kaldırır).
     *
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function update(string $keyId, array $body, ?string $appId = null): array
    {
        return $this->client->request('PATCH', $this->base($appId, $keyId), [], $body);
    }

    /** Aynı kimlikle yeni değer üretir; eski değer hemen geçersiz olur (401). @return array<string, mixed> */
    public function rotate(string $keyId, ?string $appId = null): array
    {
        return $this->client->request('POST', $this->base($appId, $keyId) . '/rotate');
    }

    /** @return array<string, mixed> */
    public function delete(string $keyId, ?string $appId = null): array
    {
        return $this->client->request('DELETE', $this->base($appId, $keyId));
    }
}
