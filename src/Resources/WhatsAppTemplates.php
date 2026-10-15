<?php

declare(strict_types=1);

namespace HeroSignal\Resources;

use HeroSignal\Client;

/** WhatsApp mesaj şablonları (Meta onay durumu ile); `sync` şablonları Meta Graph API'den yeniden çeker. */
final class WhatsAppTemplates
{
    public function __construct(private readonly Client $client)
    {
    }

    private function base(?string $appId): string
    {
        return $this->client->appPath($appId) . '/whatsapp/templates';
    }

    /**
     * @param array{status?:string} $params
     * @return array<string, mixed>
     */
    public function list(array $params = [], ?string $appId = null): array
    {
        return $this->client->request('GET', $this->base($appId), $params);
    }

    /** @return array<string, mixed> */
    public function sync(?string $appId = null): array
    {
        return $this->client->request('POST', $this->base($appId) . '/sync');
    }
}
