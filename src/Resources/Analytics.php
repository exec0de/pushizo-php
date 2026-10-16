<?php

declare(strict_types=1);

namespace Pushizo\Resources;

use Pushizo\Client;

/**
 * Analitik raporları (`analytics:read`). `from`/`to` ISO-8601 (UTC); `compare` boolean verilebilir.
 * `format => 'csv'` verilirse rapor CSV metni (string) olarak döner.
 */
final class Analytics
{
    public function __construct(private readonly Client $client)
    {
    }

    /**
     * @param array<string, scalar|null> $params
     * @return array<string, mixed>|string
     */
    private function report(string $name, array $params, ?string $appId): array|string
    {
        $path = $this->client->appPath($appId) . '/analytics/' . $name;
        if (($params['format'] ?? null) === 'csv') {
            return $this->client->requestText('GET', $path, $params);
        }
        return $this->client->request('GET', $path, $params);
    }

    /**
     * Genel bakış KPI'ları.
     *
     * @param array{range?:'7d'|'30d', from?:string, to?:string} $params
     * @return array<string, mixed>
     */
    public function overview(array $params = [], ?string $appId = null): array
    {
        return $this->client->request('GET', $this->client->appPath($appId) . '/analytics/overview', $params);
    }

    /**
     * Teslimat raporu.
     *
     * @param array{from?:string, to?:string, compare?:bool, format?:'json'|'csv', group_by?:'day'|'channel'|'campaign'} $params
     * @return array<string, mixed>|string
     */
    public function messages(array $params = [], ?string $appId = null): array|string
    {
        return $this->report('messages', $params, $appId);
    }

    /**
     * Abonelik büyümesi.
     *
     * @param array{from?:string, to?:string, compare?:bool, format?:'json'|'csv'} $params
     * @return array<string, mixed>|string
     */
    public function subscriptions(array $params = [], ?string $appId = null): array|string
    {
        return $this->report('subscriptions', $params, $appId);
    }

    /**
     * Olay hacmi.
     *
     * @param array{from?:string, to?:string, compare?:bool, format?:'json'|'csv', name?:string, group_by?:'day'|'name'} $params
     * @return array<string, mixed>|string
     */
    public function events(array $params = [], ?string $appId = null): array|string
    {
        return $this->report('events', $params, $appId);
    }

    /**
     * Dönüşüm/atribüsyon.
     *
     * @param array{from?:string, to?:string, compare?:bool, format?:'json'|'csv', campaign_id?:string} $params
     * @return array<string, mixed>|string
     */
    public function conversions(array $params = [], ?string $appId = null): array|string
    {
        return $this->report('conversions', $params, $appId);
    }

    /**
     * Sağlayıcı sağlığı.
     *
     * @param array{from?:string, to?:string, compare?:bool, format?:'json'|'csv'} $params
     * @return array<string, mixed>|string
     */
    public function providers(array $params = [], ?string $appId = null): array|string
    {
        return $this->report('providers', $params, $appId);
    }

    /**
     * Etkileşim ısı haritası.
     *
     * @param array{from?:string, to?:string, compare?:bool, format?:'json'|'csv'} $params
     * @return array<string, mixed>|string
     */
    public function engagement(array $params = [], ?string $appId = null): array|string
    {
        return $this->report('engagement', $params, $appId);
    }

    /**
     * Retention kohortu: ilk görülme haftası × sonraki haftalarda aktiflik oranı.
     *
     * @param array{weeks?:int, format?:'json'|'csv'} $params
     * @return array<string, mixed>|string
     */
    public function retention(array $params = [], ?string $appId = null): array|string
    {
        return $this->report('retention', $params, $appId);
    }

    /**
     * Sıralı adımlı huni raporu.
     *
     * @param array{steps:array<int, array{name:string, where?:array{key:string, value:string}}>, from?:string, to?:string, window_hours?:int, compare?:bool, format?:'json'|'csv'} $body
     * @return array<string, mixed>|string
     */
    public function funnel(array $body, ?string $appId = null): array|string
    {
        $path = $this->client->appPath($appId) . '/analytics/funnels';
        if (($body['format'] ?? null) === 'csv') {
            return $this->client->requestText('POST', $path, [], $body);
        }
        return $this->client->request('POST', $path, [], $body);
    }
}
