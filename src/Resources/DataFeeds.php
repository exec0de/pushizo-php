<?php

declare(strict_types=1);

namespace HeroSignal\Resources;

use HeroSignal\Client;

/**
 * Data feed'ler (Connected Content): gönderim anında dış JSON uç noktasından içerik çeker; şablonda
 * `{{ feed.<ad>.alan }}` olarak kullanılır. `secret_headers` değerleri şifreli saklanır ve geri okunmaz.
 */
final class DataFeeds
{
    public function __construct(private readonly Client $client)
    {
    }

    private function base(?string $appId): string
    {
        return $this->client->appPath($appId) . '/data-feeds';
    }

    /** @return array<string, mixed> */
    public function list(?string $appId = null): array
    {
        return $this->client->request('GET', $this->base($appId));
    }

    /**
     * @param array{
     *   name:string, url_template:string, description?:?string, method?:'GET'|'POST', body_template?:?string,
     *   headers?:array<string, string>, secret_headers?:array<string, string>, cache_ttl_seconds?:int,
     *   timeout_ms?:int, enabled?:bool
     * } $input
     * @return array<string, mixed>
     */
    public function create(array $input, ?string $appId = null): array
    {
        return $this->client->request('POST', $this->base($appId), [], $input);
    }

    /** @return array<string, mixed> */
    public function get(string $feedId, ?string $appId = null): array
    {
        return $this->client->request('GET', $this->base($appId) . '/' . rawurlencode($feedId));
    }

    /**
     * @param array<string, mixed> $input description, url_template, method, body_template, headers, secret_headers, cache_ttl_seconds, timeout_ms, enabled
     * @return array<string, mixed>
     */
    public function update(string $feedId, array $input, ?string $appId = null): array
    {
        return $this->client->request('PATCH', $this->base($appId) . '/' . rawurlencode($feedId), [], $input);
    }

    /** @return array<string, mixed> */
    public function delete(string $feedId, ?string $appId = null): array
    {
        return $this->client->request('DELETE', $this->base($appId) . '/' . rawurlencode($feedId));
    }

    /**
     * Feed'i örnek kullanıcıyla dener; boş girdi JSON nesnesi (`{}`) olarak gönderilir.
     *
     * @param array{external_id?:string, user_id?:string} $input
     * @return array<string, mixed>
     */
    public function test(string $feedId, array $input = [], ?string $appId = null): array
    {
        return $this->client->request('POST', $this->base($appId) . '/' . rawurlencode($feedId) . '/test', [], (object) $input);
    }
}
