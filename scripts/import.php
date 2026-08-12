<?php

declare(strict_types=1);

require dirname(__DIR__) . '/lib/bootstrap.php';
require dirname(__DIR__) . '/lib/admin.php';
require dirname(__DIR__) . '/lib/rakuten_api.php';
require dirname(__DIR__) . '/lib/repository.php';

$pdo = db();
$base = (array)app_config('rakuten', []);
$rakuten = $base;
$rakuten['application_id'] = setting($pdo, 'rakuten_application_id', (string)($base['application_id'] ?? ''));
$rakuten['access_key'] = setting($pdo, 'rakuten_access_key', (string)($base['access_key'] ?? ''));
$rakuten['affiliate_id'] = setting($pdo, 'rakuten_affiliate_id', (string)($base['affiliate_id'] ?? ''));
$rakuten['hits'] = max(1, min(30, (int)setting($pdo, 'rakuten_hits', (string)($base['hits'] ?? 30))));
$rakuten['pages_per_keyword'] = max(1, min(100, (int)setting($pdo, 'rakuten_pages', (string)($base['pages_per_keyword'] ?? 1))));
$rakuten['sort'] = setting($pdo, 'rakuten_sort', (string)($base['sort'] ?? '-reviewCount'));
$keywordText = setting($pdo, 'rakuten_keywords', implode("\n", (array)($base['keywords'] ?? [])));
$rakuten['keywords'] = array_values(array_unique(array_filter(array_map('trim', preg_split('/\R/u', $keywordText) ?: []))));

foreach (['application_id','access_key','affiliate_id'] as $required) {
    if (empty($rakuten[$required])) {
        fwrite(STDERR, "Rakuten API設定不足: {$required}\n");
        exit(1);
    }
}

$client = new RakutenApiClient($rakuten);
$pages = (int)$rakuten['pages_per_keyword'];

foreach ($rakuten['keywords'] as $keyword) {
    for ($page = 1; $page <= $pages; $page++) {
        try {
            $data = $client->search($keyword, $page);
            $items = $data['items'] ?? [];
            $pdo->beginTransaction();
            foreach ($items as $item) {
                if (is_array($item)) save_item($pdo, $item);
            }
            $stmt = $pdo->prepare("INSERT INTO import_logs(keyword,page_no,fetched_count,status,message) VALUES(?,?,?,'success',NULL)");
            $stmt->execute([$keyword, $page, count($items)]);
            $pdo->commit();
            echo sprintf("[%s] %s page=%d imported=%d\n", date('Y-m-d H:i:s'), $keyword, $page, count($items));
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $stmt = $pdo->prepare("INSERT INTO import_logs(keyword,page_no,fetched_count,status,message) VALUES(?,?,0,'error',?)");
            $stmt->execute([$keyword, $page, mb_substr($e->getMessage(), 0, 1000)]);
            fwrite(STDERR, $keyword . ' page=' . $page . ': ' . $e->getMessage() . PHP_EOL);
        }
        usleep(500000);
    }
}
