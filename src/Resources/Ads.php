<?php

declare(strict_types=1);

namespace Pushizo\Resources;

use Pushizo\Client;

/**
 * Reklam hesapları ve reklam kitleleri (ADR-0037). Hesap bağlama OAuth ile dashboard'dan yapılır; API hesapları
 * listeler ve kitleleri yönetir. Kitleye yalnızca `ads_personalization` rızası olan kullanıcıların SHA-256 özetleri
 * gönderilir.
 */
final class Ads
{
    public function __construct(private readonly Client $client)
    {
    }

    private function base(?string $appId): string
    {
        return $this->client->appPath($appId) . '/ads';
    }

    private function audience(string $audienceId, ?string $appId): string
    {
        return $this->base($appId) . '/audiences/' . rawurlencode($audienceId);
    }

    /**
     * Bağlı reklam hesapları (belirteçler dönmez).
     *
     * @return array<string, mixed>
     */
    public function listAccounts(?string $appId = null): array
    {
        return $this->client->request('GET', $this->base($appId) . '/accounts');
    }

    /**
     * @param array{ad_account_id?:string} $params
     * @return array<string, mixed>
     */
    public function listAudiences(array $params = [], ?string $appId = null): array
    {
        return $this->client->request('GET', $this->base($appId) . '/audiences', $params);
    }

    /**
     * Segment veya journey kaynaklı kitle; ilk senkron hemen kuyruğa alınır.
     *
     * @param array{ad_account_id:string, source:string, segment_id?:string, name:string, description?:string, identifiers?:list<string>, sync_interval_minutes?:int} $input
     * @return array<string, mixed>
     */
    public function createAudience(array $input, ?string $appId = null): array
    {
        return $this->client->request('POST', $this->base($appId) . '/audiences', [], $input);
    }

    /** @return array<string, mixed> */
    public function getAudience(string $audienceId, ?string $appId = null): array
    {
        return $this->client->request('GET', $this->audience($audienceId, $appId));
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function updateAudience(string $audienceId, array $input, ?string $appId = null): array
    {
        return $this->client->request('PATCH', $this->audience($audienceId, $appId), [], $input);
    }

    /**
     * Kitleyi siler; `$deleteOnPlatform` true ise reklam platformunda da silinir.
     *
     * @return array<string, mixed>
     */
    public function deleteAudience(string $audienceId, bool $deleteOnPlatform = false, ?string $appId = null): array
    {
        return $this->client->request('DELETE', $this->audience($audienceId, $appId), ['delete_on_platform' => $deleteOnPlatform ? 'true' : 'false']);
    }

    /**
     * Senkronu şimdi çalıştırır (`full` tam yeniden yükleme).
     *
     * @param array{full?:bool} $input
     * @return array<string, mixed>
     */
    public function syncAudience(string $audienceId, array $input = [], ?string $appId = null): array
    {
        return $this->client->request('POST', $this->audience($audienceId, $appId) . '/sync', [], $input);
    }

    /**
     * @param array{limit?:int} $params
     * @return array<string, mixed>
     */
    public function listSyncs(string $audienceId, array $params = [], ?string $appId = null): array
    {
        return $this->client->request('GET', $this->audience($audienceId, $appId) . '/syncs', $params);
    }

    /**
     * Benzer kitle: ISO ülke kodu ve oran (0,01–0,20).
     *
     * @param array{country:string, ratio:float, name?:string} $input
     * @return array<string, mixed>
     */
    public function createLookalike(string $audienceId, array $input, ?string $appId = null): array
    {
        return $this->client->request('POST', $this->audience($audienceId, $appId) . '/lookalikes', [], $input);
    }
}
