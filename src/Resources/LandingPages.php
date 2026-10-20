<?php

declare(strict_types=1);

namespace Pushizo\Resources;

use Pushizo\Client;

/** Landing sayfaları (ADR-0039): liste, detay ve yayın durumu. Sayfa düzenleme dashboard'dan yapılır. */
final class LandingPages
{
    public function __construct(private readonly Client $client)
    {
    }

    private function base(?string $appId): string
    {
        return $this->client->appPath($appId) . '/landing-pages';
    }

    /**
     * @param array{limit?:int, cursor?:string, status?:'draft'|'published'|'archived'} $params
     * @return array<string, mixed>
     */
    public function list(array $params = [], ?string $appId = null): array
    {
        return $this->client->request('GET', $this->base($appId), $params);
    }

    /** @return array<string, mixed> */
    public function get(string $pageId, ?string $appId = null): array
    {
        return $this->client->request('GET', $this->base($appId) . '/' . rawurlencode($pageId));
    }

    /**
     * Taslağı yayınlar.
     *
     * @return array<string, mixed>
     */
    public function publish(string $pageId, ?string $appId = null): array
    {
        return $this->client->request('POST', $this->base($appId) . '/' . rawurlencode($pageId) . '/publish');
    }

    /**
     * Yayından kaldırır.
     *
     * @return array<string, mixed>
     */
    public function unpublish(string $pageId, ?string $appId = null): array
    {
        return $this->client->request('POST', $this->base($appId) . '/' . rawurlencode($pageId) . '/unpublish');
    }
}
