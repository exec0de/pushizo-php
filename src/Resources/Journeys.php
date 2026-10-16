<?php

declare(strict_types=1);

namespace Pushizo\Resources;

use Pushizo\Client;

/**
 * Journey'ler (çok adımlı otomasyonlar): taslak graf → yayın (sürüm) → duraklat/sürdür/arşivle
 * yaşam döngüsü, istatistikler ve kullanıcı bazında çalıştırmalar (run).
 */
final class Journeys
{
    public function __construct(private readonly Client $client)
    {
    }

    private function base(?string $appId): string
    {
        return $this->client->appPath($appId) . '/journeys';
    }

    private function one(string $journeyId, ?string $appId): string
    {
        return $this->base($appId) . '/' . rawurlencode($journeyId);
    }

    /** @return array<string, mixed> */
    private function action(string $journeyId, string $action, ?string $appId): array
    {
        return $this->client->request('POST', $this->one($journeyId, $appId) . '/' . $action);
    }

    /**
     * @param array{limit?:int, cursor?:string, status?:string, q?:string} $params
     * @return array<string, mixed>
     */
    public function list(array $params = [], ?string $appId = null): array
    {
        return $this->client->request('GET', $this->base($appId), $params);
    }

    /**
     * Taslak journey oluşturur; `graph` verilmezse varsayılan graf ile başlar.
     *
     * @param array{name:string, description?:string, graph?:array{nodes:list<array<string, mixed>>, edges:list<array<string, mixed>>}, settings?:array<string, mixed>} $body
     * @return array<string, mixed>
     */
    public function create(array $body, ?string $appId = null): array
    {
        return $this->client->request('POST', $this->base($appId), [], $body);
    }

    /** @return array<string, mixed> */
    public function get(string $journeyId, ?string $appId = null): array
    {
        return $this->client->request('GET', $this->one($journeyId, $appId));
    }

    /**
     * Ad ve açıklamayı günceller (graf için `saveDraft`).
     *
     * @param array{name?:string, description?:?string} $body
     * @return array<string, mixed>
     */
    public function update(string $journeyId, array $body, ?string $appId = null): array
    {
        return $this->client->request('PATCH', $this->one($journeyId, $appId), [], $body);
    }

    /** @return array<string, mixed> */
    public function delete(string $journeyId, ?string $appId = null): array
    {
        return $this->client->request('DELETE', $this->one($journeyId, $appId));
    }

    /**
     * Taslak grafı ve ayarları tümüyle değiştirir; yayındaki sürüm etkilenmez.
     *
     * @param array{graph:array{nodes:list<array<string, mixed>>, edges:list<array<string, mixed>>}, settings?:array<string, mixed>} $body
     * @return array<string, mixed>
     */
    public function saveDraft(string $journeyId, array $body, ?string $appId = null): array
    {
        return $this->client->request('PUT', $this->one($journeyId, $appId) . '/draft', [], $body);
    }

    /**
     * Taslaktaki tek bir düğümün `data`/`position` alanlarını günceller.
     *
     * @param array{data?:array<string, mixed>, position?:array{x:float|int, y:float|int}} $body
     * @return array<string, mixed>
     */
    public function updateNode(string $journeyId, string $nodeId, array $body, ?string $appId = null): array
    {
        return $this->client->request('PATCH', $this->one($journeyId, $appId) . '/nodes/' . rawurlencode($nodeId), [], $body);
    }

    /** @return array<string, mixed> */
    public function versions(string $journeyId, ?string $appId = null): array
    {
        return $this->client->request('GET', $this->one($journeyId, $appId) . '/versions');
    }

    /**
     * Taslağı doğrulayıp yeni sürüm olarak yayınlar.
     *
     * @return array<string, mixed>
     */
    public function publish(string $journeyId, ?string $appId = null): array
    {
        return $this->action($journeyId, 'publish', $appId);
    }

    /** @return array<string, mixed> */
    public function pause(string $journeyId, ?string $appId = null): array
    {
        return $this->action($journeyId, 'pause', $appId);
    }

    /** @return array<string, mixed> */
    public function resume(string $journeyId, ?string $appId = null): array
    {
        return $this->action($journeyId, 'resume', $appId);
    }

    /** @return array<string, mixed> */
    public function archive(string $journeyId, ?string $appId = null): array
    {
        return $this->action($journeyId, 'archive', $appId);
    }

    /**
     * Kopyasını taslak olarak oluşturur.
     *
     * @return array<string, mixed>
     */
    public function duplicate(string $journeyId, ?string $appId = null): array
    {
        return $this->action($journeyId, 'duplicate', $appId);
    }

    /**
     * Düğüm bazında istatistikler; `version` verilmezse yayındaki sürüm.
     *
     * @param array{version?:int} $params
     * @return array<string, mixed>
     */
    public function stats(string $journeyId, array $params = [], ?string $appId = null): array
    {
        return $this->client->request('GET', $this->one($journeyId, $appId) . '/stats', $params);
    }

    /**
     * @param array{limit?:int, cursor?:string, status?:string, user_id?:string, version?:int} $params
     * @return array<string, mixed>
     */
    public function listRuns(string $journeyId, array $params = [], ?string $appId = null): array
    {
        return $this->client->request('GET', $this->one($journeyId, $appId) . '/runs', $params);
    }

    /**
     * Kullanıcıyı journey'e elle sokar (API tetikleyicisi). Kullanıcı `external_id`, `user_id`
     * veya `alias` ile seçilir; `context` journey değişkenleridir.
     *
     * @param array{external_id?:string, user_id?:string, alias?:array{label:string, value:string}, context?:array<string, mixed>} $body
     * @return array<string, mixed>
     */
    public function triggerRun(string $journeyId, array $body, ?string $appId = null): array
    {
        return $this->client->request('POST', $this->one($journeyId, $appId) . '/runs', [], $body);
    }

    /** @return array<string, mixed> */
    public function getRun(string $journeyId, string $runId, ?string $appId = null): array
    {
        return $this->client->request('GET', $this->one($journeyId, $appId) . '/runs/' . rawurlencode($runId));
    }

    /**
     * Çalıştırmayı sonlandırır (kullanıcı journey'den çıkarılır).
     *
     * @return array<string, mixed>
     */
    public function exitRun(string $journeyId, string $runId, ?string $appId = null): array
    {
        return $this->client->request('DELETE', $this->one($journeyId, $appId) . '/runs/' . rawurlencode($runId));
    }

    /**
     * Kullanıcının (alias `label`/`value`) tüm journey çalıştırmaları.
     *
     * @return array<string, mixed>
     */
    public function listForUser(string $label, string $value, ?string $appId = null): array
    {
        return $this->client->request('GET', $this->client->appPath($appId) . '/users/by/' . rawurlencode($label) . '/' . rawurlencode($value) . '/journeys');
    }
}
