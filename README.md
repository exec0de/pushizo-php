# herosignal/herosignal-php

HeroSignal PHP sunucu SDK'sı. PHP 8.1+, yalnızca `ext-curl` ve `ext-json`; ek Composer bağımlılığı yoktur.
Yalnızca sunucuda kullanın; API anahtarını istemci koduna gömmeyin. Ayrıntılı rehber:
[`docs/sdk/php.md`](../../docs/sdk/php.md).

## Başlatma

```php
$hs = new \HeroSignal\Client([
    'api_key' => getenv('HS_API_KEY'),   // hs_live_…
    'app_id' => getenv('HS_APP_ID'),     // çağrıda app verilmezse kullanılır
    'base_url' => 'https://api.herosignal.dev',
    'max_retries' => 3,                  // 429/5xx ve ağ hatalarında üstel geri çekilme
    'timeout' => 30,
    'auto_idempotency_key' => true,      // notifications->create ve users->upsert için otomatik UUID
]);
```

Ortak davranışlar:

- Kimlik doğrulama `Authorization: Bearer <api_key>` başlığıyla yapılır.
- App kapsamlı her metodun son parametresi isteğe bağlı `?string $appId`'dir; verilmezse varsayılan app kullanılır.
- Hata yanıtları `HeroSignal\HeroSignalException` (`status`, `errorCode`, `requestId`, `details`, `isRetryable()`) olarak fırlatılır.
- Sorgudaki boolean değerler `true`/`false` metnine çevrilir (`compare`, `include_archived`, `force`).
- `$hs->paginate(fn (?string $c) => $hs->templates->list(['cursor' => $c]))` cursor sayfalı listeleri öğe öğe dolaşır.
- Analitik raporlarında `'format' => 'csv'` verilirse yanıt CSV metni (`string`) olarak döner.

## Kaynaklar

Her public API işlemi (`/sdk/v1` hariç) bir SDK metoduna karşılık gelir; eşleme tablosu
`tests/run.php` içinde `api/openapi.yaml`'a karşı test edilir.

| Kaynak | Metotlar |
| --- | --- |
| `organization` | `get`, `members`, `auditLogs`, `usage` |
| `apps` | `list`, `create`, `get`, `update`, `organization` (eski kısayol) |
| `apiKeys` | `list`, `create`, `update`, `revoke`, `rotate` |
| `notifications` | `create`, `list`, `get`, `cancel`, `estimate`, `deliveries`, `history`, `exportEvents` |
| `users` | `list`, `upsert`, `search`, `getByAlias`, `getByExternalId`, `update`, `delete`, `getIdentity`, `export`, `addAliases`, `removeAlias`, `addSubscription` |
| `subscriptions` | `get`, `update`, `delete`, `getUserIdentity`, `updateByToken`, `setOwner` |
| `inbox` | `list`, `readAll`, `read`, `archive` |
| `events` | `send`, `listDefinitions`, `getDefinition`, `updateDefinition` |
| `segments` | `list`, `create`, `preview`, `get`, `update`, `delete`, `usedBy`, `estimate` |
| `templates` | `list`, `create`, `get`, `update`, `delete`, `copyToApp`, `versions`, `render`, `stats` |
| `campaigns` | `list`, `create`, `get`, `update`, `delete`, `schedule`, `send`, `pause`, `resume`, `cancel`, `duplicate`, `approve`, `stats`, `deliveries`, `resendFailed`, `messages`, `variantStats`, `selectWinner` |
| `journeys` | `list`, `create`, `get`, `update`, `delete`, `saveDraft`, `updateNode`, `versions`, `publish`, `pause`, `resume`, `archive`, `duplicate`, `stats`, `listRuns`, `triggerRun`, `getRun`, `exitRun`, `listForUser` |
| `inAppMessages` | `list`, `create`, `get`, `update`, `delete`, `setStatus`, `selectWinner` |
| `preferences` | `listCategories`, `createCategory`, `updateCategory`, `deleteCategory`, `getUser`, `setUser` |
| `analytics` | `overview`, `messages`, `subscriptions`, `events`, `conversions`, `funnel`, `providers`, `engagement`, `retention` |
| `exports` | `create`, `list`, `get` |
| `imports` | `create` (CSV metni, multipart), `createFromFile`, `list`, `get` |
| `channels` | `list`, `upsert`, `validate` |
| `suppressions` | `list`, `create`, `delete` |
| `webhooks` | `list`, `create`, `get`, `update`, `delete`, `rotateSecret`, `test`, `deliveries`, `replay` |

Webhook alıcıları için `HeroSignal\Webhook::verify()` ve `constructEvents()` kullanılır.

## Örnekler

```php
// Bildirim ve kitle tahmini
$hs->notifications->estimate(['target' => ['included_segments' => ['seg_vip']]]);
$msg = $hs->notifications->create([
    'idempotency_key' => "order-$orderId",
    'target' => ['include_aliases' => ['external_id' => [$userId]]],
    'contents' => ['tr' => 'Siparişin yolda', 'en' => 'Your order has shipped'],
]);
$hs->notifications->exportEvents($msg['id']); // CSV işi; tamamlanınca download_url

// Segment ve kampanya yaşam döngüsü
$segment = $hs->segments->create([
    'name' => 'VIP',
    'definition' => ['all' => [['field' => 'tag:plan', 'op' => 'eq', 'value' => 'pro']]],
]);
$hs->segments->estimate($segment['id'], force: true);
$campaign = $hs->campaigns->create(['name' => 'Bahar indirimi', 'audience' => ['segments' => [$segment['id']]]]);
$hs->campaigns->schedule($campaign['id'], ['send_at' => '2026-11-01T09:00:00Z']);
$hs->campaigns->stats($campaign['id']);

// Şablon render önizlemesi ve başka app'e kopyalama
$hs->templates->render('tpl_…', ['channel' => 'email', 'user' => ['external_id' => 'u-1']]);
$hs->templates->copyToApp('tpl_…', ['target_app_id' => 'app_…']);

// Analitik (dizi veya CSV metni)
$report = $hs->analytics->messages(['from' => '2026-10-01T00:00:00Z', 'compare' => true]);
$csv = $hs->analytics->messages(['format' => 'csv']);

// İçe aktarma (multipart)
$hs->imports->createFromFile('csv_users', __DIR__ . '/kullanicilar.csv');

// Kullanıcı kutusu ve token ile abonelik güncelleme
$hs->inbox->readAll('external_id', 'u-1');
$hs->subscriptions->updateByToken('email', 'ayse@example.com', ['status' => 'unsubscribed']);

// API anahtarı: düz anahtar yalnızca bu yanıtta döner, loglamayın
$created = $hs->apiKeys->create(['name' => 'ci', 'scopes' => ['notifications:write']]);
```

## A/B testi ve tercihler

```php
$cmp = $hs->campaigns->create([
    'name' => 'Bahar',
    'variants' => [
        ['name' => 'A', 'weight_percent' => 45, 'content' => ['contents' => ['tr' => 'Bahar indirimi']]],
        ['name' => 'B', 'weight_percent' => 45, 'content' => ['contents' => ['tr' => '%20 indirim']]],
        ['name' => 'Kontrol', 'weight_percent' => 10, 'is_control' => true],
    ],
    'ab_test' => ['enabled' => true, 'test_percentage' => 30, 'winner' => ['mode' => 'auto', 'metric' => 'click_rate', 'evaluate_after_minutes' => 120]],
]);
$stats = $hs->campaigns->variantStats($cmp['id']); // varyant bazlı oranlar + anlamlılık
$hs->campaigns->selectWinner($cmp['id'], $stats['variants'][0]['variant_id']); // elle seçim

// Kategoriler ve kullanıcı tercihleri (kategoriye kapalı kullanıcıya o kategori gönderilmez)
$hs->preferences->createCategory(['key' => 'marketing', 'name' => ['tr' => 'Pazarlama', 'en' => 'Marketing']]);
$hs->preferences->setUser('external_id', 'u-1', [['category' => 'marketing', 'channel' => 'email', 'opted_in' => false]]);
$hs->templates->stats('tpl_1');
$hs->analytics->retention(['weeks' => 8]);
```

## Journey'ler

Journey'ler taslak graf üzerinde düzenlenir, `publish` ile yeni sürüm olarak yayınlanır; çalışan
kullanıcılar girdikleri sürümde kalır. Graf React Flow biçimindedir (`nodes`, `edges`; düğüm
türleri ve ayarlar için `docs/journeys.md`).

```php
$journey = $hs->journeys->create(['name' => 'Sipariş sonrası']);
$hs->journeys->saveDraft($journey['id'], [
    'graph' => [
        'nodes' => [
            ['id' => 'trg', 'type' => 'trigger', 'position' => ['x' => 0, 'y' => 0], 'data' => ['trigger' => ['kind' => 'api']]],
            ['id' => 'push', 'type' => 'send_push', 'position' => ['x' => 0, 'y' => 120],
             'data' => ['content' => ['contents' => ['tr' => 'Siparişin yolda', 'en' => 'Your order has shipped']]]],
            ['id' => 'end', 'type' => 'exit', 'position' => ['x' => 0, 'y' => 240], 'data' => ['label' => 'Bitiş']],
        ],
        'edges' => [
            ['id' => 'e1', 'source' => 'trg', 'target' => 'push', 'sourceHandle' => 'next'],
            ['id' => 'e2', 'source' => 'push', 'target' => 'end', 'sourceHandle' => 'next'],
        ],
    ],
    'settings' => ['re_entry' => ['mode' => 'after_exit']],
]);
$published = $hs->journeys->publish($journey['id']); // ['journey', 'version', 'issues']

// API tetikleyicisi: kullanıcı external_id, user_id veya alias ile seçilir
$result = $hs->journeys->triggerRun($journey['id'], ['external_id' => 'u-1', 'context' => ['order_id' => 'o-42']]);
if ($result['entered'] && $result['run'] !== null) {
    $run = $hs->journeys->getRun($journey['id'], $result['run']['id']);
}
$stats = $hs->journeys->stats($journey['id'], ['version' => $published['version']]);
foreach ($hs->paginate(fn (?string $c) => $hs->journeys->listRuns($journey['id'], ['status' => 'waiting', 'cursor' => $c])) as $r) {
    echo $r['id'], ' ', $r['current_node_id'], PHP_EOL;
}
```

## Geliştirme

```bash
php tests/run.php                                              # bağımlılıksız test koşucusu
for f in src/*.php src/*/*.php tests/*.php; do php -l "$f"; done
```
