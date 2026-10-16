<?php

declare(strict_types=1);

namespace Pushizo\Resources;

use Pushizo\Client;

/**
 * Giden webhook endpoint'leri (`admin` kapsamı). Alıcı tarafta imza doğrulaması için
 * `\Pushizo\Webhook::verify()` / `constructEvents()`.
 */
final class Webhooks
{
    public function __construct(private readonly Client $client)
    {
    }

    private function base(?string $appId): string
    {
        return $this->client->appPath($appId) . '/webhooks';
    }

    /** @return array<string, mixed> */
    public function list(?string $appId = null): array
    {
        return $this->client->request('GET', $this->base($appId));
    }

    /**
     * Dönen `secret` yalnızca bu yanıtta görünür.
     *
     * @param array<string, mixed> $body url, events, batch, headers, enabled…
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

    /**
     * Yeni secret döner; eski secret 24 saat boyunca ikinci imza olarak gönderilir.
     *
     * @return array<string, mixed>
     */
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
     * Event Streams önizlemesi: örnek olay + gövde şablonu + filtre → oluşan gövde veya hata (gönderim yapmaz).
     *
     * @param array{event_type:string, data?:array<string, mixed>, body_template?:?string, body_content_type?:'application/json'|'text/plain', filters?:mixed, batch?:bool} $input
     * @return array<string, mixed>
     */
    public function preview(array $input, ?string $appId = null): array
    {
        return $this->client->request('POST', $this->base($appId) . '/preview', [], $input);
    }

    /**
     * @param array{limit?:int, cursor?:string, status?:'pending'|'success'|'failed'|'dead', event_type?:string} $params
     * @return array<string, mixed>
     */
    public function deliveries(string $webhookId, array $params = [], ?string $appId = null): array
    {
        return $this->client->request('GET', $this->base($appId) . '/' . rawurlencode($webhookId) . '/deliveries', $params);
    }

    /**
     * Teslimi aynı olay kimliğiyle yeniden gönderir.
     *
     * @return array<string, mixed>
     */
    public function replay(string $deliveryId, ?string $appId = null): array
    {
        return $this->client->request('POST', $this->base($appId) . '/deliveries/' . rawurlencode($deliveryId) . '/replay');
    }
}
