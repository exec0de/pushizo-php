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

    /** @return array<string, mixed> */
    public function organization(): array
    {
        return $this->client->request('GET', '/v1/organization');
    }

    /** @return array<string, mixed> */
    public function list(): array
    {
        return $this->client->request('GET', '/v1/apps');
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
