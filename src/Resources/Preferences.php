<?php

declare(strict_types=1);

namespace Pushizo\Resources;

use Pushizo\Client;

/**
 * Mesaj kategorileri ve kullanıcı tercihleri: kategori CRUD'u ve kullanıcının kategori/kanal bazlı
 * opt-in durumu. Kategoriye kapalı kullanıcıya o kategorideki kampanya gönderilmez.
 */
final class Preferences
{
    public function __construct(private readonly Client $client)
    {
    }

    private function base(?string $appId): string
    {
        return $this->client->appPath($appId) . '/categories';
    }

    private function user(string $label, string $value, ?string $appId): string
    {
        return $this->client->appPath($appId) . '/users/by/' . rawurlencode($label) . '/' . rawurlencode($value) . '/preferences';
    }

    /** @return array<string, mixed> */
    public function listCategories(?string $appId = null): array
    {
        return $this->client->request('GET', $this->base($appId));
    }

    /**
     * @param array{key:string, name:array<string,string>, description?:array<string,string>, channels?:?array<int,string>, default_opted_in?:bool, position?:int} $body
     * @return array<string, mixed>
     */
    public function createCategory(array $body, ?string $appId = null): array
    {
        return $this->client->request('POST', $this->base($appId), [], $body);
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function updateCategory(string $categoryId, array $body, ?string $appId = null): array
    {
        return $this->client->request('PATCH', $this->base($appId) . '/' . rawurlencode($categoryId), [], $body);
    }

    /**
     * Kategori silinince kullanıcı tercihleri de silinir (varsayılana dönerler).
     *
     * @return array<string, mixed>
     */
    public function deleteCategory(string $categoryId, ?string $appId = null): array
    {
        return $this->client->request('DELETE', $this->base($appId) . '/' . rawurlencode($categoryId));
    }

    /**
     * Kullanıcının her kategori için etkin tercihi (kanal null = tüm kanallar).
     *
     * @return array<string, mixed>
     */
    public function getUser(string $label, string $value, ?string $appId = null): array
    {
        return $this->client->request('GET', $this->user($label, $value, $appId));
    }

    /**
     * Tercihleri yazar; tanımsız kategori 404 döner.
     *
     * @param array<int, array{category:string, channel?:?string, opted_in:bool}> $preferences
     * @return array<string, mixed>
     */
    public function setUser(string $label, string $value, array $preferences, ?string $appId = null): array
    {
        return $this->client->request('PUT', $this->user($label, $value, $appId), [], ['preferences' => $preferences]);
    }
}
