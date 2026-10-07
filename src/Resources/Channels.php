<?php

declare(strict_types=1);

namespace HeroSignal\Resources;

use HeroSignal\Client;

/** Channels API (admin kapsamı): kimlik bilgisi yükleme ve doğrulama. */
final class Channels
{
    public function __construct(private readonly Client $client)
    {
    }

    private function base(?string $appId): string
    {
        return '/v1/apps/' . rawurlencode($this->client->appId($appId)) . '/channels';
    }

    /** @return array<string, mixed> */
    public function list(?string $appId = null): array
    {
        return $this->client->request('GET', $this->base($appId));
    }

    /**
     * @param array<string, mixed> $body ör. ['provider' => 'apns', 'key_id' => …, 'p8' => file_get_contents('AuthKey.p8')]
     * @return array<string, mixed>
     */
    public function upsert(string $channel, array $body, ?string $appId = null): array
    {
        return $this->client->request('PUT', $this->base($appId) . '/' . rawurlencode($channel), [], $body);
    }

    /** @return array<string, mixed> */
    public function validate(string $channel, ?string $appId = null): array
    {
        return $this->client->request('POST', $this->base($appId) . '/' . rawurlencode($channel) . '/validate');
    }
}
