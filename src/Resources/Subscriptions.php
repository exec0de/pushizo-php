<?php

declare(strict_types=1);

namespace Pushizo\Resources;

use Pushizo\Client;

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

    /**
     * Aboneliğin sahibi kullanıcının kimlikleri.
     *
     * @return array<string, mixed>
     */
    public function getUserIdentity(string $subscriptionId, ?string $appId = null): array
    {
        return $this->client->request('GET', $this->base($appId, $subscriptionId) . '/user/identity');
    }

    /**
     * Token ile güncelleme. `$tokenType`: ios_push, android_push, web_push, email, sms veya OneSignal
     * adları (iOSPush, AndroidPush, ChromePush, FirefoxPush, SafariPush, Email, SMS).
     *
     * @param array{token?:string, device?:array<string,mixed>, status?:string, test_device?:bool} $body
     * @return array<string, mixed>
     */
    public function updateByToken(string $tokenType, string $token, array $body, ?string $appId = null): array
    {
        $path = '/v1/apps/' . rawurlencode($this->client->appId($appId)) . '/subscriptions_by_token/' . rawurlencode($tokenType) . '/' . rawurlencode($token);
        return $this->client->request('PATCH', $path, [], $body);
    }

    /** @return array<string, mixed> */
    public function setOwner(string $subscriptionId, string $externalId, ?string $appId = null): array
    {
        return $this->client->request('POST', $this->base($appId, $subscriptionId) . '/owner', [], ['external_id' => $externalId]);
    }
}
