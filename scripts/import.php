<?php

declare(strict_types=1);

require dirname(__DIR__) . '/lib/bootstrap.php';
require dirname(__DIR__) . '/lib/rakuten_api.php';
require dirname(__DIR__) . '/lib/repository.php';

$rakuten = app_config('rakuten');
foreach (['application_id','access_key','affiliate_id'] as $required) {
    if (empty($rakuten[$required])) {
        fwrite(STDERR, "Rakuten API設定不足: {$required}\n");
        exit(1);
    }
}

$client = new RakutenApiClient($rakuten);
$pdo = db();
$pages = max(1, min(100, (int)($rakuten['pages_per_keyword'] ?? 1)));

foreach ((array)($rakuten['keywords'] ?? []) as $keyword) {
    $keyword = trim((string)$keyword);
    if ($keyword === '') continue;

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
        usleep(350000);
    }
}
