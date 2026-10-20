<?php

declare(strict_types=1);

namespace Pushizo\Resources;

use Pushizo\Client;

/** Anketler (NPS, CSAT, seçmeli ve metin soruları; ADR-0039): tanım, durum, rapor ve yanıtlar. */
final class Surveys
{
    public function __construct(private readonly Client $client)
    {
    }

    private function base(?string $appId): string
    {
        return $this->client->appPath($appId) . '/surveys';
    }

    /**
     * @param array{limit?:int, cursor?:string, status?:'draft'|'active'|'closed'} $params
     * @return array<string, mixed>
     */
    public function list(array $params = [], ?string $appId = null): array
    {
        return $this->client->request('GET', $this->base($appId), $params);
    }

    /**
     * Anket taslak olarak oluşur; yanıt toplamak için `setStatus($id, 'active')`.
     *
     * @param array{name:string, questions:list<array<string, mixed>>, settings?:array<string, mixed>} $input
     * @return array<string, mixed>
     */
    public function create(array $input, ?string $appId = null): array
    {
        return $this->client->request('POST', $this->base($appId), [], $input);
    }

    /** @return array<string, mixed> */
    public function get(string $surveyId, ?string $appId = null): array
    {
        return $this->client->request('GET', $this->base($appId) . '/' . rawurlencode($surveyId));
    }

    /**
     * Etkinleştir (`active`), kapat (`closed`) veya taslağa al (`draft`).
     *
     * @param 'draft'|'active'|'closed' $status
     * @return array<string, mixed>
     */
    public function setStatus(string $surveyId, string $status, ?string $appId = null): array
    {
        return $this->client->request('POST', $this->base($appId) . '/' . rawurlencode($surveyId) . '/status', [], ['status' => $status]);
    }

    /**
     * NPS / CSAT raporu (son `days` gün).
     *
     * @param array{days?:int} $params
     * @return array<string, mixed>
     */
    public function report(string $surveyId, array $params = [], ?string $appId = null): array
    {
        return $this->client->request('GET', $this->base($appId) . '/' . rawurlencode($surveyId) . '/report', $params);
    }

    /**
     * Yanıtlar (cursor sayfalı).
     *
     * @param array{limit?:int, cursor?:string, text_question?:string, nps_category?:'promoter'|'passive'|'detractor'} $params
     * @return array<string, mixed>
     */
    public function listResponses(string $surveyId, array $params = [], ?string $appId = null): array
    {
        return $this->client->request('GET', $this->base($appId) . '/' . rawurlencode($surveyId) . '/responses', $params);
    }

    /**
     * Sunucu tarafı yanıt; `external_id` ile kullanıcıya bağlanır.
     *
     * @param array{answers:array<string, string|bool|int|float|list<string>>, external_id?:string} $input
     * @return array<string, mixed>
     */
    public function respond(string $surveyId, array $input, ?string $appId = null): array
    {
        return $this->client->request('POST', $this->base($appId) . '/' . rawurlencode($surveyId) . '/responses', [], $input);
    }
}
