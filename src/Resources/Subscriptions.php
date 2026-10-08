<?php

declare(strict_types=1);

namespace HeroSignal\Resources;

use HeroSignal\Client;

/** Subscriptions API: /v1/apps/{app}/subscriptions/{id} */
final class Subscriptions
{
    public function __construct(private readonly Client $client)
    {
    }

    private function base(?string $appId, string $subscriptionId): string
    {
        return '/v1/apps/' . rawurlencode($this->client->appId($appId)) . '/subscriptions/' . rawurlencode($subscriptionId);
    }

    /** @return array<string, mixed> */
    public function get(string $subscriptionId, ?string $appId = null): array
    {
        return $this->client->request('GET', $this->base($appId, $subscriptionId));
    }

    /**
     * @param array{token?:string, device?:array<string,mixed>, status?:string, test_device?:bool} $body
     * @return array<string, mixed>
     */
    public function update(string $subscriptionId, array $body, ?string $appId = null): array
    {
        return $this->client->request('PATCH', $this->base($appId, $subscriptionId), [], $body);
    }

    /** @return array<string, mixed> */
    public function delete(string $subscriptionId, ?string $appId = null): array
    {
        return $this->client->request('DELETE', $this->base($appId, $subscriptionId));
    }

    /** @return array<string, mixed> */
    public function setOwner(string $subscriptionId, string $externalId, ?string $appId = null): array
    {
        return $this->client->request('POST', $this->base($appId, $subscriptionId) . '/owner', [], ['external_id' => $externalId]);
    }

    /**
     * Aboneliğin sahibi kullanıcının kimlikleri (kullanıcısız abonelikte 404).
     *
     * @return array{identity: array<string, string>}
     */
    public function identity(string $subscriptionId, ?string $appId = null): array
    {
        /** @var array{identity: array<string, string>} $result */
        $result = $this->client->request('GET', $this->base($appId, $subscriptionId) . '/user/identity');
        return $result;
    }

    /**
     * Aboneliği kanal + token ile güncelle. Kanal: ios_push, android_push, email, sms (token kanonik biçime getirilir).
     *
     * @param array{token?:string, device?:array<string,mixed>, status?:string, test_device?:bool} $body
     * @return array<string, mixed>
     */
    public function updateByToken(string $channel, string $token, array $body, ?string $appId = null): array
    {
        $path = '/v1/apps/' . rawurlencode($this->client->appId($appId)) . '/subscriptions/by-token/' . rawurlencode($channel) . '/' . rawurlencode($token);
        return $this->client->request('PATCH', $path, [], $body);
    }
}
