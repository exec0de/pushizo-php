<?php

declare(strict_types=1);

namespace HeroSignal\Resources;

use HeroSignal\Client;

/** POST /v1/notifications ve ilgili uçlar. */
final class Notifications
{
    public function __construct(private readonly Client $client)
    {
    }

    /**
     * Bildirim oluşturur (202). `idempotency_key` verilmezse otomatik UUID eklenir.
     *
     * @param array<string, mixed> $input OneSignal uyumlu gövde: target, headings, contents, url, data, ttl, …;
     *   e-posta: email_subject, email_body, email_mjml, email_preheader, email_from_name, email_from_address,
     *   email_reply_to_address, disable_email_click_tracking; SMS: sms_from, disable_sms_link_shortening;
     *   bildirim kutusu: add_to_inbox, inbox_ttl (saniye)
     * @return array{id:string, status:string, recipients_estimate:int, external_id_missing:array<int,string>}
     */
    public function create(array $input, ?string $appId = null): array
    {
        $body = $input;
        $body['app_id'] = $this->client->appId($appId ?? ($input['app_id'] ?? null));
        if (!isset($body['idempotency_key']) && $this->client->autoIdempotencyKey()) {
            $body['idempotency_key'] = Client::uuid();
        }
        /** @var array{id:string, status:string, recipients_estimate:int, external_id_missing:array<int,string>} $result */
        $result = $this->client->request('POST', '/v1/notifications', [], $body);
        return $result;
    }

    /** @return array<string, mixed> */
    public function get(string $messageId, ?string $appId = null): array
    {
        return $this->client->request('GET', '/v1/notifications/' . rawurlencode($messageId), ['app_id' => $this->client->appId($appId)]);
    }

    /**
     * @param array{limit?:int, cursor?:string, source?:string, kind?:string, status?:string, sort?:string} $params
     * @return array<string, mixed>
     */
    public function list(array $params = [], ?string $appId = null): array
    {
        return $this->client->request('GET', '/v1/notifications', ['app_id' => $this->client->appId($appId)] + $params);
    }

    /**
     * Kaydedilmemiş hedef için alıcı sayısı (gönderim yapmaz).
     *
     * @param array{target:array<string, mixed>, include_unsubscribed?:bool} $input
     * @return array<string, mixed>
     */
    public function estimate(array $input, ?string $appId = null): array
    {
        $body = $input;
        $body['app_id'] = $this->client->appId($appId ?? ($input['app_id'] ?? null));
        return $this->client->request('POST', '/v1/notifications/estimate', [], $body);
    }

    /** @return array<string, mixed> */
    public function cancel(string $messageId, ?string $appId = null): array
    {
        return $this->client->request('DELETE', '/v1/notifications/' . rawurlencode($messageId), ['app_id' => $this->client->appId($appId)]);
    }

    /**
     * @param array{limit?:int, cursor?:string, status?:string} $params
     * @return array<string, mixed>
     */
    public function deliveries(string $messageId, array $params = [], ?string $appId = null): array
    {
        return $this->client->request('GET', '/v1/notifications/' . rawurlencode($messageId) . '/deliveries', ['app_id' => $this->client->appId($appId)] + $params);
    }

    /**
     * Bildirim geçmişi (teslimatlar) CSV dışa aktarma işi; `status === 'completed'` olunca `download_url` dolar.
     *
     * @return array<string, mixed>
     */
    public function history(string $messageId, ?string $appId = null): array
    {
        return $this->client->request('GET', '/v1/notifications/' . rawurlencode($messageId) . '/history', ['app_id' => $this->client->appId($appId)]);
    }

    /**
     * Mesaj olayları (gönderildi/teslim/açıldı/tıklandı/bounce…) CSV dışa aktarma işi (202).
     *
     * @return array<string, mixed>
     */
    public function exportEvents(string $messageId, ?string $appId = null): array
    {
        return $this->client->request('POST', '/v1/notifications/' . rawurlencode($messageId) . '/export_events', ['app_id' => $this->client->appId($appId)]);
    }
}
