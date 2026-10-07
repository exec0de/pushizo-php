<?php

declare(strict_types=1);

namespace HeroSignal;

/**
 * Giden webhook imza doğrulaması (alıcı tarafı).
 *
 * Başlık: `HeroSignal-Signature: t=<unix>,v1=<hex>` — `v1 = HMAC-SHA256(secret, t . "." . ham_gövde)`.
 * Secret rotasyonunda başlıkta iki `v1` bulunabilir; herhangi biri eşleşirse geçerlidir.
 *
 * ```php
 * $payload = file_get_contents('php://input');               // ham gövde (json_decode etmeden)
 * $header = $_SERVER['HTTP_HEROSIGNAL_SIGNATURE'] ?? null;
 * try {
 *     $events = HeroSignal\Webhook::constructEvents($payload, $header, getenv('HS_WEBHOOK_SECRET'));
 * } catch (HeroSignal\WebhookSignatureException $e) {
 *     http_response_code(400);
 *     exit;
 * }
 * // HeroSignal-Event-Id ile tekrarları ayıklayın; 2xx ile hızlı yanıt verin.
 * http_response_code(204);
 * ```
 */
final class Webhook
{
    /** Varsayılan tolerans (sn): daha eski `t` tekrar gönderim (replay) sayılır. */
    public const DEFAULT_TOLERANCE_SECONDS = 300;

    /**
     * İmzayı doğrular. Geçerliyse null, değilse hata nedenini döner:
     * `missing_header`, `malformed_header`, `timestamp_out_of_tolerance`, `signature_mismatch`.
     *
     * @param string|string[] $secret
     */
    public static function verify(
        string $payload,
        ?string $header,
        string|array $secret,
        int $toleranceSeconds = self::DEFAULT_TOLERANCE_SECONDS,
        ?int $now = null,
    ): ?string {
        if ($header === null || $header === '') {
            return 'missing_header';
        }
        $timestamp = null;
        $signatures = [];
        foreach (explode(',', $header) as $part) {
            $pos = strpos($part, '=');
            if ($pos === false || $pos === 0) {
                continue;
            }
            $key = trim(substr($part, 0, $pos));
            $value = trim(substr($part, $pos + 1));
            if ($key === 't' && ctype_digit($value)) {
                $timestamp = (int) $value;
            } elseif ($key === 'v1' && preg_match('/^[0-9a-f]{64}$/', $value) === 1) {
                $signatures[] = $value;
            }
        }
        if ($timestamp === null || $signatures === []) {
            return 'malformed_header';
        }
        if (abs(($now ?? time()) - $timestamp) > $toleranceSeconds) {
            return 'timestamp_out_of_tolerance';
        }
        foreach ((array) $secret as $s) {
            $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $s);
            foreach ($signatures as $sig) {
                if (hash_equals($expected, $sig)) {
                    return null;
                }
            }
        }
        return 'signature_mismatch';
    }

    /**
     * Doğrular ve olay listesini döner (tekil gövde veya `{ "events": [...] }` batch gövdesi).
     *
     * @param string|string[] $secret
     * @return array<int, array<string, mixed>>
     * @throws WebhookSignatureException
     */
    public static function constructEvents(
        string $payload,
        ?string $header,
        string|array $secret,
        int $toleranceSeconds = self::DEFAULT_TOLERANCE_SECONDS,
        ?int $now = null,
    ): array {
        $reason = self::verify($payload, $header, $secret, $toleranceSeconds, $now);
        if ($reason !== null) {
            throw new WebhookSignatureException($reason);
        }
        $body = json_decode($payload, true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($body)) {
            throw new WebhookSignatureException('malformed_body');
        }
        return isset($body['events']) && is_array($body['events']) ? $body['events'] : [$body];
    }
}
