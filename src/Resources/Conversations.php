<?php

declare(strict_types=1);

namespace Pushizo\Resources;

use Pushizo\Client;

/**
 * İki yönlü mesajlaşma konuşmaları (SMS, WhatsApp, RCS). WhatsApp'ta son gelen mesajdan sonraki 24 saat dışında
 * yalnızca onaylı şablonla yanıt verilebilir (aksi hâlde 422 `WHATSAPP_WINDOW_CLOSED`).
 */
final class Conversations
{
    public function __construct(private readonly Client $client)
    {
    }

    private function base(?string $appId): string
    {
        return $this->client->appPath($appId) . '/conversations';
    }

    /**
     * Son mesaja göre sıralı konuşmalar (cursor sayfalı).
     *
     * @param array{limit?:int, cursor?:string, channel?:'sms'|'whatsapp'|'rcs', status?:'open'|'closed', unread?:bool, q?:string} $params
     * @return array<string, mixed>
     */
    public function list(array $params = [], ?string $appId = null): array
    {
        return $this->client->request('GET', $this->base($appId), $params);
    }

    /**
     * Konuşma (WhatsApp oturum penceresi ve açık rıza durumu dahil).
     *
     * @return array<string, mixed>
     */
    public function get(string $conversationId, ?string $appId = null): array
    {
        return $this->client->request('GET', $this->base($appId) . '/' . rawurlencode($conversationId));
    }

    /**
     * Mesajlar (yeniden eskiye, cursor sayfalı).
     *
     * @param array{limit?:int, cursor?:string} $params
     * @return array<string, mixed>
     */
    public function messages(string $conversationId, array $params = [], ?string $appId = null): array
    {
        return $this->client->request('GET', $this->base($appId) . '/' . rawurlencode($conversationId) . '/messages', $params);
    }

    /**
     * Yanıt gönderir: `text`, `template` (WhatsApp onaylı şablon) veya `media`.
     *
     * @param array{text?:string, template?:array<string, mixed>, media?:array<string, mixed>} $input
     * @return array<string, mixed>
     */
    public function reply(string $conversationId, array $input, ?string $appId = null): array
    {
        return $this->client->request('POST', $this->base($appId) . '/' . rawurlencode($conversationId) . '/messages', [], $input);
    }

    /**
     * Gelen mesajları okundu işaretler.
     *
     * @return array<string, mixed>
     */
    public function markRead(string $conversationId, ?string $appId = null): array
    {
        return $this->client->request('POST', $this->base($appId) . '/' . rawurlencode($conversationId) . '/read');
    }
}
