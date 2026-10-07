<?php

declare(strict_types=1);

namespace HeroSignal\Resources;

use HeroSignal\Client;

/** Events API: POST /v1/apps/{app}/events (en fazla 500 olay). */
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
}
