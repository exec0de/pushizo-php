<?php

declare(strict_types=1);

namespace HeroSignal\Resources;

use HeroSignal\Client;

/** Users API: /v1/apps/{app}/users… */
final class Users
{
    public function __construct(private readonly Client $client)
    {
    }

    private function base(?string $appId): string
    {
        return '/v1/apps/' . rawurlencode($this->client->appId($appId)) . '/users';
    }

    /**
     * @param array{limit?:int, cursor?:string, q?:string, tag?:string} $params
     * @return array<string, mixed>
     */
    public function list(array $params = [], ?string $appId = null): array
    {
        return $this->client->request('GET', $this->base($appId), $params);
    }

    /**
     * Oluştur/güncelle: `identity.external_id` veya `external_id`, tags, properties, subscriptions.
     *
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function upsert(array $body, ?string $appId = null): array
    {
        return $this->client->request('POST', $this->base($appId), [], $body);
    }

    /** @return array<string, mixed> */
    public function getByAlias(string $label, string $value, ?string $appId = null): array
    {
        return $this->client->request('GET', $this->base($appId) . '/by/' . rawurlencode($label) . '/' . rawurlencode($value));
    }

    /** @return array<string, mixed> */
    public function getByExternalId(string $externalId, ?string $appId = null): array
    {
        return $this->getByAlias('external_id', $externalId, $appId);
    }

    /**
     * @param array{tags?:array<string,?string>, properties?:array<string,mixed>, deltas?:array{session_count?:int}} $body
     * @return array<string, mixed>
     */
    public function update(string $label, string $value, array $body, ?string $appId = null): array
    {
        return $this->client->request('PATCH', $this->base($appId) . '/by/' . rawurlencode($label) . '/' . rawurlencode($value), [], $body);
    }

    /** @return array<string, mixed> */
    public function delete(string $label, string $value, ?string $appId = null): array
    {
        return $this->client->request('DELETE', $this->base($appId) . '/by/' . rawurlencode($label) . '/' . rawurlencode($value));
    }

    /**
     * @param array<string, string> $identity
     * @return array<string, mixed>
     */
    public function addAliases(string $label, string $value, array $identity, ?string $appId = null): array
    {
        return $this->client->request('PATCH', $this->base($appId) . '/by/' . rawurlencode($label) . '/' . rawurlencode($value) . '/identity', [], ['identity' => $identity]);
    }

    /** @return array<string, mixed> */
    public function removeAlias(string $label, string $value, string $aliasLabel, ?string $appId = null): array
    {
        return $this->client->request('DELETE', $this->base($appId) . '/by/' . rawurlencode($label) . '/' . rawurlencode($value) . '/identity/' . rawurlencode($aliasLabel));
    }

    /**
     * @param array{channel:string, token:string, device?:array<string,mixed>, status?:string} $subscription
     * @return array<string, mixed>
     */
    public function addSubscription(string $label, string $value, array $subscription, ?string $appId = null): array
    {
        return $this->client->request('POST', $this->base($appId) . '/by/' . rawurlencode($label) . '/' . rawurlencode($value) . '/subscriptions', [], $subscription);
    }
}
