<?php

declare(strict_types=1);

namespace Pushizo;

/**
 * İstek sunucuya hiç ulaşmadan oluşan ağ hatası (DNS çözümlenemedi, bağlantı reddedildi, bağlantı zaman aşımı).
 * Sunucu isteği almadığı için POST/PATCH dahil her metotta güvenle yeniden denenir. Özel taşımalar bağlantı
 * kurulamadığında bu istisnayı, istek gönderildikten sonraki hatalarda `PushizoException` (status 0) fırlatır.
 */
final class ConnectionException extends PushizoException
{
    public function __construct(string $message)
    {
        parent::__construct(0, 'NETWORK', $message);
    }
}
