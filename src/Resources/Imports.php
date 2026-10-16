<?php

declare(strict_types=1);

namespace Pushizo\Resources;

use Pushizo\Client;

/** İçe aktarma işleri (multipart CSV, UTF-8, en fazla 50 MB): OneSignal player dökümü veya kullanıcı CSV'si. */
final class Imports
{
    public function __construct(private readonly Client $client)
    {
    }

    private function base(?string $appId): string
    {
        return $this->client->appPath($appId) . '/imports';
    }

    /**
     * CSV içeriğinden import işi başlatır.
     *
     * @param 'onesignal_players'|'csv_users' $type
     * @return array<string, mixed>
     */
    public function create(string $type, string $csv, string $filename = 'import.csv', ?string $appId = null): array
    {
        return $this->client->request('POST', $this->base($appId), [], null, [
            'multipart' => [
                'type' => $type,
                'file' => ['filename' => $filename, 'content' => $csv, 'content_type' => 'text/csv'],
            ],
        ]);
    }

    /**
     * Diskteki CSV dosyasından import işi başlatır.
     *
     * @param 'onesignal_players'|'csv_users' $type
     * @return array<string, mixed>
     */
    public function createFromFile(string $type, string $path, ?string $appId = null): array
    {
        $content = @file_get_contents($path);
        if ($content === false) {
            throw new \InvalidArgumentException('Pushizo: dosya okunamadı: ' . $path);
        }
        return $this->create($type, $content, basename($path), $appId);
    }

    /**
     * @param array{limit?:int, cursor?:string} $params
     * @return array<string, mixed>
     */
    public function list(array $params = [], ?string $appId = null): array
    {
        return $this->client->request('GET', $this->base($appId), $params);
    }

    /** @return array<string, mixed> */
    public function get(string $importId, ?string $appId = null): array
    {
        return $this->client->request('GET', $this->base($appId) . '/' . rawurlencode($importId));
    }
}
