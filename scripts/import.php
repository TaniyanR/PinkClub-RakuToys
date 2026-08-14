<?php

declare(strict_types=1);

require dirname(__DIR__) . '/lib/bootstrap.php';
require dirname(__DIR__) . '/lib/admin.php';
require dirname(__DIR__) . '/lib/rakuten_api.php';
require dirname(__DIR__) . '/lib/repository.php';

/** @return resource|null */
function import_lock()
{
    $directory = dirname(__DIR__) . '/storage/locks';
    if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException('cronロック用ディレクトリを作成できません');
    }
    $handle = @fopen($directory . '/rakuten-import.lock', 'c');
    if (!is_resource($handle)) {
        throw new RuntimeException('cronロックファイルを開けません');
    }
    if (!@flock($handle, LOCK_EX | LOCK_NB)) {
        fclose($handle);
        return null;
    }
    return $handle;
}

function cleanup_old_import_data(PDO $pdo, int $batchSize = 500): void
{
    $batchSize = max(50, min(2000, $batchSize));
    foreach ([
        ['table' => 'import_logs', 'column' => 'created_at', 'days' => 90],
        ['table' => 'page_views', 'column' => 'created_at', 'days' => 730],
        ['table' => 'affiliate_clicks', 'column' => 'created_at', 'days' => 730],
    ] as $target) {
        try {
            $sql = sprintf(
                'DELETE FROM `%s` WHERE `%s` < DATE_SUB(NOW(), INTERVAL %d DAY) ORDER BY id ASC LIMIT %d',
                $target['table'],
                $target['column'],
                $target['days'],
                $batchSize
            );
            $pdo->exec($sql);
        } catch (Throwable $e) {
            error_log('[resource_cleanup] ' . $target['table'] . ': ' . $e->getMessage());
        }
    }
}

$importLock = import_lock();
if (!is_resource($importLock)) {
    echo '[' . date('Y-m-d H:i:s') . "] import skipped: another process is running\n";
    exit(0);
}
register_shutdown_function(static function () use ($importLock): void {
    @flock($importLock, LOCK_UN);
    fclose($importLock);
});

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

$jobs = [];
foreach ($rakuten['keywords'] as $keyword) {
    for ($page = 1; $page <= $pages; $page++) {
        $jobs[] = ['keyword' => $keyword, 'page' => $page];
    }
}

if ($jobs !== []) {
    $cursor = max(0, (int)setting($pdo, 'rakuten_import_cursor', '0')) % count($jobs);
    $jobsPerRun = min(3, count($jobs));
    for ($processed = 0; $processed < $jobsPerRun; $processed++) {
        $jobIndex = ($cursor + $processed) % count($jobs);
        $keyword = (string)$jobs[$jobIndex]['keyword'];
        $page = (int)$jobs[$jobIndex]['page'];
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
        save_setting($pdo, 'rakuten_import_cursor', (string)(($jobIndex + 1) % count($jobs)));
        if ($processed + 1 < $jobsPerRun) {
            sleep(1);
        }
    }
}

cleanup_old_import_data($pdo, 500);
