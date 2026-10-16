<?php

declare(strict_types=1);

namespace Pushizo\Resources;

use Pushizo\Client;

/** MCP onaylı mod taslakları: gönderim yapmadan kampanya ve dinamik segment taslağı oluşturur. */
final class Mcp
{
    public function __construct(private readonly Client $client)
    {
    }

    private function base(?string $appId): string
    {
        return $this->client->appPath($appId) . '/mcp';
    }

    /**
     * Kampanya taslağı (gövde `campaigns->create` ile aynıdır; gönderim yapılmaz).
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function createCampaignDraft(array $input, ?string $appId = null): array
    {
        return $this->client->request('POST', $this->base($appId) . '/campaign-drafts', [], $input);
    }

    /**
     * Dinamik segment taslağı (gövde `segments->create` ile aynıdır).
     *
     * @param array<string, mixed> $input name, definition, description…
     * @return array<string, mixed>
     */
    public function createSegmentDraft(array $input, ?string $appId = null): array
    {
        return $this->client->request('POST', $this->base($appId) . '/segment-drafts', [], $input);
    }
}
