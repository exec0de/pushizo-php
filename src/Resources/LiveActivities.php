<?php

declare(strict_types=1);

namespace HeroSignal\Resources;

use HeroSignal\Client;

/**
 * iOS Live Activities (ADR-0024): türler (`admin` kapsamı) ve start/update/end gönderimi (`notifications:write`).
 * Tür adı uygulamadaki `ActivityAttributes` Swift tip adıdır.
 */
final class LiveActivities
{
    public function __construct(private readonly Client $client)
    {
    }

    private function base(?string $appId): string
    {
        return $this->client->appPath($appId) . '/live-activities';
    }

    /** @return array<string, mixed> */
    public function listTypes(?string $appId = null): array
    {
        return $this->client->request('GET', $this->base($appId) . '/types');
    }

    /**
     * @param array<string, mixed> $body name, content_state_schema, attributes_schema?, description?
     * @return array<string, mixed>
     */
    public function createType(array $body, ?string $appId = null): array
    {
        return $this->client->request('POST', $this->base($appId) . '/types', [], $body);
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function updateType(string $activityType, array $body, ?string $appId = null): array
    {
        return $this->client->request('PATCH', $this->base($appId) . '/types/' . rawurlencode($activityType), [], $body);
    }

    /**
     * Türü ve bu türe ait cihaz belirteçlerini siler.
     *
     * @return array<string, mixed>
     */
    public function deleteType(string $activityType, ?string $appId = null): array
    {
        return $this->client->request('DELETE', $this->base($appId) . '/types/' . rawurlencode($activityType));
    }

    /**
     * Push-to-start ile başlatır (iOS 17.2+); `activity_id` verilmezse üretilip döner.
     *
     * @param array<string, mixed> $body target, content_state, attributes?, activity_id?, alert?, stale_date?, priority?
     * @return array<string, mixed>
     */
    public function start(string $activityType, array $body, ?string $appId = null): array
    {
        return $this->send('start', $activityType, $body, $appId);
    }

    /**
     * @param array<string, mixed> $body activity_id, content_state, alert?, stale_date?, priority?
     * @return array<string, mixed>
     */
    public function update(string $activityType, array $body, ?string $appId = null): array
    {
        return $this->send('update', $activityType, $body, $appId);
    }

    /**
     * @param array<string, mixed> $body activity_id, content_state?, dismissal_date?
     * @return array<string, mixed>
     */
    public function end(string $activityType, array $body, ?string $appId = null): array
    {
        return $this->send('end', $activityType, $body, $appId);
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    private function send(string $event, string $activityType, array $body, ?string $appId): array
    {
        return $this->client->request('POST', $this->base($appId) . '/' . rawurlencode($activityType) . '/' . $event, [], $body);
    }
}
