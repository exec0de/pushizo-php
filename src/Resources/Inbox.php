<?php

declare(strict_types=1);

namespace HeroSignal\Resources;

use HeroSignal\Client;

/** Kullanıcının bildirim kutusu (inbox); kullanıcı alias (`label`/`value`) ile adreslenir. */
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
}
