<?php

declare(strict_types=1);

namespace HeroSignal\Resources;

use HeroSignal\Client;

/**
 * İki yönlü mesaj anahtar sözcükleri (org geneli + app). Eylemler: tag ekle/kaldır, olay → journey tetiği,
 * otomatik yanıt, abonelik. Yasal (ayrılmış) sözcükler listede ayrıca döner.
 */
final class SmsKeywords
{
    public function __construct(private readonly Client $client)
    {
    }

    private function base(?string $appId): string
    {
        return $this->client->appPath($appId) . '/sms-keywords';
    }

    /** @return array<string, mixed> */
    public function list(?string $appId = null): array
    {
        return $this->client->request('GET', $this->base($appId));
    }

    /**
     * @param array{keyword:string, actions:array<string, mixed>, scope?:'org'|'app', channels?:list<string>, enabled?:bool, description?:string} $input
     * @return array<string, mixed>
     */
    public function create(array $input, ?string $appId = null): array
    {
        return $this->client->request('POST', $this->base($appId), [], $input);
    }

    /**
     * @param array<string, mixed> $input keyword, channels, actions, enabled, description
     * @return array<string, mixed>
     */
    public function update(string $keywordId, array $input, ?string $appId = null): array
    {
        return $this->client->request('PATCH', $this->base($appId) . '/' . rawurlencode($keywordId), [], $input);
    }

    /** @return array<string, mixed> */
    public function delete(string $keywordId, ?string $appId = null): array
    {
        return $this->client->request('DELETE', $this->base($appId) . '/' . rawurlencode($keywordId));
    }
}
