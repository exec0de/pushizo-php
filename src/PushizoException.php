<?php

declare(strict_types=1);

namespace Pushizo;

/** API hata zarfından türetilen istisna; `status` ve `code` ile programatik işleme. */
class PushizoException extends \RuntimeException
{
    /**
     * @param array<string, mixed>|null $details
     */
    public function __construct(
        public readonly int $status,
        public readonly string $errorCode,
        string $message,
        public readonly ?string $requestId = null,
        public readonly ?array $details = null,
    ) {
        parent::__construct($message, $status);
    }

    /** 429 veya 5xx (ya da ağ hatası): yeniden denenebilir. */
    public function isRetryable(): bool
    {
        return $this->status === 0 || $this->status === 429 || $this->status >= 500;
    }
}
