<?php

declare(strict_types=1);

namespace Pushizo\Resources;

use Pushizo\Client;

/**
 * Reklam hesapları ve reklam kitleleri (ADR-0037); kampanya raporlama, atribüsyon/LTV raporları, Conversions API
 * teslim geçmişi ve bütçe uyarıları (ADR-0038). Hesap bağlama OAuth ile dashboard'dan yapılır; API hesapları
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

    /**
     * Kampanya, reklam seti veya reklam listesi (`level`) ve `from`–`to` aralığındaki metrikler; `parent_id` ile bir
     * kampanyanın reklam setleri ya da bir reklam setinin reklamları süzülür.
     *
     * @param array{ad_account_id?:string, level?:'campaign'|'ad_set'|'ad', parent_id?:string, status?:string, q?:string, from?:string, to?:string, limit?:int} $params
     * @return array<string, mixed>
     */
    public function listCampaigns(array $params = [], ?string $appId = null): array
    {
        return $this->client->request('GET', $this->base($appId) . '/campaigns', $params);
    }

    /** @return array<string, mixed> */
    public function getCampaign(string $entityId, ?string $appId = null): array
    {
        return $this->client->request('GET', $this->base($appId) . '/campaigns/' . rawurlencode($entityId));
    }

    /**
     * Durum (`active`/`paused`) ve günlük bütçe değişikliği reklam platformuna da yazılır.
     *
     * @param array{status?:'active'|'paused', daily_budget?:string|int|float} $input
     * @return array<string, mixed>
     */
    public function updateCampaign(string $entityId, array $input, ?string $appId = null): array
    {
        return $this->client->request('PATCH', $this->base($appId) . '/campaigns/' . rawurlencode($entityId), [], $input);
    }

    /**
     * Hesabın kampanyalarını ve metriklerini şimdi yeniler (202, kuyruğa alınır).
     *
     * @return array<string, mixed>
     */
    public function refreshAccount(string $accountId, ?string $appId = null): array
    {
        return $this->client->request('POST', $this->base($appId) . '/accounts/' . rawurlencode($accountId) . '/refresh');
    }

    /**
     * Harcama ve performans raporu (`group_by`: gün, platform, hesap, kampanya veya reklam seti).
     *
     * @param array{from:string, to:string, currency?:string, group_by?:'day'|'platform'|'account'|'campaign'|'ad_set', ad_account_id?:string, campaign_id?:string} $params
     * @return array<string, mixed>
     */
    public function spendReport(array $params, ?string $appId = null): array
    {
        return $this->client->request('GET', $this->base($appId) . '/reports/spend', $params);
    }

    /**
     * Birleşik atribüsyon raporu (ROAS, CAC); `conversion_events` virgülle ayrılmış olay adlarıdır.
     *
     * @param array{from:string, to:string, model?:'last_click'|'first_click'|'linear'|'time_decay', window_days?:int, half_life_days?:int, currency?:string, group_by?:'channel'|'campaign'|'ad_set', conversion_events?:string} $params
     * @return array<string, mixed>
     */
    public function attributionReport(array $params, ?string $appId = null): array
    {
        return $this->client->request('GET', $this->base($appId) . '/reports/attribution', $params);
    }

    /**
     * Kohort LTV: ilk dönüşüm ayına göre kümülatif kullanıcı başı gelir.
     *
     * @param array{months?:int, cohorts?:int, currency?:string} $params
     * @return array<string, mixed>
     */
    public function ltvReport(array $params = [], ?string $appId = null): array
    {
        return $this->client->request('GET', $this->base($appId) . '/reports/ltv', $params);
    }

    /**
     * Conversions API yapılandırmaları (erişim belirteçleri dönmez).
     *
     * @return array<string, mixed>
     */
    public function listCapiConfigs(?string $appId = null): array
    {
        return $this->client->request('GET', $this->base($appId) . '/conversions-api/configs');
    }

    /**
     * Conversions API teslim geçmişi.
     *
     * @param array{config_id?:string, status?:'pending'|'sent'|'failed'|'skipped', limit?:int} $params
     * @return array<string, mixed>
     */
    public function listCapiDeliveries(array $params = [], ?string $appId = null): array
    {
        return $this->client->request('GET', $this->base($appId) . '/conversions-api/deliveries', $params);
    }

    /** @return array<string, mixed> */
    public function listAlertRules(?string $appId = null): array
    {
        return $this->client->request('GET', $this->base($appId) . '/alert-rules');
    }

    /**
     * Bütçe uyarı kuralı: günlük harcama, ROAS düşüşü veya bütçe tükenmesi eşiği.
     *
     * @param array{name:string, kind:'daily_spend'|'roas_drop'|'budget_depletion', threshold:int|float, ad_account_id?:string|null, campaign_external_id?:string|null, window_days?:int, recipients?:list<string>, enabled?:bool} $input
     * @return array<string, mixed>
     */
    public function createAlertRule(array $input, ?string $appId = null): array
    {
        return $this->client->request('POST', $this->base($appId) . '/alert-rules', [], $input);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function updateAlertRule(string $ruleId, array $input, ?string $appId = null): array
    {
        return $this->client->request('PATCH', $this->base($appId) . '/alert-rules/' . rawurlencode($ruleId), [], $input);
    }

    /** @return array<string, mixed> */
    public function deleteAlertRule(string $ruleId, ?string $appId = null): array
    {
        return $this->client->request('DELETE', $this->base($appId) . '/alert-rules/' . rawurlencode($ruleId));
    }

    /**
     * Tetiklenen bütçe uyarıları.
     *
     * @param array{rule_id?:string, limit?:int} $params
     * @return array<string, mixed>
     */
    public function listAlerts(array $params = [], ?string $appId = null): array
    {
        return $this->client->request('GET', $this->base($appId) . '/alerts', $params);
    }
}
