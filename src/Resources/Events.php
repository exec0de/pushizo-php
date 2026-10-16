<?php

declare(strict_types=1);

namespace Pushizo\Resources;

use Pushizo\Client;

/** Events API: toplu olay gönderimi (en fazla 500) ve olay tanımları. */
final class Events
{
    public function __construct(private readonly Client $client)
    {
    }

    /**
     * @param array<int, array{name:string, external_id?:string, subscription_id?:string, properties?:array<string,mixed>, value?:float, occurred_at?:string}> $events
     * @return array{accepted:int}
     */
    public function send(array $events, ?string $appId = null): array
    {
        /** @var array{accepted:int} $result */
        $result = $this->client->request('POST', '/v1/apps/' . rawurlencode($this->client->appId($appId)) . '/events', [], ['events' => $events]);
        return $result;
    }

    /** @return array<string, mixed> */
    public function listDefinitions(?string $appId = null): array
    {
        return $this->client->request('GET', $this->client->appPath($appId) . '/events/definitions');
    }

    /** @return array<string, mixed> */
    public function getDefinition(string $name, ?string $appId = null): array
    {
        return $this->client->request('GET', $this->client->appPath($appId) . '/events/definitions/' . rawurlencode($name));
    }

    /**
     * Görünen ad ve dönüşüm işareti.
     *
     * @param array{display_name?:?string, is_conversion?:bool} $body
     * @return array<string, mixed>
     */
    public function updateDefinition(string $name, array $body, ?string $appId = null): array
    {
        return $this->client->request('PATCH', $this->client->appPath($appId) . '/events/definitions/' . rawurlencode($name), [], $body);
    }
}
