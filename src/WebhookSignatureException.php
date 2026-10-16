<?php

declare(strict_types=1);

namespace Pushizo;

/** Webhook imzası doğrulanamadı; `reason` makine tarafından okunabilir neden. */
final class WebhookSignatureException extends \RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct('Pushizo webhook imzası geçersiz: ' . $reason);
    }
}
