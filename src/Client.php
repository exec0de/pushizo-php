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

    /**
     * JSON istek; 429/5xx ve ağ hatalarında üstel geri çekilme (Retry-After'a saygılı).
     *
     * @param array<string, scalar|null> $query
     * @return array<string, mixed>
     */
    public function request(string $method, string $path, array $query = [], mixed $body = null): array
    {
        $url = $this->baseUrl . $path;
        $filtered = array_filter($query, static fn ($v) => $v !== null);
        if ($filtered !== []) {
            $url .= '?' . http_build_query($filtered);
        }
        $headers = [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Accept' => 'application/json',
            'User-Agent' => 'herosignal-php/' . self::VERSION,
        ];
        $json = null;
        if ($body !== null) {
            $headers['Content-Type'] = 'application/json';
            $json = json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $attempt = 0;
        while (true) {
            try {
                $response = $this->transport->send($method, $url, $headers, $json, $this->timeoutSeconds);
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
                if ($response['body'] === '') {
                    return [];
                }
                /** @var array<string, mixed> $decoded */
                $decoded = json_decode($response['body'], true, 512, JSON_THROW_ON_ERROR);
                return $decoded;
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
}
