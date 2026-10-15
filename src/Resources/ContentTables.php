<?php

declare(strict_types=1);

namespace HeroSignal\Resources;

use HeroSignal\Client;

/**
 * İçerik tabloları (Content Catalogs): UTF-8 CSV ile multipart/form-data olarak yüklenir, şablonda anahtar
 * sütunuyla aranır. Yeniden yükleme tablonun sürümünü artırır.
 */
final class ContentTables
{
    public function __construct(private readonly Client $client)
    {
    }

    private function base(?string $appId): string
    {
        return $this->client->appPath($appId) . '/content-tables';
    }

    /** @return array<string, mixed> */
    public function list(?string $appId = null): array
    {
        return $this->client->request('GET', $this->base($appId));
    }

    /**
     * CSV içeriğinden tablo oluşturur.
     *
     * @param array{name:string, key_column:string, description?:string} $input `name`: Liquid tablo adı (küçük harf, rakam, alt çizgi)
     * @return array<string, mixed>
     */
    public function create(array $input, string $csv, string $filename = 'table.csv', ?string $appId = null): array
    {
        $fields = ['name' => $input['name'], 'key_column' => $input['key_column']];
        if (isset($input['description'])) {
            $fields['description'] = $input['description'];
        }
        $fields['file'] = ['filename' => $filename, 'content' => $csv, 'content_type' => 'text/csv'];
        return $this->client->request('POST', $this->base($appId), [], null, ['multipart' => $fields]);
    }

    /**
     * Diskteki CSV dosyasından tablo oluşturur.
     *
     * @param array{name:string, key_column:string, description?:string} $input
     * @return array<string, mixed>
     */
    public function createFromFile(array $input, string $path, ?string $appId = null): array
    {
        return $this->create($input, self::readFile($path), basename($path), $appId);
    }

    /** @return array<string, mixed> */
    public function get(string $tableId, ?string $appId = null): array
    {
        return $this->client->request('GET', $this->base($appId) . '/' . rawurlencode($tableId));
    }

    /**
     * Tablo açıklamasını günceller (`description`; `null` siler).
     *
     * @param array{description?:string|null} $input
     * @return array<string, mixed>
     */
    public function update(string $tableId, array $input, ?string $appId = null): array
    {
        return $this->client->request('PATCH', $this->base($appId) . '/' . rawurlencode($tableId), [], $input);
    }

    /** @return array<string, mixed> */
    public function delete(string $tableId, ?string $appId = null): array
    {
        return $this->client->request('DELETE', $this->base($appId) . '/' . rawurlencode($tableId));
    }

    /**
     * Tablo satırları (offset sayfalı; `q` ile arama).
     *
     * @param array{limit?:int, offset?:int, q?:string} $params
     * @return array<string, mixed>
     */
    public function rows(string $tableId, array $params = [], ?string $appId = null): array
    {
        return $this->client->request('GET', $this->base($appId) . '/' . rawurlencode($tableId) . '/rows', $params);
    }

    /**
     * Tüm satırları yeni CSV ile değiştirir (sürüm artar); `$keyColumn` verilirse anahtar sütunu da değişir.
     *
     * @return array<string, mixed>
     */
    public function replaceRows(string $tableId, string $csv, ?string $keyColumn = null, string $filename = 'table.csv', ?string $appId = null): array
    {
        $fields = [];
        if ($keyColumn !== null) {
            $fields['key_column'] = $keyColumn;
        }
        $fields['file'] = ['filename' => $filename, 'content' => $csv, 'content_type' => 'text/csv'];
        return $this->client->request('PUT', $this->base($appId) . '/' . rawurlencode($tableId) . '/rows', [], null, ['multipart' => $fields]);
    }

    /**
     * Diskteki CSV dosyasıyla satırları değiştirir.
     *
     * @return array<string, mixed>
     */
    public function replaceRowsFromFile(string $tableId, string $path, ?string $keyColumn = null, ?string $appId = null): array
    {
        return $this->replaceRows($tableId, self::readFile($path), $keyColumn, basename($path), $appId);
    }

    private static function readFile(string $path): string
    {
        $content = @file_get_contents($path);
        if ($content === false) {
            throw new \InvalidArgumentException('HeroSignal: dosya okunamadı: ' . $path);
        }
        return $content;
    }
}
