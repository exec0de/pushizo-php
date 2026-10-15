<?php

declare(strict_types=1);

namespace HeroSignal\Resources;

use HeroSignal\Client;

/**
 * E-posta teslim edilebilirliği: BCC/arşiv, gönderim öncesi adres doğrulama, üçüncü taraf doğrulayıcı ve
 * alan adı/IP ısındırma planları. Doğrulayıcı API anahtarı zarf şifreli saklanır ve geri okunmaz.
 */
final class EmailDeliverability
{
    public function __construct(private readonly Client $client)
    {
    }

    private function base(?string $appId): string
    {
        return $this->client->appPath($appId) . '/email-deliverability';
    }

    /** @return array<string, mixed> */
    public function get(?string $appId = null): array
    {
        return $this->client->request('GET', $this->base($appId));
    }

    /**
     * @param array{bcc_address?:?string, validation?:array<string, mixed>, verifier?:array<string, mixed>} $input
     * @return array<string, mixed>
     */
    public function update(array $input, ?string $appId = null): array
    {
        return $this->client->request('PUT', $this->base($appId), [], $input);
    }

    /**
     * Adres doğrulama testi (sözdizimi, MX, tek kullanımlık alan adı, rol hesabı, üçüncü taraf).
     *
     * @return array<string, mixed>
     */
    public function verifyAddress(string $address, ?string $appId = null): array
    {
        return $this->client->request('POST', $this->base($appId) . '/verify-address', [], ['address' => $address]);
    }

    /**
     * Isındırma planları (bugünkü limit/kullanım ve ileri günlere ayrılmış gönderimler).
     *
     * @return array<string, mixed>
     */
    public function listWarmupPlans(?string $appId = null): array
    {
        return $this->client->request('GET', $this->base($appId) . '/warmup-plans');
    }

    /**
     * Günlük limit eğrisi (`daily_limits`) veya başlangıç/çarpan/hedef (`curve`) ile plan oluşturur.
     *
     * @param array{
     *   name:string, kind:'domain'|'ip', target:string, start_date?:string, daily_limits?:list<int>,
     *   curve?:array<string, mixed>, provider_shares?:array<string, mixed>, timezone?:string
     * } $input
     * @return array<string, mixed>
     */
    public function createWarmupPlan(array $input, ?string $appId = null): array
    {
        return $this->client->request('POST', $this->base($appId) . '/warmup-plans', [], $input);
    }

    /** @return array<string, mixed> */
    public function getWarmupPlan(string $planId, ?string $appId = null): array
    {
        return $this->client->request('GET', $this->base($appId) . '/warmup-plans/' . rawurlencode($planId));
    }

    /**
     * Planın gün × sağlayıcı kullanımı (son `$days` gün, 1–120).
     *
     * @return array<string, mixed>
     */
    public function warmupPlanUsage(string $planId, ?int $days = null, ?string $appId = null): array
    {
        return $this->client->request('GET', $this->base($appId) . '/warmup-plans/' . rawurlencode($planId) . '/usage', $days !== null ? ['days' => $days] : []);
    }

    /**
     * Planı günceller (duraklat/sürdür, eğri, paylar).
     *
     * @param array{name?:string, status?:'active'|'paused', daily_limits?:list<int>, provider_shares?:?array<string, mixed>} $input
     * @return array<string, mixed>
     */
    public function updateWarmupPlan(string $planId, array $input, ?string $appId = null): array
    {
        return $this->client->request('PATCH', $this->base($appId) . '/warmup-plans/' . rawurlencode($planId), [], $input);
    }

    /** @return array<string, mixed> */
    public function deleteWarmupPlan(string $planId, ?string $appId = null): array
    {
        return $this->client->request('DELETE', $this->base($appId) . '/warmup-plans/' . rawurlencode($planId));
    }
}
