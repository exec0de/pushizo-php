<?php

declare(strict_types=1);

namespace Pushizo\Resources;

use Pushizo\Client;

/** Segmentler (`segments:read`; yazma `admin`): filtre AST'si (all/any/none, { field, op, value }) ile kitleler. */
final class Segments
{
    public function __construct(private readonly Client $client)
    {
    }

    private function base(?string $appId): string
    {
        return $this->client->appPath($appId) . '/segments';
    }

    /**
     * @param array{limit?:int, cursor?:string, q?:string} $params
     * @return array<string, mixed>
     */
    public function list(array $params = [], ?string $appId = null): array
    {
        return $this->client->request('GET', $this->base($appId), $params);
    }

    /**
     * @param array{name:string, description?:string, definition:array<string, mixed>} $body
     * @return array<string, mixed>
     */
    public function create(array $body, ?string $appId = null): array
    {
        return $this->client->request('POST', $this->base($appId), [], $body);
    }

    /**
     * Kaydedilmemiş tanım için sayım ve örnek kullanıcılar.
     *
     * @param array{definition:array<string, mixed>} $body
     * @return array<string, mixed>
     */
    public function preview(array $body, ?string $appId = null): array
    {
        return $this->client->request('POST', $this->base($appId) . '/preview', [], $body);
    }

    /** @return array<string, mixed> */
    public function get(string $segmentId, ?string $appId = null): array
    {
        return $this->client->request('GET', $this->base($appId) . '/' . rawurlencode($segmentId));
    }

    /**
     * @param array{name?:string, description?:string, definition?:array<string, mixed>} $body
     * @return array<string, mixed>
     */
    public function update(string $segmentId, array $body, ?string $appId = null): array
    {
        return $this->client->request('PATCH', $this->base($appId) . '/' . rawurlencode($segmentId), [], $body);
    }

    /** @return array<string, mixed> */
    public function delete(string $segmentId, ?string $appId = null): array
    {
        return $this->client->request('DELETE', $this->base($appId) . '/' . rawurlencode($segmentId));
    }

    /**
     * Segmenti kullanan kampanyalar.
     *
     * @return array<string, mixed>
     */
    public function usedBy(string $segmentId, ?string $appId = null): array
    {
        return $this->client->request('GET', $this->base($appId) . '/' . rawurlencode($segmentId) . '/campaigns');
    }

    /**
     * Tahmini sayım (10 dk önbellek); `$force = true` önbelleği atlayıp tam sayım yapar.
     *
     * @return array<string, mixed>
     */
    public function estimate(string $segmentId, bool $force = false, ?string $appId = null): array
    {
        return $this->client->request('POST', $this->base($appId) . '/' . rawurlencode($segmentId) . '/estimate', ['force' => $force]);
    }
}
