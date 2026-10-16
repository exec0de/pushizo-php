<?php

declare(strict_types=1);

namespace Pushizo\Transport;

/** HTTP taşıma soyutlaması: üretimde cURL, testlerde sahte. */
interface TransportInterface
{
    /**
     * @param array<string, string> $headers
     * @return array{status:int, body:string, headers:array<string,string>}
     */
    public function send(string $method, string $url, array $headers, ?string $body, int $timeoutSeconds): array;
}
