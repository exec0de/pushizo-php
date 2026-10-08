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
    'GET /v1/apps' => fn (Client $c) => $c->apps->list(),
    'POST /v1/apps' => fn (Client $c) => $c->apps->create(['name' => 'Yeni app']),
    'GET /v1/apps/{appId}' => fn (Client $c) => $c->apps->get(),
    'PATCH /v1/apps/{appId}' => fn (Client $c) => $c->apps->update(['name' => 'Ad']),
    'GET /v1/apps/{appId}/api-keys' => fn (Client $c) => $c->apiKeys->list(),
    'POST /v1/apps/{appId}/api-keys' => fn (Client $c) => $c->apiKeys->create(['name' => 'ci', 'scopes' => ['notifications:write']]),
    'PATCH /v1/apps/{appId}/api-keys/{keyId}' => fn (Client $c) => $c->apiKeys->update('key_1', ['name' => 'x']),
    'DELETE /v1/apps/{appId}/api-keys/{keyId}' => fn (Client $c) => $c->apiKeys->revoke('key_1'),
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
];

$assert(count($specOps) >= 141, 'spec en az 141 public işlem içerir (' . count($specOps) . ')');
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
    ['status' => 503, 'body' => '{"error":{"code":"HS_UNAVAILABLE","message":"meşgul"}}'],
    ['status' => 200, 'body' => '{"id":"imp_1","status":"pending"}'],
]);
$hs = new Client(['api_key' => 'k', 'base_url' => 'https://api.test', 'app_id' => 'app_1', 'transport' => $t, 'sleep' => $noSleep]);
$import = $hs->imports->create('csv_users', "external_id,email\nu1,a@example.com\n", 'kullanicilar.csv');
$assert($import['id'] === 'imp_1' && count($t->calls) === 2, '503 sonrası yeniden denendi');
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
