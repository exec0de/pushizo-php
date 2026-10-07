<?php

declare(strict_types=1);

namespace HeroSignal\Resources;

use HeroSignal\Client;

/** Apps ve organizasyon uçları. */
final class Apps
{
    public function __construct(private readonly Client $client)
    {
    }

    /**
     * Geriye dönük uyumluluk kısayolu; `$client->organization->get()` ile aynıdır.
     *
     * @return array<string, mixed>
     */
    public function organization(): array
    {
        return $this->client->request('GET', '/v1/organization');
    }

    /** @return array<string, mixed> */
    public function list(): array
    {
        return $this->client->request('GET', '/v1/apps');
    }

    /**
     * App oluştur (org geneli admin anahtarı).
     *
     * @param array{name:string, slug?:string, platform_flags?:array<string,bool>, default_language?:string, timezone?:string} $body
     * @return array<string, mixed>
     */
    public function create(array $body): array
    {
        return $this->client->request('POST', '/v1/apps', [], $body);
    }

    /** @return array<string, mixed> */
    public function get(?string $appId = null): array
    {
        return $this->client->request('GET', '/v1/apps/' . rawurlencode($this->client->appId($appId)));
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function update(array $body, ?string $appId = null): array
    {
        return $this->client->request('PATCH', '/v1/apps/' . rawurlencode($this->client->appId($appId)), [], $body);
    }
}
