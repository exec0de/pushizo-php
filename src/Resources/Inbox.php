<?php

declare(strict_types=1);

namespace Pushizo\Resources;

use Pushizo\Client;

/**
 * Kullanıcının bildirim kutusu (inbox; kullanıcı alias `label`/`value` ile adreslenir) ve ekip gelen kutusu
 * yönetimi: hazır yanıtlar, takımlar, temsilciler, ayarlar ve metrikler (ADR-0040).
 */
final class Inbox
{
    public function __construct(private readonly Client $client)
    {
    }

    private function base(string $label, string $value, ?string $appId): string
    {
        return $this->client->appPath($appId) . '/users/by/' . rawurlencode($label) . '/' . rawurlencode($value) . '/inbox';
    }

    /**
     * @param array{limit?:int, cursor?:string, include_archived?:bool} $params
     * @return array<string, mixed>
     */
    public function list(string $label, string $value, array $params = [], ?string $appId = null): array
    {
        return $this->client->request('GET', $this->base($label, $value, $appId), $params);
    }

    /** @return array<string, mixed> */
    public function readAll(string $label, string $value, ?string $appId = null): array
    {
        return $this->client->request('POST', $this->base($label, $value, $appId) . '/read-all');
    }

    /** @return array<string, mixed> */
    public function read(string $label, string $value, string $itemId, ?string $appId = null): array
    {
        return $this->client->request('POST', $this->base($label, $value, $appId) . '/' . rawurlencode($itemId) . '/read');
    }

    /**
     * Öğeyi kutudan kaldırır (arşivler).
     *
     * @return array<string, mixed>
     */
    public function archive(string $label, string $value, string $itemId, ?string $appId = null): array
    {
        return $this->client->request('DELETE', $this->base($label, $value, $appId) . '/' . rawurlencode($itemId));
    }

    private function canned(?string $appId): string
    {
        return $this->client->appPath($appId) . '/canned-responses';
    }

    /**
     * Hazır yanıtlar; `q` kısayol, başlık veya gövdede arar.
     *
     * @param array{q?:string} $params
     * @return array<string, mixed>
     */
    public function listCannedResponses(array $params = [], ?string $appId = null): array
    {
        return $this->client->request('GET', $this->canned($appId), $params);
    }

    /**
     * Hazır yanıt oluşturur; gövde Liquid şablonudur (ör. `{{ contact.name }}`) ve kayıtta doğrulanır.
     *
     * @param array{shortcut:string, title:string, body:string, language?:string|null} $input
     * @return array<string, mixed>
     */
    public function createCannedResponse(array $input, ?string $appId = null): array
    {
        return $this->client->request('POST', $this->canned($appId), [], $input);
    }

    /**
     * @param array{shortcut?:string, title?:string, body?:string, language?:string|null} $input
     * @return array<string, mixed>
     */
    public function updateCannedResponse(string $cannedId, array $input, ?string $appId = null): array
    {
        return $this->client->request('PATCH', $this->canned($appId) . '/' . rawurlencode($cannedId), [], $input);
    }

    /** @return array<string, mixed> */
    public function deleteCannedResponse(string $cannedId, ?string $appId = null): array
    {
        return $this->client->request('DELETE', $this->canned($appId) . '/' . rawurlencode($cannedId));
    }

    /**
     * Gelen kutusu takımları.
     *
     * @return array<string, mixed>
     */
    public function listTeams(?string $appId = null): array
    {
        return $this->client->request('GET', $this->client->appPath($appId) . '/inbox/teams');
    }

    /**
     * Temsilciler ve varlık (presence) durumu.
     *
     * @return array<string, mixed>
     */
    public function listAgents(?string $appId = null): array
    {
        return $this->client->request('GET', $this->client->appPath($appId) . '/inbox/agents');
    }

    /**
     * Sohbet widget'ı, mesai saatleri, yönlendirme ve e-posta yanıtı ayarları.
     *
     * @return array<string, mixed>
     */
    public function getSettings(?string $appId = null): array
    {
        return $this->client->request('GET', $this->client->appPath($appId) . '/inbox/settings');
    }

    /**
     * Yalnızca verilen bölümler güncellenir.
     *
     * @param array{widget?:array<string, mixed>, business_hours?:array<string, mixed>, routing?:array<string, mixed>, email?:array{capture_replies?:bool}} $input
     * @return array<string, mixed>
     */
    public function updateSettings(array $input, ?string $appId = null): array
    {
        return $this->client->request('PUT', $this->client->appPath($appId) . '/inbox/settings', [], $input);
    }

    /**
     * İlk yanıt/çözüm süresi ortalaması ve p90, SLA oranı ve temsilci tablosu.
     *
     * @param array{from?:string, to?:string, channel?:'sms'|'whatsapp'|'rcs'|'email'|'chat'} $params
     * @return array<string, mixed>
     */
    public function metrics(array $params = [], ?string $appId = null): array
    {
        return $this->client->request('GET', $this->client->appPath($appId) . '/inbox/metrics', $params);
    }
}
