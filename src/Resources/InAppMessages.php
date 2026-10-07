<?php

declare(strict_types=1);

namespace HeroSignal\Resources;

use HeroSignal\Client;

/** Uygulama içi mesajlar (in-app): CRUD ve durum geçişi (draft/active/paused/archived). */
final class InAppMessages
{
    public function __construct(private readonly Client $client)
    {
    }

    private function base(?string $appId): string
    {
        return $this->client->appPath($appId) . '/in-app-messages';
    }

    /**
     * @param array{limit?:int, cursor?:string, status?:'draft'|'active'|'paused'|'archived'} $params
     * @return array<string, mixed>
     */
    public function list(array $params = [], ?string $appId = null): array
    {
        return $this->client->request('GET', $this->base($appId), $params);
    }

    /**
     * @param array<string, mixed> $body name, triggers, display (layout, blocks…), audience, priority, start_at, end_at
     * @return array<string, mixed>
     */
    public function create(array $body, ?string $appId = null): array
    {
        return $this->client->request('POST', $this->base($appId), [], $body);
    }

    /** @return array<string, mixed> */
    public function get(string $messageId, ?string $appId = null): array
    {
        return $this->client->request('GET', $this->base($appId) . '/' . rawurlencode($messageId));
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function update(string $messageId, array $body, ?string $appId = null): array
    {
        return $this->client->request('PATCH', $this->base($appId) . '/' . rawurlencode($messageId), [], $body);
    }

    /** @return array<string, mixed> */
    public function delete(string $messageId, ?string $appId = null): array
    {
        return $this->client->request('DELETE', $this->base($appId) . '/' . rawurlencode($messageId));
    }

    /**
     * Yayınla / duraklat / arşivle.
     *
     * @param 'draft'|'active'|'paused'|'archived' $status
     * @return array<string, mixed>
     */
    public function setStatus(string $messageId, string $status, ?string $appId = null): array
    {
        return $this->client->request('POST', $this->base($appId) . '/' . rawurlencode($messageId) . '/status', [], ['status' => $status]);
    }
}
