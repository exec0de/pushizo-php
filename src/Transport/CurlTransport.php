<?php

declare(strict_types=1);

namespace Pushizo\Transport;

use Pushizo\ConnectionException;
use Pushizo\PushizoException;

/** ext-curl ile HTTP istekleri (ek bağımlılık yok). */
final class CurlTransport implements TransportInterface
{
    /** Bağlantı kurulmadan oluşan cURL hataları: proxy/sunucu adı çözümlenemedi, bağlantı kurulamadı. */
    private const NOT_SENT_ERRORS = [CURLE_COULDNT_RESOLVE_PROXY, CURLE_COULDNT_RESOLVE_HOST, CURLE_COULDNT_CONNECT];

    public function send(string $method, string $url, array $headers, ?string $body, int $timeoutSeconds): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new PushizoException(0, 'NETWORK', 'cURL başlatılamadı');
        }
        $headerLines = [];
        foreach ($headers as $k => $v) {
            $headerLines[] = $k . ': ' . $v;
        }
        $responseHeaders = [];
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_TIMEOUT => $timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$responseHeaders): int {
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) {
                    $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                }
                return strlen($line);
            },
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $result = curl_exec($ch);
        if ($result === false) {
            $error = curl_error($ch);
            $errno = curl_errno($ch);
            // Zaman aşımı bağlantı kurulmadan (connect_time 0) oluştuysa istek gönderilmemiştir.
            $notSent = in_array($errno, self::NOT_SENT_ERRORS, true)
                || ($errno === CURLE_OPERATION_TIMEDOUT && (float) curl_getinfo($ch, CURLINFO_CONNECT_TIME) === 0.0);
            curl_close($ch);
            $message = $error !== '' ? $error : 'ağ hatası';
            throw $notSent ? new ConnectionException($message) : new PushizoException(0, 'NETWORK', $message);
        }
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        return ['status' => $status, 'body' => (string) $result, 'headers' => $responseHeaders];
    }
}
