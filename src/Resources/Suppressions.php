<?php

declare(strict_types=1);

namespace Pushizo\Resources;

use Pushizo\Client;

/**
 * Suppression list API: /v1/apps/{app}/suppressions — e-posta/SMS gönderilmeyecek adresler.
 * Ham adres saklanmaz; yanıtlarda maskeli değer (`value_masked`) döner. Okuma `users:read`, yazma `users:write`.
 */
final class Suppressions
{
    public function __construct(private readonly Client $client)
    {
    }

    private function base(?string $appId): string
    {
        return '/v1/apps/' . rawurlencode($this->client->appId($appId)) . '/suppressions';
    }

    /**
     * @param array{channel?:'email'|'sms', reason?:'bounce'|'complaint'|'manual'|'unsubscribe_link'|'stop', value?:string, limit?:int, cursor?:string} $params
     * @return array{data:array<int, array<string, mixed>>, next_cursor:?string, has_more:bool}
     */
    public function list(array $params = [], ?string $appId = null): array
    {
        /** @var array{data:array<int, array<string, mixed>>, next_cursor:?string, has_more:bool} $page */
        $page = $this->client->request('GET', $this->base($appId), $params);
        return $page;
    }

    /**
     * Adresi listeye ekler (varsayılan neden `manual`).
     *
     * @param array{channel:'email'|'sms', value:string, reason?:string, expires_at?:string, note?:string} $input
     * @return array<string, mixed>
     */
    public function create(array $input, ?string $appId = null): array
    {
        return $this->client->request('POST', $this->base($appId), [], $input);
    }

    /**
     * Kaydı kaldırır; adres yeniden gönderim alabilir.
     *
     * @return array<string, mixed>
     */
    public function delete(string $suppressionId, ?string $appId = null): array
    {
        return $this->client->request('DELETE', $this->base($appId) . '/' . rawurlencode($suppressionId));
    }
}
