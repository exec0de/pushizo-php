<?php

declare(strict_types=1);

namespace HeroSignal\Resources;

use HeroSignal\Client;

/** Templates API: /v1/apps/{app}/templates… (okuma `analytics:read`, yazma `admin`). */
final class Templates
{
    public function __construct(private readonly Client $client)
    {
    }

    private function base(?string $appId, ?string $templateId = null): string
    {
        $path = '/v1/apps/' . rawurlencode($this->client->appId($appId)) . '/templates';
        return $templateId === null ? $path : $path . '/' . rawurlencode($templateId);
    }

    /**
     * @param array{limit?:int, cursor?:string, q?:string, channel?:string} $params
     * @return array<string, mixed>
     */
    public function list(array $params = [], ?string $appId = null): array
    {
        return $this->client->request('GET', $this->base($appId), $params);
    }

    /** @return array<string, mixed> */
    public function get(string $templateId, ?string $appId = null): array
    {
        return $this->client->request('GET', $this->base($appId, $templateId));
    }

    /**
     * @param array{name:string, channel?:?string, tags?:array<int,string>, content:array<string,mixed>} $body
     * @return array<string, mixed>
     */
    public function create(array $body, ?string $appId = null): array
    {
        return $this->client->request('POST', $this->base($appId), [], $body);
    }

    /**
     * `content` verilirse yeni sürüm açılır.
     *
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function update(string $templateId, array $body, ?string $appId = null): array
    {
        return $this->client->request('PATCH', $this->base($appId, $templateId), [], $body);
    }

    /** @return array<string, mixed> */
    public function delete(string $templateId, ?string $appId = null): array
    {
        return $this->client->request('DELETE', $this->base($appId, $templateId));
    }

    /** @return array<string, mixed> */
    public function versions(string $templateId, ?string $appId = null): array
    {
        return $this->client->request('GET', $this->base($appId, $templateId) . '/versions');
    }

    /**
     * Örnek verilerle önizleme (`channel` varsayılanı ios_push).
     *
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function render(string $templateId, array $body = [], ?string $appId = null): array
    {
        return $this->client->request('POST', $this->base($appId, $templateId) . '/render', [], (object) $body);
    }

    /**
     * Aynı organizasyondaki başka bir app'e kopyalar (org anahtarı gerekir); hedefte sürüm 1 olur.
     *
     * @return array<string, mixed>
     */
    public function copy(string $templateId, string $targetAppId, ?string $name = null, ?string $appId = null): array
    {
        $body = ['target_app_id' => $targetAppId];
        if ($name !== null) {
            $body['name'] = $name;
        }
        return $this->client->request('POST', $this->base($appId, $templateId) . '/copy', [], $body);
    }
}
