<?php

declare(strict_types=1);

namespace HeroSignal;

use HeroSignal\Transport\CurlTransport;
use HeroSignal\Transport\TransportInterface;

/**
 * HeroSignal PHP SDK istemcisi.
 *
 * ```php
 * $hs = new \HeroSignal\Client(['api_key' => getenv('HS_API_KEY'), 'app_id' => 'app_…']);
 * $hs->notifications->create([
 *   'idempotency_key' => "order-$id",
 *   'target' => ['include_aliases' => ['external_id' => [$userId]]],
 *   'contents' => ['en' => 'Shipped', 'tr' => 'Kargoya verildi'],
 * ]);
 * ```
 */
final class Client
{
    public const VERSION = '0.1.0';
    private const BACKOFF_MS = [300, 1000, 3000];

    private readonly string $apiKey;
    private readonly string $baseUrl;
    private readonly ?string $defaultAppId;
    private readonly int $maxRetries;
    private readonly int $timeoutSeconds;
    private readonly bool $autoIdempotencyKey;
    private readonly TransportInterface $transport;
    /** @var callable(int):void */
    private $sleep;

    public readonly Resources\Notifications $notifications;
    public readonly Resources\Users $users;
    public readonly Resources\Subscriptions $subscriptions;
    public readonly Resources\Events $events;
    public readonly Resources\Channels $channels;
    public readonly Resources\Apps $apps;
    public readonly Resources\Suppressions $suppressions;
    public readonly Resources\Organization $organization;
    public readonly Resources\ApiKeys $apiKeys;
    public readonly Resources\Webhooks $webhooks;
    public readonly Resources\Segments $segments;
    public readonly Resources\Templates $templates;
    public readonly Resources\Campaigns $campaigns;
    public readonly Resources\InAppMessages $inAppMessages;
    public readonly Resources\Inbox $inbox;
    public readonly Resources\Analytics $analytics;
    public readonly Resources\Exports $exports;
    public readonly Resources\Imports $imports;

    /**
     * @param array{
     *   api_key:string, base_url?:string, app_id?:string, max_retries?:int, timeout?:int,
     *   auto_idempotency_key?:bool, transport?:TransportInterface, sleep?:callable
     * } $options
     */
    public function __construct(array $options)
    {
        if (!isset($options['api_key']) || $options['api_key'] === '') {
            throw new \InvalidArgumentException('HeroSignal: api_key gerekli.');
        }
        $this->apiKey = $options['api_key'];
        $this->baseUrl = rtrim($options['base_url'] ?? 'https://api.herosignal.dev', '/');
        $this->defaultAppId = $options['app_id'] ?? null;
        $this->maxRetries = $options['max_retries'] ?? count(self::BACKOFF_MS);
        $this->timeoutSeconds = $options['timeout'] ?? 30;
        $this->autoIdempotencyKey = $options['auto_idempotency_key'] ?? true;
        $this->transport = $options['transport'] ?? new CurlTransport();
        $this->sleep = $options['sleep'] ?? static function (int $ms): void {
            usleep($ms * 1000);
        };

        $this->notifications = new Resources\Notifications($this);
        $this->users = new Resources\Users($this);
        $this->subscriptions = new Resources\Subscriptions($this);
        $this->events = new Resources\Events($this);
        $this->channels = new Resources\Channels($this);
        $this->apps = new Resources\Apps($this);
        $this->suppressions = new Resources\Suppressions($this);
        $this->organization = new Resources\Organization($this);
        $this->apiKeys = new Resources\ApiKeys($this);
        $this->webhooks = new Resources\Webhooks($this);
        $this->segments = new Resources\Segments($this);
        $this->templates = new Resources\Templates($this);
        $this->campaigns = new Resources\Campaigns($this);
        $this->inAppMessages = new Resources\InAppMessages($this);
        $this->inbox = new Resources\Inbox($this);
        $this->analytics = new Resources\Analytics($this);
        $this->exports = new Resources\Exports($this);
        $this->imports = new Resources\Imports($this);
    }

    /** SDK identity verification: `identity_hash` = HMAC-SHA256(sdk_secret, external_id). */
    public static function identityHash(string $sdkSecret, string $externalId): string
    {
        return hash_hmac('sha256', $externalId, $sdkSecret);
    }

    public function appId(?string $appId = null): string
    {
        $id = $appId ?? $this->defaultAppId;
        if ($id === null || $id === '') {
            throw new \InvalidArgumentException('HeroSignal: app_id verilmedi (seçenek veya çağrı parametresi).');
        }
        return $id;
    }

    public function autoIdempotencyKey(): bool
    {
        return $this->autoIdempotencyKey;
    }

    /** `/v1/apps/{appId}` yol öneki (kodlanmış; varsayılan app kullanılabilir). */
    public function appPath(?string $appId = null): string
    {
        return '/v1/apps/' . rawurlencode($this->appId($appId));
    }

    /**
     * Cursor sayfalı liste çağrısını öğe öğe dolaşır.
     *
     * ```php
     * foreach ($hs->paginate(fn (?string $c) => $hs->templates->list(['cursor' => $c, 'limit' => 100])) as $tpl) { … }
     * ```
     *
     * @param callable(?string):array<string, mixed> $fetchPage
     * @return \Generator<int, mixed>
     */
    public function paginate(callable $fetchPage, ?string $cursor = null): \Generator
    {
        while (true) {
            $page = $fetchPage($cursor);
            foreach (($page['data'] ?? []) as $item) {
                yield $item;
            }
            $next = $page['next_cursor'] ?? null;
            if (empty($page['has_more']) || !is_string($next) || $next === '') {
                return;
            }
            $cursor = $next;
        }
    }

    /** RFC 4122 sürüm 4 UUID (otomatik idempotency anahtarı için). */
    public static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    /**
     * JSON istek; 429/5xx ve ağ hatalarında üstel geri çekilme (Retry-After'a saygılı).
     *
     * @param array<string, scalar|null> $query
     * @param array{idempotency_key?:string, multipart?:array<string, string|array{filename:string, content:string, content_type?:string}>} $options
     *   `idempotency_key` → `Idempotency-Key` başlığı; `multipart` verilirse gövde multipart/form-data olarak gönderilir.
     * @return array<string, mixed>
     */
    public function request(string $method, string $path, array $query = [], mixed $body = null, array $options = []): array
    {
        $raw = $this->send($method, $path, $query, $body, $options);
        if ($raw === '') {
            return [];
        }
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        return $decoded;
    }

    /**
     * Yanıt gövdesini ham metin olarak döndürür (ör. `format=csv` raporları).
     *
     * @param array<string, scalar|null> $query
     * @param array{idempotency_key?:string, multipart?:array<string, string|array{filename:string, content:string, content_type?:string}>} $options
     */
    public function requestText(string $method, string $path, array $query = [], mixed $body = null, array $options = []): string
    {
        return $this->send($method, $path, $query, $body, $options);
    }

    /**
     * @param array<string, scalar|null> $query
     * @param array{idempotency_key?:string, multipart?:array<string, string|array{filename:string, content:string, content_type?:string}>} $options
     */
    private function send(string $method, string $path, array $query, mixed $body, array $options): string
    {
        $url = $this->baseUrl . $path;
        $filtered = [];
        foreach ($query as $key => $value) {
            if ($value === null) {
                continue;
            }
            // Sözleşmedeki "true"/"false" sorgu değerleri için boolean'lar metne çevrilir.
            $filtered[$key] = is_bool($value) ? ($value ? 'true' : 'false') : $value;
        }
        if ($filtered !== []) {
            $url .= '?' . http_build_query($filtered);
        }
        $headers = [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Accept' => 'application/json',
            'User-Agent' => 'herosignal-php/' . self::VERSION,
        ];
        if (isset($options['idempotency_key']) && $options['idempotency_key'] !== '') {
            $headers['Idempotency-Key'] = $options['idempotency_key'];
        }
        $payload = null;
        if (isset($options['multipart'])) {
            $boundary = 'herosignal-' . bin2hex(random_bytes(12));
            $headers['Content-Type'] = 'multipart/form-data; boundary=' . $boundary;
            $payload = self::multipartBody($options['multipart'], $boundary);
        } elseif ($body !== null) {
            $headers['Content-Type'] = 'application/json';
            $payload = json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $attempt = 0;
        while (true) {
            try {
                $response = $this->transport->send($method, $url, $headers, $payload, $this->timeoutSeconds);
            } catch (HeroSignalException $e) {
                if (!$e->isRetryable() || $attempt >= $this->maxRetries) {
                    throw $e;
                }
                ($this->sleep)(self::BACKOFF_MS[min($attempt, count(self::BACKOFF_MS) - 1)]);
                $attempt++;
                continue;
            }

            $status = $response['status'];
            if ($status >= 200 && $status < 300) {
                return $response['body'];
            }

            $envelope = json_decode($response['body'], true);
            $error = is_array($envelope) && isset($envelope['error']) && is_array($envelope['error']) ? $envelope['error'] : [];
            $exception = new HeroSignalException(
                $status,
                (string) ($error['code'] ?? ('HTTP_' . $status)),
                (string) ($error['message'] ?? ('HTTP ' . $status)),
                isset($error['request_id']) ? (string) $error['request_id'] : ($response['headers']['x-request-id'] ?? null),
                isset($error['details']) && is_array($error['details']) ? $error['details'] : null,
            );
            if (!$exception->isRetryable() || $attempt >= $this->maxRetries) {
                throw $exception;
            }
            $retryAfter = isset($response['headers']['retry-after']) ? (int) $response['headers']['retry-after'] : 0;
            ($this->sleep)($retryAfter > 0 ? $retryAfter * 1000 : self::BACKOFF_MS[min($attempt, count(self::BACKOFF_MS) - 1)]);
            $attempt++;
        }
    }

    /**
     * multipart/form-data gövdesi; dosya alanları `['filename' => …, 'content' => …]` biçimindedir.
     *
     * @param array<string, string|array{filename:string, content:string, content_type?:string}> $fields
     */
    private static function multipartBody(array $fields, string $boundary): string
    {
        $out = '';
        foreach ($fields as $name => $value) {
            $out .= '--' . $boundary . "\r\n";
            if (is_array($value)) {
                $filename = str_replace(['"', "\r", "\n"], '', $value['filename']);
                $out .= 'Content-Disposition: form-data; name="' . $name . '"; filename="' . $filename . "\"\r\n";
                $out .= 'Content-Type: ' . ($value['content_type'] ?? 'application/octet-stream') . "\r\n\r\n";
                $out .= $value['content'] . "\r\n";
            } else {
                $out .= 'Content-Disposition: form-data; name="' . $name . "\"\r\n\r\n";
                $out .= $value . "\r\n";
            }
        }
        return $out . '--' . $boundary . "--\r\n";
    }
}
