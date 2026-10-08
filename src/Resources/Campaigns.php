<?php

declare(strict_types=1);

namespace HeroSignal\Resources;

use HeroSignal\Client;

/** Kampanyalar: taslak → zamanla/gönder → duraklat/sürdür/iptal yaşam döngüsü ve istatistikler. */
final class Campaigns
{
    public function __construct(private readonly Client $client)
    {
    }

    private function base(?string $appId): string
    {
        return $this->client->appPath($appId) . '/campaigns';
    }

    private function one(string $campaignId, ?string $appId): string
    {
        return $this->base($appId) . '/' . rawurlencode($campaignId);
    }

    /** @return array<string, mixed> */
    private function action(string $campaignId, string $action, ?string $appId): array
    {
        return $this->client->request('POST', $this->one($campaignId, $appId) . '/' . $action);
    }

    /**
     * @param array{limit?:int, cursor?:string, q?:string, status?:string, type?:string, channel?:string} $params
     * @return array<string, mixed>
     */
    public function list(array $params = [], ?string $appId = null): array
    {
        return $this->client->request('GET', $this->base($appId), $params);
    }

    /**
     * @param array<string, mixed> $body name, type, audience, content | template_id, delivery, recurrence, send_at
     * @return array<string, mixed>
     */
    public function create(array $body, ?string $appId = null): array
    {
        return $this->client->request('POST', $this->base($appId), [], $body);
    }

    /** @return array<string, mixed> */
    public function get(string $campaignId, ?string $appId = null): array
    {
        return $this->client->request('GET', $this->one($campaignId, $appId));
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function update(string $campaignId, array $body, ?string $appId = null): array
    {
        return $this->client->request('PATCH', $this->one($campaignId, $appId), [], $body);
    }

    /** @return array<string, mixed> */
    public function delete(string $campaignId, ?string $appId = null): array
    {
        return $this->client->request('DELETE', $this->one($campaignId, $appId));
    }

    /**
     * `send_at` veya `recurrence` (cron) ile zamanlar.
     *
     * @param array{send_at?:string, recurrence?:array{cron:string, until?:?string, timezone?:string}} $body
     * @return array<string, mixed>
     */
    public function schedule(string $campaignId, array $body, ?string $appId = null): array
    {
        return $this->client->request('POST', $this->one($campaignId, $appId) . '/schedule', [], $body);
    }

    /**
     * Hemen gönder.
     *
     * @return array<string, mixed>
     */
    public function send(string $campaignId, ?string $appId = null): array
    {
        return $this->action($campaignId, 'send', $appId);
    }

    /** @return array<string, mixed> */
    public function pause(string $campaignId, ?string $appId = null): array
    {
        return $this->action($campaignId, 'pause', $appId);
    }

    /** @return array<string, mixed> */
    public function resume(string $campaignId, ?string $appId = null): array
    {
        return $this->action($campaignId, 'resume', $appId);
    }

    /** @return array<string, mixed> */
    public function cancel(string $campaignId, ?string $appId = null): array
    {
        return $this->action($campaignId, 'cancel', $appId);
    }

    /**
     * Kopyasını taslak olarak oluşturur.
     *
     * @return array<string, mixed>
     */
    public function duplicate(string $campaignId, ?string $appId = null): array
    {
        return $this->action($campaignId, 'duplicate', $appId);
    }

    /**
     * Onay akışında kampanyayı onaylar.
     *
     * @return array<string, mixed>
     */
    public function approve(string $campaignId, ?string $appId = null): array
    {
        return $this->action($campaignId, 'approve', $appId);
    }

    /**
     * Başarısız teslimatlara yeniden gönderir.
     *
     * @return array<string, mixed>
     */
    public function resendFailed(string $campaignId, ?string $appId = null): array
    {
        return $this->action($campaignId, 'resend-failed', $appId);
    }

    /** @return array<string, mixed> */
    public function stats(string $campaignId, ?string $appId = null): array
    {
        return $this->client->request('GET', $this->one($campaignId, $appId) . '/stats');
    }

    /**
     * @param array{limit?:int, cursor?:string, status?:string} $params
     * @return array<string, mixed>
     */
    public function deliveries(string $campaignId, array $params = [], ?string $appId = null): array
    {
        return $this->client->request('GET', $this->one($campaignId, $appId) . '/deliveries', $params);
    }

    /**
     * Kampanya çalıştırmaları (üretilen mesajlar).
     *
     * @return array<string, mixed>
     */
    public function messages(string $campaignId, ?string $appId = null): array
    {
        return $this->client->request('GET', $this->one($campaignId, $appId) . '/messages');
    }

    /**
     * A/B testi: varyant bazlı gönderim/açılma/tıklama/dönüşüm, kontrol grubu ve anlamlılık.
     *
     * @return array<string, mixed>
     */
    public function variantStats(string $campaignId, ?string $appId = null): array
    {
        return $this->client->request('GET', $this->one($campaignId, $appId) . '/variants/stats');
    }

    /**
     * A/B testinde kazananı elle seçer; kalan kitleye kazanan varyant gönderilir.
     *
     * @return array<string, mixed>
     */
    public function selectWinner(string $campaignId, string $variantId, ?string $appId = null): array
    {
        return $this->client->request('POST', $this->one($campaignId, $appId) . '/winner', [], ['variant_id' => $variantId]);
    }
}
