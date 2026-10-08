<?php

declare(strict_types=1);

namespace HeroSignal\Resources;

use HeroSignal\Client;

/** In-app mesajlar: /v1/apps/{app}/in-app-messages… (okuma `analytics:read`, yazma/yayınlama `admin`). */
final class InAppMessages
{
    public function __construct(private readonly Client $client)
    {
    }

    private function base(?string $appId, ?string $messageId = null): string
    {
        $path = '/v1/apps/' . rawurlencode($this->client->appId($appId)) . '/in-app-messages';
        return $messageId === null ? $path : $path . '/' . rawurlencode($messageId);
    }

    /**
     * @param array{limit?:int, cursor?:string, status?:string} $params
     * @return array<string, mixed>
     */
    public function list(array $params = [], ?string $appId = null): array
    {
        return $this->client->request('GET', $this->base($appId), $params);
    }

    /** @return array<string, mixed> */
    public function get(string $messageId, ?string $appId = null): array
    {
        return $this->client->request('GET', $this->base($appId, $messageId));
    }

    /**
     * Taslak olarak oluşturulur; `setStatus($id, 'active')` ile yayınlanır.
     *
     * @param array<string, mixed> $body name, triggers, display, audience?, frequency?, priority?, start_at?, end_at?
     * @return array<string, mixed>
     */
    public function create(array $body, ?string $appId = null): array
    {
        return $this->client->request('POST', $this->base($appId), [], $body);
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function update(string $messageId, array $body, ?string $appId = null): array
    {
        return $this->client->request('PATCH', $this->base($appId, $messageId), [], $body);
    }

    /**
     * @param string $status active | paused | archived
     * @return array<string, mixed>
     */
    public function setStatus(string $messageId, string $status, ?string $appId = null): array
    {
        return $this->client->request('POST', $this->base($appId, $messageId) . '/status', [], ['status' => $status]);
    }

    /** @return array<string, mixed> */
    public function delete(string $messageId, ?string $appId = null): array
    {
        return $this->client->request('DELETE', $this->base($appId, $messageId));
    }
}
