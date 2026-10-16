<?php

declare(strict_types=1);

namespace Pushizo\Resources;

use Pushizo\Client;

/** Zapier entegrasyonu: bağlantı testi, REST Hook abonelikleri ve tetik örnekleri. */
final class Zapier
{
    public function __construct(private readonly Client $client)
    {
    }

    private function base(?string $appId): string
    {
        return $this->client->appPath($appId) . '/integrations/zapier';
    }

    /**
     * Bağlantı testi: anahtarın app'i ve kapsamları.
     *
     * @return array<string, mixed>
     */
    public function me(?string $appId = null): array
    {
        return $this->client->request('GET', $this->base($appId) . '/me');
    }

    /**
     * REST Hook aboneliği (Zap açıldı). `event_name`, `event.received` tetiği için olay adı filtresidir.
     *
     * @param array{target_url:string, event:string, event_name?:string} $input
     * @return array<string, mixed>
     */
    public function subscribe(array $input, ?string $appId = null): array
    {
        return $this->client->request('POST', $this->base($appId) . '/hooks', [], $input);
    }

    /**
     * REST Hook aboneliğini siler (Zap kapatıldı).
     *
     * @return array<string, mixed>
     */
    public function unsubscribe(string $hookId, ?string $appId = null): array
    {
        return $this->client->request('DELETE', $this->base($appId) . '/hooks/' . rawurlencode($hookId));
    }

    /**
     * Tetik örnekleri (son kayıtlar; abonelik gövdesiyle aynı biçim).
     *
     * @param array{event:string, event_name?:string} $params
     * @return array<string, mixed>
     */
    public function samples(array $params, ?string $appId = null): array
    {
        return $this->client->request('GET', $this->base($appId) . '/samples', $params);
    }
}
