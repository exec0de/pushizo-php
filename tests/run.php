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

echo "identityHash" . PHP_EOL;
$assert(Client::identityHash('secret', 'u-1') === hash_hmac('sha256', 'u-1', 'secret'), 'HMAC-SHA256 eşleşir');

echo "Webhook imza doğrulama" . PHP_EOL;
$secret = 'whsec_test_secret';
$body = '{"id":"whe_1","type":"user.created","created_at":"2026-10-07T00:00:00Z","app_id":"app_1","data":{"user":{"id":"usr_1"}}}';
$ts = 1800000000;
$sig = static fn (string $s, string $p) => hash_hmac('sha256', $ts . '.' . $p, $s);
$header = "t=$ts,v1=" . $sig($secret, $body);
$assert(\HeroSignal\Webhook::verify($body, $header, $secret, 300, $ts) === null, 'geçerli imza');
$assert(\HeroSignal\Webhook::verify($body . ' ', $header, $secret, 300, $ts) === 'signature_mismatch', 'değişmiş gövde reddedilir');
$assert(\HeroSignal\Webhook::verify($body, $header, $secret, 300, $ts + 301) === 'timestamp_out_of_tolerance', 'replay (eski t) reddedilir');
$assert(\HeroSignal\Webhook::verify($body, null, $secret) === 'missing_header', 'eksik başlık');
$rot = "t=$ts,v1=" . $sig('yeni', $body) . ',v1=' . $sig($secret, $body);
$assert(\HeroSignal\Webhook::verify($body, $rot, ['yeni'], 300, $ts) === null, 'rotasyonda yeni secret');
$assert(\HeroSignal\Webhook::verify($body, $rot, $secret, 300, $ts) === null, 'rotasyonda eski secret');
$events = \HeroSignal\Webhook::constructEvents($body, $header, $secret, 300, $ts);
$assert(count($events) === 1 && $events[0]['type'] === 'user.created', 'tekil olay ayrıştırılır');
$batch = '{"events":[' . $body . ',' . $body . ']}';
$assert(count(\HeroSignal\Webhook::constructEvents($batch, "t=$ts,v1=" . $sig($secret, $batch), $secret, 300, $ts)) === 2, 'batch gövdesi');
try {
    \HeroSignal\Webhook::constructEvents($body, 'v1=00', $secret);
    $assert(false, 'geçersiz başlık istisna fırlatmalı');
} catch (\HeroSignal\WebhookSignatureException $e) {
    $assert($e->reason === 'malformed_header', 'WebhookSignatureException nedeni');
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

echo "Faz 10B uçları (sayım, olay CSV, kimlik, dışa aktarım, inbox, token ile güncelleme)" . PHP_EOL;
$t = new FakeTransport(array_fill(0, 10, ['status' => 200, 'body' => '{"identity":{"hs_id":"usr_1"}}']));
$hs = new Client(['api_key' => 'k', 'base_url' => 'https://api.test', 'app_id' => 'app_1', 'transport' => $t, 'sleep' => $noSleep]);
$hs->notifications->estimate(['email_to' => ['a@example.com']]);
$hs->notifications->exportEvents('msg_1');
$hs->notifications->history('msg_1');
$identity = $hs->users->identity('external_id', 'u 1');
$hs->users->export('external_id', 'u1');
$hs->users->inbox('external_id', 'u1', ['limit' => 5, 'include_archived' => true]);
$hs->users->inboxMarkRead('external_id', 'u1', 'inb_1');
$hs->users->inboxArchive('external_id', 'u1', 'inb_1');
$hs->subscriptions->identity('sub_1');
$hs->subscriptions->updateByToken('email', 'Ali@Example.com', ['status' => 'unsubscribed']);
$urls = array_map(static fn ($c) => $c['method'] . ' ' . $c['url'], $t->calls);
$assert($urls === [
    'POST https://api.test/v1/notifications/estimate',
    'POST https://api.test/v1/notifications/msg_1/export-events?app_id=app_1',
    'GET https://api.test/v1/notifications/msg_1/history?app_id=app_1',
    'GET https://api.test/v1/apps/app_1/users/by/external_id/u%201/identity',
    'GET https://api.test/v1/apps/app_1/users/by/external_id/u1/export',
    'GET https://api.test/v1/apps/app_1/users/by/external_id/u1/inbox?limit=5&include_archived=true',
    'POST https://api.test/v1/apps/app_1/users/by/external_id/u1/inbox/inb_1/read',
    'DELETE https://api.test/v1/apps/app_1/users/by/external_id/u1/inbox/inb_1',
    'GET https://api.test/v1/apps/app_1/subscriptions/sub_1/user/identity',
    'PATCH https://api.test/v1/apps/app_1/subscriptions/by-token/email/Ali%40Example.com',
], 'yollar');
$assert(json_decode((string) $t->calls[0]['body'], true) === ['app_id' => 'app_1', 'target' => ['email_to' => ['a@example.com']]], 'sayım gövdesi');
$assert($identity['identity']['hs_id'] === 'usr_1', 'kimlik yanıtı');
$assert(json_decode((string) $t->calls[9]['body'], true) === ['status' => 'unsubscribed'], 'token ile güncelleme gövdesi');

echo "şablon, in-app, API anahtarı ve webhook kaynakları" . PHP_EOL;
$t = new FakeTransport(array_fill(0, 14, ['status' => 200, 'body' => '{}']));
$hs = new Client(['api_key' => 'k', 'base_url' => 'https://api.test', 'app_id' => 'app_1', 'transport' => $t, 'sleep' => $noSleep]);
$hs->templates->list(['q' => 'hoş']);
$hs->templates->create(['name' => 'Hoş geldin', 'content' => ['contents' => ['en' => 'Hi']]]);
$hs->templates->render('tpl_1');
$hs->templates->copy('tpl_1', 'app_2');
$hs->templates->delete('tpl_1');
$hs->inAppMessages->list(['status' => 'active']);
$hs->inAppMessages->setStatus('iam_1', 'paused');
$hs->inAppMessages->delete('iam_1');
$hs->apiKeys->create(['name' => 'CI', 'scopes' => ['notifications:write']]);
$hs->apiKeys->update('key_1', ['ip_allowlist' => ['10.0.0.0/8']]);
$hs->apiKeys->rotate('key_1');
$hs->webhooks->create(['url' => 'https://x.example.com/h', 'events' => ['delivery.bounced']]);
$hs->webhooks->deliveries('whk_1', ['status' => 'dead']);
$hs->webhooks->replay('whd_1');
$urls = array_map(static fn ($c) => $c['method'] . ' ' . $c['url'], $t->calls);
$assert($urls === [
    'GET https://api.test/v1/apps/app_1/templates?q=ho%C5%9F',
    'POST https://api.test/v1/apps/app_1/templates',
    'POST https://api.test/v1/apps/app_1/templates/tpl_1/render',
    'POST https://api.test/v1/apps/app_1/templates/tpl_1/copy',
    'DELETE https://api.test/v1/apps/app_1/templates/tpl_1',
    'GET https://api.test/v1/apps/app_1/in-app-messages?status=active',
    'POST https://api.test/v1/apps/app_1/in-app-messages/iam_1/status',
    'DELETE https://api.test/v1/apps/app_1/in-app-messages/iam_1',
    'POST https://api.test/v1/apps/app_1/api-keys',
    'PATCH https://api.test/v1/apps/app_1/api-keys/key_1',
    'POST https://api.test/v1/apps/app_1/api-keys/key_1/rotate',
    'POST https://api.test/v1/apps/app_1/webhooks',
    'GET https://api.test/v1/apps/app_1/webhooks/whk_1/deliveries?status=dead',
    'POST https://api.test/v1/apps/app_1/webhooks/deliveries/whd_1/replay',
], 'yollar');
$assert($t->calls[2]['body'] === '{}', 'boş önizleme gövdesi nesne');
$assert(json_decode((string) $t->calls[3]['body'], true) === ['target_app_id' => 'app_2'], 'kopyalama gövdesi');
$assert(json_decode((string) $t->calls[6]['body'], true) === ['status' => 'paused'], 'durum gövdesi');

echo PHP_EOL . ($failures === 0 ? 'Tüm testler geçti.' : "$failures test başarısız.") . PHP_EOL;
exit($failures === 0 ? 0 : 1);
