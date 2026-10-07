<?php

declare(strict_types=1);

namespace HeroSignal\Resources;

use HeroSignal\Client;

/** Şablonlar: sürümlü içerik, render önizlemesi ve başka app'e kopyalama. */
final class Templates
{
    public function __construct(private readonly Client $client)
    {
    }

    private function base(?string $appId): string
    {
        return $this->client->appPath($appId) . '/templates';
    }

    private function one(string $templateId, ?string $appId): string
    {
        return $this->base($appId) . '/' . rawurlencode($templateId);
    }

    /**
     * @param array{limit?:int, cursor?:string, q?:string, channel?:string} $params
     * @return array<string, mixed>
     */
    public function list(array $params = [], ?string $appId = null): array
    {
        return $this->client->request('GET', $this->base($appId), $params);
    }

    /**
     * @param array{name:string, channel?:?string, tags?:array<int,string>, content:array<string, mixed>} $body
     * @return array<string, mixed>
     */
    public function create(array $body, ?string $appId = null): array
    {
        return $this->client->request('POST', $this->base($appId), [], $body);
    }

    /** @return array<string, mixed> */
    public function get(string $templateId, ?string $appId = null): array
    {
        return $this->client->request('GET', $this->one($templateId, $appId));
    }

    /**
     * İçerik değişikliği yeni sürüm oluşturur.
     *
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function update(string $templateId, array $body, ?string $appId = null): array
    {
        return $this->client->request('PATCH', $this->one($templateId, $appId), [], $body);
    }

    /** @return array<string, mixed> */
    public function delete(string $templateId, ?string $appId = null): array
    {
        return $this->client->request('DELETE', $this->one($templateId, $appId));
    }

    /**
     * Şablonu aynı organizasyondaki başka bir app'e kopyalar.
     *
     * @param array{target_app_id:string, name?:string} $body
     * @return array<string, mixed>
     */
    public function copyToApp(string $templateId, array $body, ?string $appId = null): array
    {
        return $this->client->request('POST', $this->one($templateId, $appId) . '/copy_to_app', [], $body);
    }

    /** @return array<string, mixed> */
    public function versions(string $templateId, ?string $appId = null): array
    {
        return $this->client->request('GET', $this->one($templateId, $appId) . '/versions');
    }

    /**
     * Örnek kullanıcı verisiyle render önizlemesi (gönderim yapmaz).
     *
     * @param array{channel?:string, language?:string, user?:array<string, mixed>} $body
     * @return array<string, mixed>
     */
    public function render(string $templateId, array $body = [], ?string $appId = null): array
    {
        return $this->client->request('POST', $this->one($templateId, $appId) . '/render', [], (object) $body);
    }
}
