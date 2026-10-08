<?php

declare(strict_types=1);

namespace HeroSignal\Resources;

use HeroSignal\Client;

/** Giden webhook endpoint'leri: /v1/apps/{app}/webhooks… (`admin` kapsamı). İmza doğrulaması: `\HeroSignal\Webhook`. */
final class Webhooks
{
    public function __construct(private readonly Client $client)
    {
    }

    private function base(?string $appId): string
    {
        return '/v1/apps/' . rawurlencode($this->client->appId($appId)) . '/webhooks';
    }

    /** @return array<string, mixed> */
    public function list(?string $appId = null): array
    {
        return $this->client->request('GET', $this->base($appId));
    }

    /**
     * Dönen `secret` yalnızca bu yanıtta görünür; güvenli bir yerde saklayın.
     *
     * @param array<string, mixed> $body url, events, description?, headers?, batch?, event_filters?
     * @return array<string, mixed>
     */
    public function create(array $body, ?string $appId = null): array
    {
        return $this->client->request('POST', $this->base($appId), [], $body);
    }

    /** @return array<string, mixed> */
    public function get(string $webhookId, ?string $appId = null): array
    {
        return $this->client->request('GET', $this->base($appId) . '/' . rawurlencode($webhookId));
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function update(string $webhookId, array $body, ?string $appId = null): array
    {
        return $this->client->request('PATCH', $this->base($appId) . '/' . rawurlencode($webhookId), [], $body);
    }

    /** @return array<string, mixed> */
    public function delete(string $webhookId, ?string $appId = null): array
    {
        return $this->client->request('DELETE', $this->base($appId) . '/' . rawurlencode($webhookId));
    }

    /** Yeni secret döner; eski secret 24 saat boyunca ikinci imza olarak gönderilir. @return array<string, mixed> */
    public function rotateSecret(string $webhookId, ?string $appId = null): array
    {
        return $this->client->request('POST', $this->base($appId) . '/' . rawurlencode($webhookId) . '/rotate-secret');
    }

    /** @return array<string, mixed> */
    public function test(string $webhookId, ?string $appId = null): array
    {
        return $this->client->request('POST', $this->base($appId) . '/' . rawurlencode($webhookId) . '/test');
    }

    /**
     * @param array{limit?:int, cursor?:string, status?:string, event_type?:string} $params
     * @return array<string, mixed>
     */
    public function deliveries(string $webhookId, array $params = [], ?string $appId = null): array
    {
        return $this->client->request('GET', $this->base($appId) . '/' . rawurlencode($webhookId) . '/deliveries', $params);
    }

    /** @return array<string, mixed> */
    public function replay(string $deliveryId, ?string $appId = null): array
    {
        return $this->client->request('POST', $this->base($appId) . '/deliveries/' . rawurlencode($deliveryId) . '/replay');
    }
}
