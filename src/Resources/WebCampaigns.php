<?php

declare(strict_types=1);

namespace Pushizo\Resources;

use Pushizo\Client;

/**
 * Web kampanyaları (pop-up, banner, slide-in; ADR-0039): taslak oluşturma, düzenleme, durum geçişi ve günlük rapor.
 * Liste cursor sayfalıdır (`Client::paginate` ile dolaşılabilir).
 */
final class WebCampaigns
{
    public function __construct(private readonly Client $client)
    {
    }

    private function base(?string $appId): string
    {
        return $this->client->appPath($appId) . '/web-campaigns';
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
     * Kampanya taslak olarak oluşur; yayın için `setStatus($id, 'active')`.
     *
     * @param array<string, mixed> $input name, display (layout, blocks…), trigger, variants, conditions, targeting, frequency, priority, start_at, end_at
     * @return array<string, mixed>
     */
    public function create(array $input, ?string $appId = null): array
    {
        return $this->client->request('POST', $this->base($appId), [], $input);
    }

    /**
     * Detay ve toplam istatistikler.
     *
     * @return array<string, mixed>
     */
    public function get(string $campaignId, ?string $appId = null): array
    {
        return $this->client->request('GET', $this->base($appId) . '/' . rawurlencode($campaignId));
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function update(string $campaignId, array $input, ?string $appId = null): array
    {
        return $this->client->request('PATCH', $this->base($appId) . '/' . rawurlencode($campaignId), [], $input);
    }

    /**
     * Son `days` günün günlük gösterim, tıklama, kapatma ve dönüşüm serisi.
     *
     * @param array{days?:int} $params
     * @return array<string, mixed>
     */
    public function report(string $campaignId, array $params = [], ?string $appId = null): array
    {
        return $this->client->request('GET', $this->base($appId) . '/' . rawurlencode($campaignId) . '/report', $params);
    }

    /**
     * Yayınla (`active`), duraklat (`paused`), arşivle (`archived`) veya taslağa al (`draft`).
     *
     * @param 'draft'|'active'|'paused'|'archived' $status
     * @return array<string, mixed>
     */
    public function setStatus(string $campaignId, string $status, ?string $appId = null): array
    {
        return $this->client->request('POST', $this->base($appId) . '/' . rawurlencode($campaignId) . '/status', [], ['status' => $status]);
    }
}
