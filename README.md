# pushizo/pushizo-php

Pushizo PHP sunucu SDK'sı. PHP 8.1+, yalnızca `ext-curl` ve `ext-json`; ek Composer bağımlılığı yoktur.
Yalnızca sunucuda kullanın; API anahtarını istemci koduna gömmeyin. Ayrıntılı rehber:
[docs.pushizo.com/docs/sdk/php](https://docs.pushizo.com/docs/sdk/php).

## Kurulum

```bash
composer require pushizo/pushizo-php
```

Packagist'e erişilemeyen ortamlarda doğrudan bu depodan:

```bash
composer config repositories.pushizo vcs https://github.com/exec0de/pushizo-php
composer require pushizo/pushizo-php
```

## Başlatma

```php
$hs = new \Pushizo\Client([
    'api_key' => getenv('HS_API_KEY'),   // hs_live_…
    'app_id' => getenv('HS_APP_ID'),     // çağrıda app verilmezse kullanılır
    'base_url' => 'https://api.pushizo.com',
    'max_retries' => 3,                  // 429 her metotta; 5xx/ağ hatası GET/PUT/DELETE'te, POST/PATCH'te yalnızca Idempotency-Key varsa veya istek gönderilemediyse
    'timeout' => 30,
    'auto_idempotency_key' => true,      // notifications->create ve users->upsert için otomatik UUID
]);
```

Ortak davranışlar:

- Kimlik doğrulama `Authorization: Bearer <api_key>` başlığıyla yapılır.
- App kapsamlı her metodun son parametresi isteğe bağlı `?string $appId`'dir; verilmezse varsayılan app kullanılır.
- Hata yanıtları `Pushizo\PushizoException` (`status`, `errorCode`, `requestId`, `details`, `isRetryable()`) olarak fırlatılır.
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
| `inbox` | `list`, `readAll`, `read`, `archive`; ekip gelen kutusu: `listCannedResponses`, `createCannedResponse`, `updateCannedResponse`, `deleteCannedResponse`, `listTeams`, `listAgents`, `getSettings`, `updateSettings`, `metrics` |
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
| `webhooks` | `list`, `create`, `get`, `update`, `delete`, `rotateSecret`, `test`, `deliveries`, `replay`, `preview` |
| `dataFeeds` | `list`, `create`, `get`, `update`, `delete`, `test` |
| `contentTables` | `list`, `create` (CSV metni, multipart), `createFromFile`, `get`, `update`, `delete`, `rows`, `replaceRows`, `replaceRowsFromFile` |
| `warehouseExports` | `tables`, `testConnection`, `list`, `create`, `get`, `update`, `delete`, `test`, `run`, `runs` |
| `conversations` | `list`, `get`, `messages`, `reply`, `markRead`, `update`, `assign`, `updateTags`, `activity` |
| `smsKeywords` | `list`, `create`, `update`, `delete` |
| `whatsappTemplates` | `list`, `sync` |
| `emailDeliverability` | `get`, `update`, `verifyAddress`, `listWarmupPlans`, `createWarmupPlan`, `updateWarmupPlan`, `deleteWarmupPlan` |
| `zapier` | `me`, `subscribe`, `unsubscribe`, `samples` |
| `mcp` | `createCampaignDraft`, `createSegmentDraft` |
| `ads` | `listAccounts`, `listAudiences`, `createAudience`, `getAudience`, `updateAudience`, `deleteAudience`, `syncAudience`, `listSyncs`, `createLookalike`, `listCampaigns`, `getCampaign`, `updateCampaign`, `refreshAccount`, `spendReport`, `attributionReport`, `ltvReport`, `listCapiConfigs`, `listCapiDeliveries`, `listAlertRules`, `createAlertRule`, `updateAlertRule`, `deleteAlertRule`, `listAlerts` |
| `webCampaigns` | `list`, `create`, `get`, `update`, `report`, `setStatus` |
| `forms` | `list`, `create`, `get`, `listSubmissions`, `submit` |
| `surveys` | `list`, `create`, `get`, `setStatus`, `report`, `listResponses`, `respond` |
| `landingPages` | `list`, `get`, `publish`, `unpublish` |

Webhook alıcıları için `Pushizo\Webhook::verify()` ve `constructEvents()` kullanılır.

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

## Dinamik içerik, mesajlaşma ve veri ambarı

```php
// Data feed ve içerik tablosu (şablonda {{ feed.stok.adet }} ve tablo araması)
$feed = $hs->dataFeeds->create(['name' => 'stok', 'url_template' => 'https://example.com/stok/{{ user.external_id }}']);
$hs->dataFeeds->test($feed['id'], ['external_id' => 'u-1']);
$table = $hs->contentTables->createFromFile(['name' => 'urunler', 'key_column' => 'sku'], __DIR__ . '/urunler.csv');
$hs->contentTables->replaceRowsFromFile($table['id'], __DIR__ . '/urunler-yeni.csv'); // sürüm artar

// İki yönlü konuşmalar (WhatsApp 24 saat penceresi dışında yalnızca onaylı şablon)
foreach ($hs->paginate(fn (?string $c) => $hs->conversations->list(['unread' => true, 'cursor' => $c])) as $conv) {
    $hs->conversations->reply($conv['id'], ['text' => 'Teşekkürler, inceliyoruz.']);
    $hs->conversations->markRead($conv['id']);
}

// E-posta adres doğrulama ve ısındırma planı
$hs->emailDeliverability->verifyAddress('ayse@example.com');
$hs->emailDeliverability->createWarmupPlan(['name' => 'Yeni alan adı', 'kind' => 'domain', 'target' => 'mail.example.com']);

// Veri ambarı dışa aktarımı: kimlik bilgisi şifreli saklanır, yanıtta dönmez
$export = $hs->warehouseExports->create([
    'name' => 'BigQuery', 'tables' => ['events', 'deliveries'], 'destination' => 'bigquery',
    'config' => ['project_id' => 'proje-1', 'dataset' => 'pushizo'],
    'credentials' => ['service_account_json' => getenv('GCP_SA_JSON')],
]);
$hs->warehouseExports->run($export['id']);
```

## Gelen kutusu, reklam raporlama ve web büyüme

```php
// Ekip gelen kutusu: durum, atama, etiket ve metrikler (ADR-0040)
$hs->conversations->assign('cnv_1', ['team_id' => 'team_1', 'assignee_id' => null]);
$hs->conversations->updateTags('cnv_1', ['add' => ['plan' => 'pro'], 'remove' => ['lead']]);
$hs->conversations->update('cnv_1', ['status' => 'resolved']);
$metrics = $hs->inbox->metrics(['from' => '2026-10-01T00:00:00Z', 'channel' => 'chat']);
$hs->inbox->createCannedResponse(['shortcut' => 'iade', 'title' => 'İade', 'body' => 'Merhaba {{ contact.name }}, iadeniz alındı.']);

// Reklam raporlama ve bütçe uyarıları (ADR-0038); from/to zorunlu
$spend = $hs->ads->spendReport(['from' => '2026-10-01', 'to' => '2026-10-31', 'group_by' => 'campaign']);
$hs->ads->updateCampaign('ade_1', ['status' => 'paused']); // platforma da yazılır
$hs->ads->createAlertRule(['name' => 'Günlük tavan', 'kind' => 'daily_spend', 'threshold' => 500, 'recipients' => ['pazarlama@example.com']]);

// Web büyüme (ADR-0039): kampanya taslak oluşur, setStatus ile yayınlanır
$popup = $hs->webCampaigns->create([
    'name' => 'Bülten pop-up',
    'display' => ['layout' => 'popup', 'blocks' => [['type' => 'title', 'text' => ['tr' => 'Bültenimize katılın']]], 'form_id' => 'frm_1'],
    'trigger' => ['kind' => 'exit_intent'],
]);
$hs->webCampaigns->setStatus($popup['id'], 'active');
$hs->forms->submit('frm_1', ['values' => ['email' => 'ayse@example.com'], 'external_id' => 'u-1']); // sunucu tarafı gönderim
foreach ($hs->paginate(fn (?string $c) => $hs->forms->listSubmissions('frm_1', ['status' => 'confirmed', 'cursor' => $c])) as $s) {
    echo $s['id'], PHP_EOL;
}
$nps = $hs->surveys->report('srv_1', ['days' => 30]);
$hs->landingPages->publish('lp_1');
```

## Geliştirme

```bash
php tests/run.php                                              # bağımlılıksız test koşucusu
for f in src/*.php src/*/*.php tests/*.php; do php -l "$f"; done
```
