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
            $body['idempotency_key'] = self::uuid();
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
     * @param array{limit?:int, cursor?:string, source?:string, status?:string} $params
     * @return array<string, mixed>
     */
    public function list(array $params = [], ?string $appId = null): array
    {
        return $this->client->request('GET', '/v1/notifications', ['app_id' => $this->client->appId($appId)] + $params);
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

    private static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
