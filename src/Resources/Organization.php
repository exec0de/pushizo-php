<?php

declare(strict_types=1);

namespace HeroSignal\Resources;

use HeroSignal\Client;

/** Organizasyon uçları (org geneli admin anahtarı): bilgi, üyeler, audit log. */
final class Organization
{
    public function __construct(private readonly Client $client)
    {
    }

    /** @return array<string, mixed> */
    public function get(): array
    {
        return $this->client->request('GET', '/v1/organization');
    }

    /** @return array<string, mixed> */
    public function members(): array
    {
        return $this->client->request('GET', '/v1/organization/members');
    }

    /**
     * @param array{limit?:int, cursor?:string, action?:string, actor_id?:string, app_id?:string, from?:string, to?:string} $params
     * @return array<string, mixed>
     */
    public function auditLogs(array $params = []): array
    {
        return $this->client->request('GET', '/v1/organization/audit-logs', $params);
    }
}
