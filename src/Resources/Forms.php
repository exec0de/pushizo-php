<?php

declare(strict_types=1);

namespace Pushizo\Resources;

use Pushizo\Client;

/** Formlar (ADR-0039): tanım, gönderim listesi ve sunucu tarafı gönderim. */
final class Forms
{
    public function __construct(private readonly Client $client)
    {
    }

    private function base(?string $appId): string
    {
        return $this->client->appPath($appId) . '/forms';
    }

    /**
     * @param array{limit?:int, cursor?:string, status?:'active'|'archived'} $params
     * @return array<string, mixed>
     */
    public function list(array $params = [], ?string $appId = null): array
    {
        return $this->client->request('GET', $this->base($appId), $params);
    }

    /**
     * @param array{name:string, fields:list<array<string, mixed>>, settings?:array<string, mixed>, status?:'active'|'archived'} $input
     * @return array<string, mixed>
     */
    public function create(array $input, ?string $appId = null): array
    {
        return $this->client->request('POST', $this->base($appId), [], $input);
    }

    /** @return array<string, mixed> */
    public function get(string $formId, ?string $appId = null): array
    {
        return $this->client->request('GET', $this->base($appId) . '/' . rawurlencode($formId));
    }

    /**
     * Gönderimler (cursor sayfalı).
     *
     * @param array{limit?:int, cursor?:string, status?:'accepted'|'pending_confirmation'|'confirmed', since?:string} $params
     * @return array<string, mixed>
     */
    public function listSubmissions(string $formId, array $params = [], ?string $appId = null): array
    {
        return $this->client->request('GET', $this->base($appId) . '/' . rawurlencode($formId) . '/submissions', $params);
    }

    /**
     * Sunucu tarafı gönderim; `external_id` verilirse o kullanıcının profili güncellenir.
     *
     * @param array{values:array<string, string|bool|int|float|list<string>>, external_id?:string, page_url?:string, language?:string} $input
     * @return array<string, mixed>
     */
    public function submit(string $formId, array $input, ?string $appId = null): array
    {
        return $this->client->request('POST', $this->base($appId) . '/' . rawurlencode($formId) . '/submissions', [], $input);
    }
}
