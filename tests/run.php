<?php

declare(strict_types=1);

// Bağımlılıksız test koşucusu: `php tests/run.php` (PHPUnit gerekmez).
spl_autoload_register(static function (string $class): void {
    $prefix = 'HeroSignal\\';
    if (str_starts_with($class, $prefix)) {
        $file = __DIR__ . '/../src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

use HeroSignal\Client;
use HeroSignal\HeroSignalException;
use HeroSignal\Transport\TransportInterface;

final class FakeTransport implements TransportInterface
{
    /** @var array<int, array{method:string,url:string,headers:array<string,string>,body:?string}> */
    public array $calls = [];
    /** @var array<int, array{status:int, body:string, headers:array<string,string>}> */
    private array $responses;

    /** @param array<int, array{status:int, body:string, headers?:array<string,string>}> $responses */
    public function __construct(array $responses)
    {
        $this->responses = array_map(static fn ($r) => $r + ['headers' => []], $responses);
    }

    public function send(string $method, string $url, array $headers, ?string $body, int $timeoutSeconds): array
    {
        $this->calls[] = ['method' => $method, 'url' => $url, 'headers' => $headers, 'body' => $body];
        if ($this->responses === []) {
            throw new HeroSignalException(0, 'NETWORK', 'bağlantı yok');
        }
        return array_shift($this->responses);
    }
}

$failures = 0;
$assert = static function (bool $cond, string $label) use (&$failures): void {
    echo ($cond ? '  ✔ ' : '  ✘ ') . $label . PHP_EOL;
    if (!$cond) {
        $failures++;
    }
};
$noSleep = static function (int $ms): void {
};

echo "notifications.create" . PHP_EOL;
$t = new FakeTransport([['status' => 202, 'body' => '{"id":"msg_1","status":"queued","recipients_estimate":1,"external_id_missing":[]}']]);
$hs = new Client(['api_key' => 'hs_live_x', 'base_url' => 'https://api.test/', 'app_id' => 'app_1', 'transport' => $t, 'sleep' => $noSleep]);
$res = $hs->notifications->create(['target' => ['include_aliases' => ['external_id' => ['u1']]], 'contents' => ['tr' => 'Merhaba']]);
$assert($res['id'] === 'msg_1', 'yanıt id');
$assert($t->calls[0]['url'] === 'https://api.test/v1/notifications', 'url');
$assert($t->calls[0]['headers']['Authorization'] === 'Bearer hs_live_x', 'bearer başlığı');
$sent = json_decode((string) $t->calls[0]['body'], true);
$assert($sent['app_id'] === 'app_1', 'app_id eklendi');
$assert(preg_match('/^[0-9a-f-]{36}$/', $sent['idempotency_key']) === 1, 'otomatik idempotency anahtarı');
$assert($sent['contents']['tr'] === 'Merhaba', 'UTF-8 korunur');

echo "sorgu parametreleri ve yol kodlaması" . PHP_EOL;
$t = new FakeTransport([['status' => 200, 'body' => '{"data":[]}'], ['status' => 200, 'body' => '{"id":"usr_1"}'], ['status' => 200, 'body' => '{}']]);
$hs = new Client(['api_key' => 'k', 'base_url' => 'https://api.test', 'app_id' => 'app_1', 'transport' => $t, 'sleep' => $noSleep]);
$hs->notifications->list(['limit' => 10, 'status' => 'completed']);
$assert($t->calls[0]['url'] === 'https://api.test/v1/notifications?app_id=app_1&limit=10&status=completed', 'liste sorgusu');
$hs->users->getByAlias('crm id', 'a/b');
$assert($t->calls[1]['url'] === 'https://api.test/v1/apps/app_1/users/by/crm%20id/a%2Fb', 'alias kodlaması');
$hs->subscriptions->setOwner('sub_1', 'u-9');
$assert($t->calls[2]['method'] === 'POST' && $t->calls[2]['body'] === '{"external_id":"u-9"}', 'owner gövdesi');

echo "yeniden deneme ve hatalar" . PHP_EOL;
$t = new FakeTransport([
    ['status' => 429, 'body' => '{"error":{"code":"HS_RATE_LIMITED","message":"yavaş"}}', 'headers' => ['retry-after' => '1']],
    ['status' => 200, 'body' => '{"id":"msg_1"}'],
]);
$slept = [];
$hs = new Client(['api_key' => 'k', 'base_url' => 'https://api.test', 'app_id' => 'app_1', 'transport' => $t, 'sleep' => static function (int $ms) use (&$slept): void { $slept[] = $ms; }]);
$assert($hs->notifications->get('msg_1')['id'] === 'msg_1', '429 sonrası başarı');
$assert($slept === [1000], 'Retry-After kadar beklendi');

$t = new FakeTransport([['status' => 404, 'body' => '{"error":{"code":"HS_NOT_FOUND","message":"Mesaj bulunamadı","request_id":"req_1"}}']]);
$hs = new Client(['api_key' => 'k', 'base_url' => 'https://api.test', 'app_id' => 'app_1', 'transport' => $t, 'sleep' => $noSleep]);
try {
    $hs->notifications->get('msg_x');
    $assert(false, '404 istisna fırlatmalı');
} catch (HeroSignalException $e) {
    $assert($e->status === 404 && $e->errorCode === 'HS_NOT_FOUND' && $e->requestId === 'req_1' && !$e->isRetryable(), '404 → HeroSignalException');
}

$t = new FakeTransport([]);
$hs = new Client(['api_key' => 'k', 'base_url' => 'https://api.test', 'app_id' => 'app_1', 'transport' => $t, 'sleep' => $noSleep, 'max_retries' => 1]);
try {
    $hs->apps->list();
    $assert(false, 'ağ hatası istisna fırlatmalı');
} catch (HeroSignalException $e) {
    $assert($e->status === 0 && $e->errorCode === 'NETWORK' && count($t->calls) === 2, 'ağ hatasında max_retries kadar denendi');
}

try {
    (new Client(['api_key' => 'k', 'transport' => new FakeTransport([]), 'sleep' => $noSleep]))->users->list();
    $assert(false, 'app_id yoksa hata');
} catch (\InvalidArgumentException $e) {
    $assert(str_contains($e->getMessage(), 'app_id'), 'app_id eksik hatası');
}

echo "yeniden deneme politikası" . PHP_EOL;
$unavailable = ['status' => 503, 'body' => '{"error":{"code":"HS_SERVICE_UNAVAILABLE","message":"x"}}'];
$ok = ['status' => 200, 'body' => '{"id":"ok"}'];
$make = static function (array $responses, array $extra = []) use ($noSleep): array {
    $t = new FakeTransport($responses);
    return [new Client($extra + ['api_key' => 'k', 'base_url' => 'https://api.test', 'app_id' => 'app_1', 'transport' => $t, 'sleep' => $noSleep]), $t];
};

[$hs, $t] = $make([$unavailable, $ok]);
try {
    $hs->events->send([['external_id' => 'u1', 'name' => 'purchase']]);
    $assert(false, 'anahtarsız POST 503 istisna fırlatmalı');
} catch (HeroSignalException $e) {
    $assert($e->status === 503 && count($t->calls) === 1, 'anahtarsız POST 503 sonrası yeniden denenmez');
}

[$hs, $t] = $make([$unavailable, $ok], ['auto_idempotency_key' => false]);
try {
    $hs->notifications->create(['target' => ['all' => true], 'contents' => ['en' => 'x']]);
    $assert(false, 'otomatik anahtar kapalı: 503 istisna fırlatmalı');
} catch (HeroSignalException $e) {
    $assert(count($t->calls) === 1, 'otomatik anahtar kapalıyken notifications.create tekrarlanmaz');
}

[$hs, $t] = $make([$unavailable, $ok]);
$hs->request('POST', '/v1/apps/app_1/events', [], ['events' => []], ['idempotency_key' => 'evt-1']);
$assert(count($t->calls) === 2 && $t->calls[1]['headers']['Idempotency-Key'] === 'evt-1', 'Idempotency-Key taşıyan POST 503 sonrası yeniden denenir');

[$hs, $t] = $make([$unavailable, ['status' => 202, 'body' => '{"id":"msg_1"}']]);
$hs->notifications->create(['target' => ['all' => true], 'contents' => ['en' => 'x']]);
$keys = array_map(static fn (array $c): string => json_decode((string) $c['body'], true)['idempotency_key'], $t->calls);
$assert(count($t->calls) === 2 && $keys[0] === $keys[1], 'otomatik idempotency anahtarlı notifications.create aynı anahtarla yeniden denenir');

[$hs, $t] = $make([$unavailable, $ok, $unavailable, ['status' => 204, 'body' => '']]);
$hs->notifications->get('msg_1');
$hs->request('DELETE', '/v1/apps/app_1/webhooks/whk_1');
$assert(array_column($t->calls, 'method') === ['GET', 'GET', 'DELETE', 'DELETE'], 'GET ve DELETE 503 sonrası yeniden denenir');

$slept = [];
$t = new FakeTransport([['status' => 429, 'body' => '{"error":{"code":"HS_RATE_LIMITED","message":"x"}}', 'headers' => ['retry-after' => '2']], $ok]);
$hs = new Client(['api_key' => 'k', 'base_url' => 'https://api.test', 'app_id' => 'app_1', 'transport' => $t, 'sleep' => static function (int $ms) use (&$slept): void { $slept[] = $ms; }]);
$hs->events->send([]);
$assert(count($t->calls) === 2 && $slept === [2000], '429 alan anahtarsız POST Retry-After sonrası yeniden denenir');

$refusedOnce = new class implements TransportInterface {
    public int $calls = 0;

    public function send(string $method, string $url, array $headers, ?string $body, int $timeoutSeconds): array
    {
        if ($this->calls++ === 0) {
            throw new \HeroSignal\ConnectionException('Failed to connect: Connection refused');
        }
        return ['status' => 200, 'body' => '{"accepted":1}', 'headers' => []];
    }
};
$hs = new Client(['api_key' => 'k', 'base_url' => 'https://api.test', 'app_id' => 'app_1', 'transport' => $refusedOnce, 'sleep' => $noSleep]);
$assert($hs->events->send([]) === ['accepted' => 1] && $refusedOnce->calls === 2, 'bağlantı kurulamayan (ConnectionException) POST yeniden denenir');

[$hs, $t] = $make([]);
try {
    $hs->events->send([]);
    $assert(false, 'ağ hatası istisna fırlatmalı');
} catch (HeroSignalException $e) {
    $assert($e->status === 0 && count($t->calls) === 1, 'gönderildikten sonra kopan anahtarsız POST tekrarlanmaz');
}

if (extension_loaded('curl')) {
    $sock = stream_socket_server('tcp://127.0.0.1:0');
    $port = (int) substr((string) strrchr((string) stream_socket_get_name($sock, false), ':'), 1);
    fclose($sock);
    $slept = [];
    $hs = new Client(['api_key' => 'k', 'base_url' => "http://127.0.0.1:$port", 'app_id' => 'app_1', 'max_retries' => 1, 'sleep' => static function (int $ms) use (&$slept): void { $slept[] = $ms; }]);
    try {
        $hs->events->send([]);
        $assert(false, 'kapalı port istisna fırlatmalı');
    } catch (\HeroSignal\ConnectionException $e) {
        $assert($e->status === 0 && $e->errorCode === 'NETWORK' && $slept === [300], 'gerçek bağlantı reddinde (cURL) POST yeniden denenir');
    }
}

echo "inAppMessages.selectWinner" . PHP_EOL;
[$hs, $t] = $make([$ok]);
$hs->inAppMessages->selectWinner('iam_1', 'b');
$assert($t->calls[0]['method'] === 'POST' && $t->calls[0]['url'] === 'https://api.test/v1/apps/app_1/in-app-messages/iam_1/winner' && $t->calls[0]['body'] === '{"variant_id":"b"}', 'A/B kazananı POST edilir');

echo "identityHash" . PHP_EOL;
$assert(Client::identityHash('secret', 'u-1') === hash_hmac('sha256', 'u-1', 'secret'), 'HMAC-SHA256 eşleşir');

echo "identityToken (ES256 JWT)" . PHP_EOL;
$ecKey = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
if ($ecKey === false) {
    // Bazı Windows PHP paketleri (ör. XAMPP) openssl.cnf olmadan EC anahtarı üretemez; CI'daki Linux PHP'de test tam koşar.
    $assert(true, 'OpenSSL EC anahtar üretimi bu PHP kurulumunda yok; atlandı (CI Linux PHP ortamında koşar)');
} else {
    openssl_pkey_export($ecKey, $ecPem);
    $jwt = Client::identityToken($ecPem, 'main', 'app_1', 'kullanici-1', 600, 1800000000);
    [$h, $p, $sig] = explode('.', $jwt);
    $dec = static fn (string $v): string => (string) base64_decode(strtr($v, '-_', '+/') . str_repeat('=', (4 - strlen($v) % 4) % 4));
    $assert(json_decode($dec($h), true) === ['alg' => 'ES256', 'typ' => 'JWT', 'kid' => 'main'], 'başlık');
    $assert(json_decode($dec($p), true) === ['sub' => 'kullanici-1', 'aud' => 'app_1', 'iat' => 1800000000, 'exp' => 1800000600], 'talepler');
    $raw = $dec($sig);
    $assert(strlen($raw) === 64, 'r||s 64 bayt');
    // r||s → DER (doğrulama için).
    $int = static function (string $b): string { $b = ltrim($b, "\x00"); if ($b === '' || (ord($b[0]) & 0x80)) { $b = "\x00" . $b; } return "\x02" . chr(strlen($b)) . $b; };
    $seq = $int(substr($raw, 0, 32)) . $int(substr($raw, 32));
    $der = "\x30" . chr(strlen($seq)) . $seq;
    $pub = openssl_pkey_get_details($ecKey)['key'];
    $assert(openssl_verify($h . '.' . $p, $der, $pub, OPENSSL_ALGO_SHA256) === 1, 'imza genel anahtarla doğrulanır');
    $threw = false;
    try { Client::identityToken($ecPem, 'k', 'a', 'e', 90000); } catch (\InvalidArgumentException) { $threw = true; }
    $assert($threw, '24 saatten uzun ömür reddedilir');
}

echo "Webhook imza doğrulama (ortak vektörler)" . PHP_EOL;
/** @var array{cases: array<int, array{name:string, toleranceSeconds:int, payload:string, header:?string, secrets:string[], now:int, expected:array{ok:bool, timestamp?:int, reason?:string}, events?:int}>} $vectors */
$vectors = json_decode((string) file_get_contents(__DIR__ . '/../../testdata/webhook-signature-vectors.json'), true, 512, JSON_THROW_ON_ERROR);
$assert(count($vectors['cases']) > 10, 'vektör dosyası yüklendi');
foreach ($vectors['cases'] as $case) {
    $secret = count($case['secrets']) === 1 ? $case['secrets'][0] : $case['secrets'];
    $expectedReason = $case['expected']['ok'] ? null : $case['expected']['reason'];
    $reason = \HeroSignal\Webhook::verify($case['payload'], $case['header'], $secret, $case['toleranceSeconds'], $case['now']);
    $assert($reason === $expectedReason, $case['name']);
    $assert(
        \HeroSignal\Webhook::verify($case['payload'], $case['header'], $case['secrets'], $case['toleranceSeconds'], $case['now']) === $expectedReason,
        $case['name'] . ' (secret dizisi)',
    );
    if (isset($case['events'])) {
        $events = \HeroSignal\Webhook::constructEvents($case['payload'], $case['header'], $secret, $case['toleranceSeconds'], $case['now']);
        $assert(count($events) === $case['events'], $case['name'] . ': olay sayısı');
    } elseif ($expectedReason !== null) {
        try {
            \HeroSignal\Webhook::constructEvents($case['payload'], $case['header'], $secret, $case['toleranceSeconds'], $case['now']);
            $assert(false, $case['name'] . ': istisna fırlatmalı');
        } catch (\HeroSignal\WebhookSignatureException $e) {
            $assert($e->reason === $expectedReason, $case['name'] . ': WebhookSignatureException nedeni');
        }
    }
}

echo "Suppression list" . PHP_EOL;
$supp = '{"id":"sup_1","app_id":"app_1","channel":"email","value_masked":"a***e@example.com","reason":"manual","source":"api","detail":{},"expires_at":null,"created_at":"2026-10-07T00:00:00.000Z","updated_at":"2026-10-07T00:00:00.000Z"}';
$t = new FakeTransport([
    ['status' => 200, 'body' => '{"data":[' . $supp . '],"next_cursor":"c2","has_more":true}'],
    ['status' => 201, 'body' => $supp],
    ['status' => 200, 'body' => '{"ok":true}'],
    ['status' => 400, 'body' => '{"error":{"code":"HS_VALIDATION_ERROR","message":"Geçersiz adres"}}'],
]);
$hs = new Client(['api_key' => 'k', 'base_url' => 'https://api.test', 'app_id' => 'app_1', 'transport' => $t, 'sleep' => $noSleep]);
$page = $hs->suppressions->list(['channel' => 'email', 'reason' => 'bounce', 'value' => 'Ayse@Example.com', 'limit' => 20]);
$assert($t->calls[0]['method'] === 'GET' && $t->calls[0]['url'] === 'https://api.test/v1/apps/app_1/suppressions?channel=email&reason=bounce&value=Ayse%40Example.com&limit=20', 'list sorgusu');
$assert($page['data'][0]['value_masked'] === 'a***e@example.com' && $page['has_more'] === true, 'list yanıtı');
$created = $hs->suppressions->create(['channel' => 'sms', 'value' => '+905320000111', 'note' => 'müşteri talebi'], 'app_9');
$assert($t->calls[1]['method'] === 'POST' && $t->calls[1]['url'] === 'https://api.test/v1/apps/app_9/suppressions', 'create yolu (app parametresi)');
$assert($t->calls[1]['body'] === '{"channel":"sms","value":"+905320000111","note":"müşteri talebi"}', 'create gövdesi (UTF-8)');
$assert($created['id'] === 'sup_1', 'create yanıtı');
$hs->suppressions->delete('sup/1');
$assert($t->calls[2]['method'] === 'DELETE' && $t->calls[2]['url'] === 'https://api.test/v1/apps/app_1/suppressions/sup%2F1', 'delete yolu kodlanır');
try {
    $hs->suppressions->create(['channel' => 'email', 'value' => 'x']);
    $assert(false, '400 istisna fırlatmalı');
} catch (HeroSignalException $e) {
    $assert($e->status === 400 && $e->errorCode === 'HS_VALIDATION_ERROR', '400 → HeroSignalException');
}

echo "e-posta/SMS/inbox içerik alanları" . PHP_EOL;
$t = new FakeTransport([['status' => 202, 'body' => '{"id":"msg_2","status":"queued","recipients_estimate":1,"external_id_missing":[]}']]);
$hs = new Client(['api_key' => 'k', 'base_url' => 'https://api.test', 'app_id' => 'app_1', 'transport' => $t, 'sleep' => $noSleep]);
$hs->notifications->create([
    'target' => ['include_aliases' => ['external_id' => ['u1']], 'channels' => ['email', 'sms']],
    'contents' => ['tr' => 'Siparişin kargoda'],
    'email_subject' => 'Siparişin yolda',
    'email_mjml' => '<mjml><mj-body></mj-body></mjml>',
    'email_from_address' => 'no-reply@example.com',
    'disable_email_click_tracking' => true,
    'sms_from' => 'MAGAZA',
    'add_to_inbox' => true,
    'inbox_ttl' => 86400,
]);
$sent = json_decode((string) $t->calls[0]['body'], true);
$assert($sent['email_subject'] === 'Siparişin yolda' && $sent['sms_from'] === 'MAGAZA' && $sent['add_to_inbox'] === true && $sent['inbox_ttl'] === 86400, 'alanlar gövdede');
$assert($sent['target']['channels'] === ['email', 'sms'], 'kanal hedefi');

echo "OpenAPI kapsamı (her public işlem bir SDK metoduna eşlenir)" . PHP_EOL;
// api/openapi.yaml'ın `paths` bölümü satır bazlı okunur (yaml eklentisi gerekmez).
$specOps = [];
$inPaths = false;
$currentPath = null;
foreach (file(__DIR__ . '/../../../api/openapi.yaml', FILE_IGNORE_NEW_LINES) as $line) {
    if ($line === 'paths:') {
        $inPaths = true;
        continue;
    }
    if ($inPaths && preg_match('/^\S/', $line) === 1) {
        break;
    }
    if (!$inPaths) {
        continue;
    }
    if (preg_match('/^  (\/\S+):\s*$/', $line, $m) === 1) {
        $currentPath = str_starts_with($m[1], '/v1/') ? $m[1] : null;
    } elseif ($currentPath !== null && preg_match('/^    (get|post|put|patch|delete):\s*$/', $line, $m) === 1) {
        $specOps[] = strtoupper($m[1]) . ' ' . $currentPath;
    }
}
sort($specOps);

$definition = ['all' => [['field' => 'tag:vip', 'op' => 'eq', 'value' => 'true']]];
/** @var array<string, callable(Client):mixed> $table */
$table = [
    'GET /v1/organization' => fn (Client $c) => $c->organization->get(),
    'GET /v1/organization/members' => fn (Client $c) => $c->organization->members(),
    'GET /v1/organization/audit-logs' => fn (Client $c) => $c->organization->auditLogs(['action' => 'app.update']),
    'GET /v1/organization/usage' => fn (Client $c) => $c->organization->usage(['days' => 30]),
    'GET /v1/apps' => fn (Client $c) => $c->apps->list(),
    'POST /v1/apps' => fn (Client $c) => $c->apps->create(['name' => 'Yeni app']),
    'GET /v1/apps/{appId}' => fn (Client $c) => $c->apps->get(),
    'PATCH /v1/apps/{appId}' => fn (Client $c) => $c->apps->update(['name' => 'Ad']),
    'GET /v1/apps/{appId}/api-keys' => fn (Client $c) => $c->apiKeys->list(),
    'POST /v1/apps/{appId}/api-keys' => fn (Client $c) => $c->apiKeys->create(['name' => 'ci', 'scopes' => ['notifications:write']]),
    'PATCH /v1/apps/{appId}/api-keys/{keyId}' => fn (Client $c) => $c->apiKeys->update('key_1', ['name' => 'x']),
    'DELETE /v1/apps/{appId}/api-keys/{keyId}' => fn (Client $c) => $c->apiKeys->revoke('key_1'),
    'GET /v1/apps/{appId}/identity-keys' => fn (Client $c) => $c->identityKeys->list(),
    'POST /v1/apps/{appId}/identity-keys' => fn (Client $c) => $c->identityKeys->create(['kid' => 'main', 'public_key_pem' => 'pem']),
    'DELETE /v1/apps/{appId}/identity-keys/{keyId}' => fn (Client $c) => $c->identityKeys->revoke('ajk_1'),
    'GET /v1/apps/{appId}/live-activities/types' => fn (Client $c) => $c->liveActivities->listTypes(),
    'POST /v1/apps/{appId}/live-activities/types' => fn (Client $c) => $c->liveActivities->createType(['name' => 'OrderActivity', 'content_state_schema' => ['type' => 'object', 'properties' => ['eta' => ['type' => 'integer']]]]),
    'PATCH /v1/apps/{appId}/live-activities/types/{activityType}' => fn (Client $c) => $c->liveActivities->updateType('OrderActivity', ['description' => 'Sipariş']),
    'DELETE /v1/apps/{appId}/live-activities/types/{activityType}' => fn (Client $c) => $c->liveActivities->deleteType('OrderActivity'),
    'POST /v1/apps/{appId}/live-activities/{activityType}/start' => fn (Client $c) => $c->liveActivities->start('OrderActivity', ['target' => ['all' => true], 'content_state' => ['eta' => 5]]),
    'POST /v1/apps/{appId}/live-activities/{activityType}/update' => fn (Client $c) => $c->liveActivities->update('OrderActivity', ['activity_id' => 'order-1', 'content_state' => ['eta' => 3]]),
    'POST /v1/apps/{appId}/live-activities/{activityType}/end' => fn (Client $c) => $c->liveActivities->end('OrderActivity', ['activity_id' => 'order-1']),
    'POST /v1/apps/{appId}/api-keys/{keyId}/rotate' => fn (Client $c) => $c->apiKeys->rotate('key_1'),
    'GET /v1/apps/{appId}/webhooks' => fn (Client $c) => $c->webhooks->list(),
    'POST /v1/apps/{appId}/webhooks' => fn (Client $c) => $c->webhooks->create(['url' => 'https://example.com/h', 'events' => ['user.created']]),
    'GET /v1/apps/{appId}/webhooks/{webhookId}' => fn (Client $c) => $c->webhooks->get('wh_1'),
    'PATCH /v1/apps/{appId}/webhooks/{webhookId}' => fn (Client $c) => $c->webhooks->update('wh_1', ['enabled' => false]),
    'DELETE /v1/apps/{appId}/webhooks/{webhookId}' => fn (Client $c) => $c->webhooks->delete('wh_1'),
    'POST /v1/apps/{appId}/webhooks/{webhookId}/rotate-secret' => fn (Client $c) => $c->webhooks->rotateSecret('wh_1'),
    'POST /v1/apps/{appId}/webhooks/{webhookId}/test' => fn (Client $c) => $c->webhooks->test('wh_1'),
    'GET /v1/apps/{appId}/webhooks/{webhookId}/deliveries' => fn (Client $c) => $c->webhooks->deliveries('wh_1'),
    'POST /v1/apps/{appId}/webhooks/deliveries/{deliveryId}/replay' => fn (Client $c) => $c->webhooks->replay('whd_1'),
    'GET /v1/apps/{appId}/users' => fn (Client $c) => $c->users->list(),
    'POST /v1/apps/{appId}/users' => fn (Client $c) => $c->users->upsert(['external_id' => 'u1']),
    'POST /v1/apps/{appId}/users/search' => fn (Client $c) => $c->users->search(['definition' => $definition]),
    'GET /v1/apps/{appId}/users/by/{label}/{value}' => fn (Client $c) => $c->users->getByExternalId('u1'),
    'PATCH /v1/apps/{appId}/users/by/{label}/{value}' => fn (Client $c) => $c->users->update('external_id', 'u1', ['tags' => ['vip' => '1']]),
    'DELETE /v1/apps/{appId}/users/by/{label}/{value}' => fn (Client $c) => $c->users->delete('external_id', 'u1'),
    'GET /v1/apps/{appId}/users/by/{label}/{value}/identity' => fn (Client $c) => $c->users->getIdentity('external_id', 'u1'),
    'PATCH /v1/apps/{appId}/users/by/{label}/{value}/identity' => fn (Client $c) => $c->users->addAliases('external_id', 'u1', ['crm' => 'c1']),
    'GET /v1/apps/{appId}/users/by/{label}/{value}/export' => fn (Client $c) => $c->users->export('external_id', 'u1'),
    'DELETE /v1/apps/{appId}/users/by/{label}/{value}/identity/{aliasLabel}' => fn (Client $c) => $c->users->removeAlias('external_id', 'u1', 'crm'),
    'POST /v1/apps/{appId}/users/by/{label}/{value}/subscriptions' => fn (Client $c) => $c->users->addSubscription('external_id', 'u1', ['channel' => 'email', 'token' => 'a@example.com']),
    'GET /v1/apps/{appId}/users/by/{label}/{value}/inbox' => fn (Client $c) => $c->inbox->list('external_id', 'u1'),
    'POST /v1/apps/{appId}/users/by/{label}/{value}/inbox/read-all' => fn (Client $c) => $c->inbox->readAll('external_id', 'u1'),
    'POST /v1/apps/{appId}/users/by/{label}/{value}/inbox/{itemId}/read' => fn (Client $c) => $c->inbox->read('external_id', 'u1', 'inb_1'),
    'DELETE /v1/apps/{appId}/users/by/{label}/{value}/inbox/{itemId}' => fn (Client $c) => $c->inbox->archive('external_id', 'u1', 'inb_1'),
    'GET /v1/apps/{appId}/subscriptions/{subscriptionId}' => fn (Client $c) => $c->subscriptions->get('sub_1'),
    'PATCH /v1/apps/{appId}/subscriptions/{subscriptionId}' => fn (Client $c) => $c->subscriptions->update('sub_1', ['status' => 'unsubscribed']),
    'DELETE /v1/apps/{appId}/subscriptions/{subscriptionId}' => fn (Client $c) => $c->subscriptions->delete('sub_1'),
    'GET /v1/apps/{appId}/subscriptions/{subscriptionId}/user/identity' => fn (Client $c) => $c->subscriptions->getUserIdentity('sub_1'),
    'PATCH /v1/apps/{appId}/subscriptions_by_token/{tokenType}/{token}' => fn (Client $c) => $c->subscriptions->updateByToken('email', 'a@example.com', ['status' => 'unsubscribed']),
    'POST /v1/apps/{appId}/subscriptions/{subscriptionId}/owner' => fn (Client $c) => $c->subscriptions->setOwner('sub_1', 'u1'),
    'POST /v1/apps/{appId}/events' => fn (Client $c) => $c->events->send([['name' => 'purchase', 'external_id' => 'u1']]),
    'GET /v1/apps/{appId}/events/definitions' => fn (Client $c) => $c->events->listDefinitions(),
    'GET /v1/apps/{appId}/events/definitions/{name}' => fn (Client $c) => $c->events->getDefinition('purchase'),
    'PATCH /v1/apps/{appId}/events/definitions/{name}' => fn (Client $c) => $c->events->updateDefinition('purchase', ['is_conversion' => true]),
    'GET /v1/apps/{appId}/channels' => fn (Client $c) => $c->channels->list(),
    'PUT /v1/apps/{appId}/channels/{channel}' => fn (Client $c) => $c->channels->upsert('ios_push', ['provider' => 'mock']),
    'POST /v1/apps/{appId}/channels/{channel}/validate' => fn (Client $c) => $c->channels->validate('ios_push'),
    'POST /v1/notifications' => fn (Client $c) => $c->notifications->create(['target' => ['all' => true], 'contents' => ['en' => 'x']]),
    'GET /v1/notifications' => fn (Client $c) => $c->notifications->list(),
    'POST /v1/notifications/estimate' => fn (Client $c) => $c->notifications->estimate(['target' => ['all' => true]]),
    'GET /v1/notifications/{messageId}' => fn (Client $c) => $c->notifications->get('msg_1'),
    'DELETE /v1/notifications/{messageId}' => fn (Client $c) => $c->notifications->cancel('msg_1'),
    'GET /v1/notifications/{messageId}/deliveries' => fn (Client $c) => $c->notifications->deliveries('msg_1'),
    'GET /v1/notifications/{messageId}/history' => fn (Client $c) => $c->notifications->history('msg_1'),
    'POST /v1/notifications/{messageId}/export_events' => fn (Client $c) => $c->notifications->exportEvents('msg_1'),
    'GET /v1/apps/{appId}/analytics/overview' => fn (Client $c) => $c->analytics->overview(['range' => '30d']),
    'GET /v1/apps/{appId}/analytics/messages' => fn (Client $c) => $c->analytics->messages(),
    'GET /v1/apps/{appId}/analytics/subscriptions' => fn (Client $c) => $c->analytics->subscriptions(),
    'GET /v1/apps/{appId}/analytics/events' => fn (Client $c) => $c->analytics->events(['name' => 'purchase']),
    'GET /v1/apps/{appId}/analytics/conversions' => fn (Client $c) => $c->analytics->conversions(),
    'POST /v1/apps/{appId}/analytics/funnels' => fn (Client $c) => $c->analytics->funnel(['steps' => [['name' => 'a'], ['name' => 'b']]]),
    'GET /v1/apps/{appId}/analytics/providers' => fn (Client $c) => $c->analytics->providers(),
    'GET /v1/apps/{appId}/analytics/engagement' => fn (Client $c) => $c->analytics->engagement(),
    'GET /v1/apps/{appId}/analytics/retention' => fn (Client $c) => $c->analytics->retention(['weeks' => 8]),
    'POST /v1/apps/{appId}/exports' => fn (Client $c) => $c->exports->create(['type' => 'users']),
    'GET /v1/apps/{appId}/exports' => fn (Client $c) => $c->exports->list(),
    'GET /v1/apps/{appId}/exports/{exportId}' => fn (Client $c) => $c->exports->get('exp_1'),
    'POST /v1/apps/{appId}/imports' => fn (Client $c) => $c->imports->create('csv_users', "external_id\nu1\n"),
    'GET /v1/apps/{appId}/imports' => fn (Client $c) => $c->imports->list(),
    'GET /v1/apps/{appId}/imports/{importId}' => fn (Client $c) => $c->imports->get('imp_1'),
    'GET /v1/apps/{appId}/segments' => fn (Client $c) => $c->segments->list(),
    'POST /v1/apps/{appId}/segments' => fn (Client $c) => $c->segments->create(['name' => 'VIP', 'definition' => $definition]),
    'POST /v1/apps/{appId}/segments/preview' => fn (Client $c) => $c->segments->preview(['definition' => $definition]),
    'GET /v1/apps/{appId}/segments/{segmentId}' => fn (Client $c) => $c->segments->get('seg_1'),
    'PATCH /v1/apps/{appId}/segments/{segmentId}' => fn (Client $c) => $c->segments->update('seg_1', ['name' => 'X']),
    'DELETE /v1/apps/{appId}/segments/{segmentId}' => fn (Client $c) => $c->segments->delete('seg_1'),
    'GET /v1/apps/{appId}/segments/{segmentId}/campaigns' => fn (Client $c) => $c->segments->usedBy('seg_1'),
    'POST /v1/apps/{appId}/segments/{segmentId}/estimate' => fn (Client $c) => $c->segments->estimate('seg_1'),
    'GET /v1/apps/{appId}/templates' => fn (Client $c) => $c->templates->list(),
    'POST /v1/apps/{appId}/templates' => fn (Client $c) => $c->templates->create(['name' => 'Hoş geldin', 'content' => ['contents' => ['en' => 'Hi']]]),
    'GET /v1/apps/{appId}/templates/{templateId}' => fn (Client $c) => $c->templates->get('tpl_1'),
    'PATCH /v1/apps/{appId}/templates/{templateId}' => fn (Client $c) => $c->templates->update('tpl_1', ['name' => 'Y']),
    'DELETE /v1/apps/{appId}/templates/{templateId}' => fn (Client $c) => $c->templates->delete('tpl_1'),
    'POST /v1/apps/{appId}/templates/{templateId}/copy_to_app' => fn (Client $c) => $c->templates->copyToApp('tpl_1', ['target_app_id' => 'app_2']),
    'GET /v1/apps/{appId}/templates/{templateId}/versions' => fn (Client $c) => $c->templates->versions('tpl_1'),
    'GET /v1/apps/{appId}/templates/{templateId}/stats' => fn (Client $c) => $c->templates->stats('tpl_1'),
    'POST /v1/apps/{appId}/templates/{templateId}/render' => fn (Client $c) => $c->templates->render('tpl_1'),
    'GET /v1/apps/{appId}/campaigns' => fn (Client $c) => $c->campaigns->list(),
    'POST /v1/apps/{appId}/campaigns' => fn (Client $c) => $c->campaigns->create(['name' => 'Bahar']),
    'GET /v1/apps/{appId}/campaigns/{campaignId}' => fn (Client $c) => $c->campaigns->get('cmp_1'),
    'PATCH /v1/apps/{appId}/campaigns/{campaignId}' => fn (Client $c) => $c->campaigns->update('cmp_1', ['name' => 'Yaz']),
    'DELETE /v1/apps/{appId}/campaigns/{campaignId}' => fn (Client $c) => $c->campaigns->delete('cmp_1'),
    'POST /v1/apps/{appId}/campaigns/{campaignId}/schedule' => fn (Client $c) => $c->campaigns->schedule('cmp_1', ['send_at' => '2026-11-01T09:00:00Z']),
    'POST /v1/apps/{appId}/campaigns/{campaignId}/send' => fn (Client $c) => $c->campaigns->send('cmp_1'),
    'POST /v1/apps/{appId}/campaigns/{campaignId}/pause' => fn (Client $c) => $c->campaigns->pause('cmp_1'),
    'POST /v1/apps/{appId}/campaigns/{campaignId}/resume' => fn (Client $c) => $c->campaigns->resume('cmp_1'),
    'POST /v1/apps/{appId}/campaigns/{campaignId}/cancel' => fn (Client $c) => $c->campaigns->cancel('cmp_1'),
    'POST /v1/apps/{appId}/campaigns/{campaignId}/duplicate' => fn (Client $c) => $c->campaigns->duplicate('cmp_1'),
    'POST /v1/apps/{appId}/campaigns/{campaignId}/approve' => fn (Client $c) => $c->campaigns->approve('cmp_1'),
    'GET /v1/apps/{appId}/campaigns/{campaignId}/stats' => fn (Client $c) => $c->campaigns->stats('cmp_1'),
    'GET /v1/apps/{appId}/campaigns/{campaignId}/deliveries' => fn (Client $c) => $c->campaigns->deliveries('cmp_1'),
    'POST /v1/apps/{appId}/campaigns/{campaignId}/resend-failed' => fn (Client $c) => $c->campaigns->resendFailed('cmp_1'),
    'GET /v1/apps/{appId}/campaigns/{campaignId}/messages' => fn (Client $c) => $c->campaigns->messages('cmp_1'),
    'GET /v1/apps/{appId}/campaigns/{campaignId}/variants/stats' => fn (Client $c) => $c->campaigns->variantStats('cmp_1'),
    'POST /v1/apps/{appId}/campaigns/{campaignId}/winner' => fn (Client $c) => $c->campaigns->selectWinner('cmp_1', 'cvr_1'),
    'GET /v1/apps/{appId}/categories' => fn (Client $c) => $c->preferences->listCategories(),
    'POST /v1/apps/{appId}/categories' => fn (Client $c) => $c->preferences->createCategory(['key' => 'marketing', 'name' => ['tr' => 'Pazarlama']]),
    'PATCH /v1/apps/{appId}/categories/{categoryId}' => fn (Client $c) => $c->preferences->updateCategory('cat_1', ['position' => 1]),
    'DELETE /v1/apps/{appId}/categories/{categoryId}' => fn (Client $c) => $c->preferences->deleteCategory('cat_1'),
    'GET /v1/apps/{appId}/users/by/{label}/{value}/preferences' => fn (Client $c) => $c->preferences->getUser('external_id', 'u1'),
    'PUT /v1/apps/{appId}/users/by/{label}/{value}/preferences' => fn (Client $c) => $c->preferences->setUser('external_id', 'u1', [['category' => 'marketing', 'opted_in' => false]]),
    'GET /v1/apps/{appId}/suppressions' => fn (Client $c) => $c->suppressions->list(),
    'POST /v1/apps/{appId}/suppressions' => fn (Client $c) => $c->suppressions->create(['channel' => 'email', 'value' => 'a@example.com']),
    'DELETE /v1/apps/{appId}/suppressions/{suppressionId}' => fn (Client $c) => $c->suppressions->delete('sup_1'),
    'GET /v1/apps/{appId}/in-app-messages' => fn (Client $c) => $c->inAppMessages->list(),
    'POST /v1/apps/{appId}/in-app-messages' => fn (Client $c) => $c->inAppMessages->create(['name' => 'Banner', 'triggers' => [['kind' => 'session_start']], 'display' => ['layout' => 'modal', 'blocks' => []]]),
    'GET /v1/apps/{appId}/in-app-messages/{messageId}' => fn (Client $c) => $c->inAppMessages->get('iam_1'),
    'PATCH /v1/apps/{appId}/in-app-messages/{messageId}' => fn (Client $c) => $c->inAppMessages->update('iam_1', ['name' => 'Yeni']),
    'DELETE /v1/apps/{appId}/in-app-messages/{messageId}' => fn (Client $c) => $c->inAppMessages->delete('iam_1'),
    'POST /v1/apps/{appId}/in-app-messages/{messageId}/status' => fn (Client $c) => $c->inAppMessages->setStatus('iam_1', 'active'),
    'POST /v1/apps/{appId}/in-app-messages/{messageId}/winner' => fn (Client $c) => $c->inAppMessages->selectWinner('iam_1', 'a'),
    // journey'ler
    'GET /v1/apps/{appId}/journeys' => fn (Client $c) => $c->journeys->list(),
    'POST /v1/apps/{appId}/journeys' => fn (Client $c) => $c->journeys->create(['name' => 'Karşılama']),
    'GET /v1/apps/{appId}/journeys/{journeyId}' => fn (Client $c) => $c->journeys->get('jrn_1'),
    'PATCH /v1/apps/{appId}/journeys/{journeyId}' => fn (Client $c) => $c->journeys->update('jrn_1', ['name' => 'Yeni ad']),
    'DELETE /v1/apps/{appId}/journeys/{journeyId}' => fn (Client $c) => $c->journeys->delete('jrn_1'),
    'PUT /v1/apps/{appId}/journeys/{journeyId}/draft' => fn (Client $c) => $c->journeys->saveDraft('jrn_1', ['graph' => ['nodes' => [], 'edges' => []]]),
    'PATCH /v1/apps/{appId}/journeys/{journeyId}/nodes/{nodeId}' => fn (Client $c) => $c->journeys->updateNode('jrn_1', 'n1', ['position' => ['x' => 0, 'y' => 0]]),
    'GET /v1/apps/{appId}/journeys/{journeyId}/versions' => fn (Client $c) => $c->journeys->versions('jrn_1'),
    'POST /v1/apps/{appId}/journeys/{journeyId}/publish' => fn (Client $c) => $c->journeys->publish('jrn_1'),
    'POST /v1/apps/{appId}/journeys/{journeyId}/pause' => fn (Client $c) => $c->journeys->pause('jrn_1'),
    'POST /v1/apps/{appId}/journeys/{journeyId}/resume' => fn (Client $c) => $c->journeys->resume('jrn_1'),
    'POST /v1/apps/{appId}/journeys/{journeyId}/archive' => fn (Client $c) => $c->journeys->archive('jrn_1'),
    'POST /v1/apps/{appId}/journeys/{journeyId}/duplicate' => fn (Client $c) => $c->journeys->duplicate('jrn_1'),
    'GET /v1/apps/{appId}/journeys/{journeyId}/stats' => fn (Client $c) => $c->journeys->stats('jrn_1'),
    'GET /v1/apps/{appId}/journeys/{journeyId}/runs' => fn (Client $c) => $c->journeys->listRuns('jrn_1'),
    'POST /v1/apps/{appId}/journeys/{journeyId}/runs' => fn (Client $c) => $c->journeys->triggerRun('jrn_1', ['external_id' => 'u1']),
    'GET /v1/apps/{appId}/journeys/{journeyId}/runs/{runId}' => fn (Client $c) => $c->journeys->getRun('jrn_1', 'run_1'),
    'DELETE /v1/apps/{appId}/journeys/{journeyId}/runs/{runId}' => fn (Client $c) => $c->journeys->exitRun('jrn_1', 'run_1'),
    'GET /v1/apps/{appId}/users/by/{label}/{value}/journeys' => fn (Client $c) => $c->journeys->listForUser('external_id', 'u1'),
    // v2: dinamik içerik
    'GET /v1/apps/{appId}/data-feeds' => fn (Client $c) => $c->dataFeeds->list(),
    'POST /v1/apps/{appId}/data-feeds' => fn (Client $c) => $c->dataFeeds->create(['name' => 'stok', 'url_template' => 'https://example.com/stok/{{ user.external_id }}']),
    'GET /v1/apps/{appId}/data-feeds/{feedId}' => fn (Client $c) => $c->dataFeeds->get('df_1'),
    'PATCH /v1/apps/{appId}/data-feeds/{feedId}' => fn (Client $c) => $c->dataFeeds->update('df_1', ['enabled' => false]),
    'DELETE /v1/apps/{appId}/data-feeds/{feedId}' => fn (Client $c) => $c->dataFeeds->delete('df_1'),
    'POST /v1/apps/{appId}/data-feeds/{feedId}/test' => fn (Client $c) => $c->dataFeeds->test('df_1', ['external_id' => 'u1']),
    'GET /v1/apps/{appId}/content-tables' => fn (Client $c) => $c->contentTables->list(),
    'POST /v1/apps/{appId}/content-tables' => fn (Client $c) => $c->contentTables->create(['name' => 'urunler', 'key_column' => 'sku'], "sku,ad\nA1,Kalem\n"),
    'GET /v1/apps/{appId}/content-tables/{tableId}' => fn (Client $c) => $c->contentTables->get('ct_1'),
    'PATCH /v1/apps/{appId}/content-tables/{tableId}' => fn (Client $c) => $c->contentTables->update('ct_1', ['description' => 'Ürün kataloğu']),
    'DELETE /v1/apps/{appId}/content-tables/{tableId}' => fn (Client $c) => $c->contentTables->delete('ct_1'),
    'GET /v1/apps/{appId}/content-tables/{tableId}/rows' => fn (Client $c) => $c->contentTables->rows('ct_1', ['limit' => 50]),
    'PUT /v1/apps/{appId}/content-tables/{tableId}/rows' => fn (Client $c) => $c->contentTables->replaceRows('ct_1', "sku,ad\nA2,Defter\n"),
    'POST /v1/apps/{appId}/webhooks/preview' => fn (Client $c) => $c->webhooks->preview(['event_type' => 'user.created']),
    // v2: veri ambarı dışa aktarımları
    'GET /v1/apps/{appId}/warehouse-exports/tables' => fn (Client $c) => $c->warehouseExports->tables(),
    'POST /v1/apps/{appId}/warehouse-exports/test-connection' => fn (Client $c) => $c->warehouseExports->testConnection(['destination' => 'bigquery', 'config' => ['project_id' => 'proje-1', 'dataset' => 'hs'], 'credentials' => ['service_account_json' => '{}']]),
    'GET /v1/apps/{appId}/warehouse-exports' => fn (Client $c) => $c->warehouseExports->list(),
    'POST /v1/apps/{appId}/warehouse-exports' => fn (Client $c) => $c->warehouseExports->create(['name' => 'BQ', 'tables' => ['events'], 'destination' => 'bigquery', 'config' => ['project_id' => 'proje-1', 'dataset' => 'hs'], 'credentials' => ['service_account_json' => '{}']]),
    'GET /v1/apps/{appId}/warehouse-exports/{exportId}' => fn (Client $c) => $c->warehouseExports->get('wex_1'),
    'PATCH /v1/apps/{appId}/warehouse-exports/{exportId}' => fn (Client $c) => $c->warehouseExports->update('wex_1', ['enabled' => false]),
    'DELETE /v1/apps/{appId}/warehouse-exports/{exportId}' => fn (Client $c) => $c->warehouseExports->delete('wex_1'),
    'POST /v1/apps/{appId}/warehouse-exports/{exportId}/test' => fn (Client $c) => $c->warehouseExports->test('wex_1'),
    'POST /v1/apps/{appId}/warehouse-exports/{exportId}/run' => fn (Client $c) => $c->warehouseExports->run('wex_1'),
    'GET /v1/apps/{appId}/warehouse-exports/{exportId}/runs' => fn (Client $c) => $c->warehouseExports->runs('wex_1'),
    // v2: iki yönlü mesajlaşma
    'GET /v1/apps/{appId}/conversations' => fn (Client $c) => $c->conversations->list(['unread' => true]),
    'GET /v1/apps/{appId}/conversations/{conversationId}' => fn (Client $c) => $c->conversations->get('cnv_1'),
    'GET /v1/apps/{appId}/conversations/{conversationId}/messages' => fn (Client $c) => $c->conversations->messages('cnv_1'),
    'POST /v1/apps/{appId}/conversations/{conversationId}/messages' => fn (Client $c) => $c->conversations->reply('cnv_1', ['text' => 'Merhaba']),
    'POST /v1/apps/{appId}/conversations/{conversationId}/read' => fn (Client $c) => $c->conversations->markRead('cnv_1'),
    'GET /v1/apps/{appId}/sms-keywords' => fn (Client $c) => $c->smsKeywords->list(),
    'POST /v1/apps/{appId}/sms-keywords' => fn (Client $c) => $c->smsKeywords->create(['keyword' => 'KAMPANYA', 'actions' => ['add_tags' => ['kampanya' => '1']]]),
    'PATCH /v1/apps/{appId}/sms-keywords/{keywordId}' => fn (Client $c) => $c->smsKeywords->update('kw_1', ['enabled' => false]),
    'DELETE /v1/apps/{appId}/sms-keywords/{keywordId}' => fn (Client $c) => $c->smsKeywords->delete('kw_1'),
    'GET /v1/apps/{appId}/whatsapp/templates' => fn (Client $c) => $c->whatsappTemplates->list(['status' => 'APPROVED']),
    'POST /v1/apps/{appId}/whatsapp/templates/sync' => fn (Client $c) => $c->whatsappTemplates->sync(),
    // v2: e-posta teslim edilebilirliği
    'GET /v1/apps/{appId}/email-deliverability' => fn (Client $c) => $c->emailDeliverability->get(),
    'PUT /v1/apps/{appId}/email-deliverability' => fn (Client $c) => $c->emailDeliverability->update(['bcc_address' => 'arsiv@example.com']),
    'POST /v1/apps/{appId}/email-deliverability/verify-address' => fn (Client $c) => $c->emailDeliverability->verifyAddress('a@example.com'),
    'GET /v1/apps/{appId}/email-deliverability/warmup-plans' => fn (Client $c) => $c->emailDeliverability->listWarmupPlans(),
    'POST /v1/apps/{appId}/email-deliverability/warmup-plans' => fn (Client $c) => $c->emailDeliverability->createWarmupPlan(['name' => 'Yeni alan', 'kind' => 'domain', 'target' => 'mail.example.com']),
    'GET /v1/apps/{appId}/email-deliverability/warmup-plans/{planId}' => fn (Client $c) => $c->emailDeliverability->getWarmupPlan('wup_1'),
    'GET /v1/apps/{appId}/email-deliverability/warmup-plans/{planId}/usage' => fn (Client $c) => $c->emailDeliverability->warmupPlanUsage('wup_1', 7),
    'PATCH /v1/apps/{appId}/email-deliverability/warmup-plans/{planId}' => fn (Client $c) => $c->emailDeliverability->updateWarmupPlan('wup_1', ['status' => 'paused']),
    'DELETE /v1/apps/{appId}/email-deliverability/warmup-plans/{planId}' => fn (Client $c) => $c->emailDeliverability->deleteWarmupPlan('wup_1'),
    // v2: Zapier ve MCP
    'GET /v1/apps/{appId}/integrations/zapier/me' => fn (Client $c) => $c->zapier->me(),
    'POST /v1/apps/{appId}/integrations/zapier/hooks' => fn (Client $c) => $c->zapier->subscribe(['target_url' => 'https://hooks.zapier.com/x', 'event' => 'user.created']),
    'DELETE /v1/apps/{appId}/integrations/zapier/hooks/{hookId}' => fn (Client $c) => $c->zapier->unsubscribe('zh_1'),
    'GET /v1/apps/{appId}/integrations/zapier/samples' => fn (Client $c) => $c->zapier->samples(['event' => 'user.created']),
    'POST /v1/apps/{appId}/mcp/campaign-drafts' => fn (Client $c) => $c->mcp->createCampaignDraft(['name' => 'Taslak']),
    'POST /v1/apps/{appId}/mcp/segment-drafts' => fn (Client $c) => $c->mcp->createSegmentDraft(['name' => 'VIP', 'definition' => $definition]),
];

$assert(count($specOps) >= 142, 'spec en az 142 public işlem içerir (' . count($specOps) . ')');
$missing = array_values(array_diff($specOps, array_keys($table)));
$assert($missing === [], 'her spec işlemi tabloda' . ($missing === [] ? '' : ': eksik ' . implode(', ', $missing)));
$stale = array_values(array_diff(array_keys($table), $specOps));
$assert($stale === [], 'tabloda spec dışı işlem yok' . ($stale === [] ? '' : ': ' . implode(', ', $stale)));
$mismatched = [];
foreach ($table as $op => $call) {
    [$method, $template] = explode(' ', $op, 2);
    $regex = '#^' . implode('[^/]+', array_map(static fn ($p) => preg_quote($p, '#'), preg_split('/\{[^}]+\}/', $template))) . '$#';
    $t = new FakeTransport([['status' => 200, 'body' => '{}']]);
    $call(new Client(['api_key' => 'k', 'base_url' => 'https://api.test', 'app_id' => 'app_1', 'transport' => $t, 'sleep' => $noSleep]));
    $path = (string) parse_url($t->calls[0]['url'] ?? '', PHP_URL_PATH);
    if (count($t->calls) !== 1 || $t->calls[0]['method'] !== $method || preg_match($regex, $path) !== 1) {
        $mismatched[] = $op . ' → ' . ($t->calls[0]['method'] ?? '?') . ' ' . $path;
    }
}
$assert($mismatched === [], count($table) . ' metot doğru yöntem ve yola gider' . ($mismatched === [] ? '' : ': ' . implode('; ', $mismatched)));

echo "Yeni kaynaklar: istek şekilleri" . PHP_EOL;
$t = new FakeTransport(array_fill(0, 20, ['status' => 200, 'body' => '{"id":"x"}']));
$hs = new Client(['api_key' => 'k', 'base_url' => 'https://api.test', 'app_id' => 'app_1', 'transport' => $t, 'sleep' => $noSleep]);
$hs->segments->estimate('seg/1', true);
$assert($t->calls[0]['method'] === 'POST' && $t->calls[0]['url'] === 'https://api.test/v1/apps/app_1/segments/seg%2F1/estimate?force=true', 'segments.estimate force=true');
$hs->segments->estimate('seg_1');
$assert(str_ends_with($t->calls[1]['url'], '?force=false'), 'segments.estimate varsayılan force=false');
$hs->templates->copyToApp('tpl_1', ['target_app_id' => 'app_2']);
$assert($t->calls[2]['body'] === '{"target_app_id":"app_2"}', 'templates.copyToApp gövdesi');
$hs->templates->render('tpl_1');
$assert($t->calls[3]['body'] === '{}', 'templates.render boş gövde JSON nesnesi');
$hs->campaigns->resendFailed('cmp_1');
$assert($t->calls[4]['url'] === 'https://api.test/v1/apps/app_1/campaigns/cmp_1/resend-failed' && $t->calls[4]['body'] === null, 'campaigns.resendFailed gövdesiz POST');
$hs->notifications->estimate(['target' => ['included_segments' => ['seg_1']]], 'app_7');
$assert($t->calls[5]['url'] === 'https://api.test/v1/notifications/estimate' && json_decode((string) $t->calls[5]['body'], true)['app_id'] === 'app_7', 'notifications.estimate app_id gövdede');
$hs->notifications->exportEvents('msg_1');
$assert($t->calls[6]['method'] === 'POST' && $t->calls[6]['url'] === 'https://api.test/v1/notifications/msg_1/export_events?app_id=app_1', 'notifications.exportEvents');
$hs->users->upsert(['external_id' => 'u1']);
$assert(preg_match('/^[0-9a-f-]{36}$/', $t->calls[7]['headers']['Idempotency-Key'] ?? '') === 1, 'users.upsert otomatik Idempotency-Key');
$hs->users->upsert(['external_id' => 'u1'], null, 'signup-u1');
$assert(($t->calls[8]['headers']['Idempotency-Key'] ?? null) === 'signup-u1', 'users.upsert verilen Idempotency-Key');
$hs->subscriptions->updateByToken('ChromePush', 'https://push.example/a?b=c', ['status' => 'unsubscribed']);
$assert($t->calls[9]['method'] === 'PATCH' && $t->calls[9]['url'] === 'https://api.test/v1/apps/app_1/subscriptions_by_token/ChromePush/https%3A%2F%2Fpush.example%2Fa%3Fb%3Dc', 'subscriptions.updateByToken token kodlanır');
$hs->inbox->list('external_id', 'u1', ['include_archived' => true, 'limit' => 20]);
$assert($t->calls[10]['url'] === 'https://api.test/v1/apps/app_1/users/by/external_id/u1/inbox?include_archived=true&limit=20', 'inbox.list boolean sorgu true/false olur');
$hs->inAppMessages->setStatus('iam_1', 'paused');
$assert($t->calls[11]['body'] === '{"status":"paused"}', 'inAppMessages.setStatus gövdesi');
$hs->events->updateDefinition('sepete ekle', ['is_conversion' => true]);
$assert($t->calls[12]['method'] === 'PATCH' && $t->calls[12]['url'] === 'https://api.test/v1/apps/app_1/events/definitions/sepete%20ekle', 'events.updateDefinition yolu');
$hs->analytics->messages(['from' => '2026-10-01T00:00:00Z', 'compare' => true, 'group_by' => 'channel']);
$assert($t->calls[13]['url'] === 'https://api.test/v1/apps/app_1/analytics/messages?from=2026-10-01T00%3A00%3A00Z&compare=true&group_by=channel', 'analytics.messages sorgusu');
$hs->organization->auditLogs(['app_id' => 'app_1', 'action' => 'api_key.rotate']);
$assert($t->calls[14]['url'] === 'https://api.test/v1/organization/audit-logs?app_id=app_1&action=api_key.rotate', 'organization.auditLogs filtreleri');

echo "v2 kaynakları: istek şekilleri" . PHP_EOL;
$t = new FakeTransport(array_fill(0, 10, ['status' => 200, 'body' => '{"id":"x"}']));
$hs = new Client(['api_key' => 'k', 'base_url' => 'https://api.test', 'app_id' => 'app_1', 'transport' => $t, 'sleep' => $noSleep]);
$hs->contentTables->create(['name' => 'urunler', 'key_column' => 'sku', 'description' => 'Ürün kataloğu'], "sku,ad\nA1,Kalem\n", 'urunler.csv');
$ct = $t->calls[0]['headers']['Content-Type'] ?? '';
$body = (string) $t->calls[0]['body'];
$assert($t->calls[0]['method'] === 'POST' && str_starts_with($ct, 'multipart/form-data; boundary=')
    && str_contains($body, "name=\"name\"\r\n\r\nurunler\r\n") && str_contains($body, "name=\"key_column\"\r\n\r\nsku\r\n")
    && str_contains($body, "name=\"description\"\r\n\r\nÜrün kataloğu\r\n") && str_contains($body, 'filename="urunler.csv"'), 'contentTables.create multipart alanları');
$hs->contentTables->replaceRows('ct/1', "sku\nA2\n");
$body = (string) $t->calls[1]['body'];
$assert($t->calls[1]['method'] === 'PUT' && $t->calls[1]['url'] === 'https://api.test/v1/apps/app_1/content-tables/ct%2F1/rows'
    && !str_contains($body, 'name="key_column"') && str_contains($body, 'name="file"'), 'contentTables.replaceRows anahtar sütunu isteğe bağlı');
$hs->contentTables->rows('ct_1', ['limit' => 10, 'offset' => 20, 'q' => 'A1']);
$assert($t->calls[2]['url'] === 'https://api.test/v1/apps/app_1/content-tables/ct_1/rows?limit=10&offset=20&q=A1', 'contentTables.rows sorgusu');
$hs->dataFeeds->test('df_1');
$assert($t->calls[3]['body'] === '{}', 'dataFeeds.test boş gövde JSON nesnesi');
$hs->conversations->list(['channel' => 'whatsapp', 'unread' => true]);
$assert($t->calls[4]['url'] === 'https://api.test/v1/apps/app_1/conversations?channel=whatsapp&unread=true', 'conversations.list boolean filtre');
$hs->conversations->markRead('cnv_1', 'app_9');
$assert($t->calls[5]['method'] === 'POST' && $t->calls[5]['url'] === 'https://api.test/v1/apps/app_9/conversations/cnv_1/read' && $t->calls[5]['body'] === null, 'conversations.markRead gövdesiz POST');
$hs->emailDeliverability->verifyAddress('ayşe@example.com');
$assert($t->calls[6]['body'] === '{"address":"ayşe@example.com"}', 'emailDeliverability.verifyAddress gövdesi (UTF-8)');
$hs->zapier->samples(['event' => 'event.received', 'event_name' => 'purchase']);
$assert($t->calls[7]['url'] === 'https://api.test/v1/apps/app_1/integrations/zapier/samples?event=event.received&event_name=purchase', 'zapier.samples sorgusu');
$hs->warehouseExports->run('wex_1');
$assert($t->calls[8]['method'] === 'POST' && $t->calls[8]['url'] === 'https://api.test/v1/apps/app_1/warehouse-exports/wex_1/run' && $t->calls[8]['body'] === null, 'warehouseExports.run gövdesiz POST');

echo "Journey'ler: istek şekilleri" . PHP_EOL;
$t = new FakeTransport(array_fill(0, 12, ['status' => 200, 'body' => '{"id":"jrn_1"}']));
$hs = new Client(['api_key' => 'k', 'base_url' => 'https://api.test', 'app_id' => 'app_1', 'transport' => $t, 'sleep' => $noSleep]);
$graph = [
    'nodes' => [
        ['id' => 'n1', 'type' => 'trigger', 'position' => ['x' => 0, 'y' => 0], 'data' => ['trigger' => ['kind' => 'api']]],
        ['id' => 'n2', 'type' => 'exit', 'position' => ['x' => 0, 'y' => 120], 'data' => ['label' => 'Bitiş']],
    ],
    'edges' => [['id' => 'e1', 'source' => 'n1', 'target' => 'n2', 'sourceHandle' => 'next']],
];
$hs->journeys->saveDraft('jrn/1', ['graph' => $graph], 'app_9');
$assert($t->calls[0]['method'] === 'PUT' && $t->calls[0]['url'] === 'https://api.test/v1/apps/app_9/journeys/jrn%2F1/draft' && json_decode((string) $t->calls[0]['body'], true) === ['graph' => $graph], 'journeys.saveDraft PUT gövdesi ve app_id');
$hs->journeys->updateNode('jrn_1', 'n/2', ['data' => ['label' => 'Son']]);
$assert($t->calls[1]['method'] === 'PATCH' && $t->calls[1]['url'] === 'https://api.test/v1/apps/app_1/journeys/jrn_1/nodes/n%2F2', 'journeys.updateNode düğüm kimliği kodlanır');
$hs->journeys->publish('jrn_1');
$assert($t->calls[2]['method'] === 'POST' && $t->calls[2]['url'] === 'https://api.test/v1/apps/app_1/journeys/jrn_1/publish' && $t->calls[2]['body'] === null, 'journeys.publish gövdesiz POST');
$hs->journeys->triggerRun('jrn_1', ['alias' => ['label' => 'crm', 'value' => 'c1'], 'context' => ['order_id' => 'o1']]);
$assert($t->calls[3]['body'] === '{"alias":{"label":"crm","value":"c1"},"context":{"order_id":"o1"}}', 'journeys.triggerRun alias + context gövdesi');
$hs->journeys->listRuns('jrn_1', ['status' => 'waiting', 'version' => 2, 'limit' => 10]);
$assert($t->calls[4]['url'] === 'https://api.test/v1/apps/app_1/journeys/jrn_1/runs?status=waiting&version=2&limit=10', 'journeys.listRuns filtreleri');
$hs->journeys->stats('jrn_1', ['version' => 3]);
$assert($t->calls[5]['url'] === 'https://api.test/v1/apps/app_1/journeys/jrn_1/stats?version=3', 'journeys.stats sürüm sorgusu');
$hs->journeys->exitRun('jrn_1', 'run_1');
$assert($t->calls[6]['method'] === 'DELETE' && $t->calls[6]['url'] === 'https://api.test/v1/apps/app_1/journeys/jrn_1/runs/run_1', 'journeys.exitRun DELETE');
$hs->journeys->listForUser('external_id', 'u 1');
$assert($t->calls[7]['url'] === 'https://api.test/v1/apps/app_1/users/by/external_id/u%201/journeys', 'journeys.listForUser alias yolu');

$t = new FakeTransport([['status' => 200, 'body' => "date,sent\n2026-10-01,5\n", 'headers' => ['content-type' => 'text/csv; charset=utf-8']]]);
$hs = new Client(['api_key' => 'k', 'base_url' => 'https://api.test', 'app_id' => 'app_1', 'transport' => $t, 'sleep' => $noSleep]);
$csv = $hs->analytics->events(['format' => 'csv']);
$assert($csv === "date,sent\n2026-10-01,5\n" && str_contains($t->calls[0]['url'], 'format=csv'), 'analytics format=csv metin döner');

echo "Import (multipart) ve yeniden deneme" . PHP_EOL;
$t = new FakeTransport([
    ['status' => 429, 'body' => '{"error":{"code":"HS_RATE_LIMITED","message":"meşgul"}}'],
    ['status' => 200, 'body' => '{"id":"imp_1","status":"pending"}'],
]);
$hs = new Client(['api_key' => 'k', 'base_url' => 'https://api.test', 'app_id' => 'app_1', 'transport' => $t, 'sleep' => $noSleep]);
$import = $hs->imports->create('csv_users', "external_id,email\nu1,a@example.com\n", 'kullanicilar.csv');
$assert($import['id'] === 'imp_1' && count($t->calls) === 2, '429 sonrası yeniden denendi');
$ct = $t->calls[1]['headers']['Content-Type'] ?? '';
$assert(preg_match('/^multipart\/form-data; boundary=(\S+)$/', $ct, $bm) === 1, 'multipart Content-Type ve boundary');
$mp = (string) $t->calls[1]['body'];
$assert($t->calls[0]['body'] === $mp, 'yeniden denemede aynı gövde');
$assert(str_contains($mp, "Content-Disposition: form-data; name=\"type\"\r\n\r\ncsv_users\r\n"), 'type alanı');
$assert(str_contains($mp, "name=\"file\"; filename=\"kullanicilar.csv\"\r\nContent-Type: text/csv\r\n\r\nexternal_id,email\nu1,a@example.com\n\r\n"), 'file alanı');
$assert(str_ends_with($mp, '--' . ($bm[1] ?? '') . "--\r\n"), 'kapanış sınırı');

echo "paginate" . PHP_EOL;
$t = new FakeTransport([
    ['status' => 200, 'body' => '{"data":[{"id":"tpl_1"},{"id":"tpl_2"}],"next_cursor":"c2","has_more":true}'],
    ['status' => 200, 'body' => '{"data":[{"id":"tpl_3"}],"next_cursor":null,"has_more":false}'],
]);
$hs = new Client(['api_key' => 'k', 'base_url' => 'https://api.test', 'app_id' => 'app_1', 'transport' => $t, 'sleep' => $noSleep]);
$ids = [];
foreach ($hs->paginate(fn (?string $c) => $hs->templates->list(['cursor' => $c, 'limit' => 2])) as $tpl) {
    $ids[] = $tpl['id'];
}
$assert($ids === ['tpl_1', 'tpl_2', 'tpl_3'] && str_contains($t->calls[1]['url'], 'cursor=c2'), 'tüm sayfalar dolaşıldı');

echo PHP_EOL . ($failures === 0 ? 'Tüm testler geçti.' : "$failures test başarısız.") . PHP_EOL;
exit($failures === 0 ? 0 : 1);
