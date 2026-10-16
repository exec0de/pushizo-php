<?php

declare(strict_types=1);

namespace Pushizo\Resources;

use Pushizo\Client;

/**
 * Veri ambarı dışa aktarımları (BigQuery, Snowflake, S3 Parquet). Kimlik bilgileri (`credentials`) şifreli
 * saklanır ve yanıtlarda dönmez; loglamayın.
 */
final class WarehouseExports
{
    public function __construct(private readonly Client $client)
    {
    }

    private function base(?string $appId): string
    {
        return $this->client->appPath($appId) . '/warehouse-exports';
    }

    /**
     * Dışa aktarılabilir tablolar, kolonlar ve şema sürümleri.
     *
     * @return array<string, mixed>
     */
    public function tables(?string $appId = null): array
    {
        return $this->client->request('GET', $this->base($appId) . '/tables');
    }

    /**
     * Kaydetmeden bağlantı testi.
     *
     * @param array{destination:'bigquery'|'snowflake'|'s3_parquet', config:array<string, mixed>, credentials:array<string, mixed>} $input
     * @return array<string, mixed>
     */
    public function testConnection(array $input, ?string $appId = null): array
    {
        return $this->client->request('POST', $this->base($appId) . '/test-connection', [], $input);
    }

    /** @return array<string, mixed> */
    public function list(?string $appId = null): array
    {
        return $this->client->request('GET', $this->base($appId));
    }

    /**
     * @param array{
     *   name:string, tables:list<string>, destination:'bigquery'|'snowflake'|'s3_parquet', config:array<string, mixed>,
     *   credentials:array<string, mixed>, schedule?:'hourly'|'daily', enabled?:bool
     * } $input
     * @return array<string, mixed>
     */
    public function create(array $input, ?string $appId = null): array
    {
        return $this->client->request('POST', $this->base($appId), [], $input);
    }

    /** @return array<string, mixed> */
    public function get(string $exportId, ?string $appId = null): array
    {
        return $this->client->request('GET', $this->base($appId) . '/' . rawurlencode($exportId));
    }

    /**
     * @param array<string, mixed> $input name, tables, schedule, enabled, config, credentials
     * @return array<string, mixed>
     */
    public function update(string $exportId, array $input, ?string $appId = null): array
    {
        return $this->client->request('PATCH', $this->base($appId) . '/' . rawurlencode($exportId), [], $input);
    }

    /**
     * Dışa aktarımı siler; hedefe yazılmış veriler silinmez.
     *
     * @return array<string, mixed>
     */
    public function delete(string $exportId, ?string $appId = null): array
    {
        return $this->client->request('DELETE', $this->base($appId) . '/' . rawurlencode($exportId));
    }

    /**
     * Kayıtlı dışa aktarımın bağlantı testi.
     *
     * @return array<string, mixed>
     */
    public function test(string $exportId, ?string $appId = null): array
    {
        return $this->client->request('POST', $this->base($appId) . '/' . rawurlencode($exportId) . '/test');
    }

    /**
     * Şimdi çalıştırır (son başarılı imleçten itibaren).
     *
     * @return array<string, mixed>
     */
    public function run(string $exportId, ?string $appId = null): array
    {
        return $this->client->request('POST', $this->base($appId) . '/' . rawurlencode($exportId) . '/run');
    }

    /**
     * Çalıştırma geçmişi (cursor sayfalı).
     *
     * @param array{limit?:int, cursor?:string} $params
     * @return array<string, mixed>
     */
    public function runs(string $exportId, array $params = [], ?string $appId = null): array
    {
        return $this->client->request('GET', $this->base($appId) . '/' . rawurlencode($exportId) . '/runs', $params);
    }
}
