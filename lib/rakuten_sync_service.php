<?php

declare(strict_types=1);

require_once __DIR__ . '/rakuten_api_client.php';

require_once __DIR__ . '/repository.php';

class RakutenSyncService
{
    public function __construct(private readonly RakutenApiClient $client, private readonly PDO $pdo)
    {
        $this->ensureSchema();
    }

    public function syncItems(array $params = []): int
    {
        $page = $this->normalizePage((int)($params['page'] ?? $params['offset'] ?? 1));
        $hits = min(30, max(1, (int)($params['hits'] ?? 30)));
        unset($params['offset']);
        $response = $this->client->fetchItems(array_merge($params, ['hits' => $hits, 'page' => $page]));
        return $this->saveItems($this->normalizeItemsResponse($response), 'items');
    }

    public function syncItemsBatch(int $batch, int $page = 1, array $extraParams = [], array $excludeKeywords = []): array
    {
        $targetNew = max(1, $batch);
        $currentPage = $this->normalizePage($page);
        $nextPage = $currentPage;
        $apiCount = 0;
        $newCount = 0;
        $updatedCount = 0;
        $excludedCount = 0;
        $checkedCount = 0;
        $requests = 0;
        $maxRequests = min(10, max(1, (int)ceil($targetNew / 30) + 2));

        while ($newCount < $targetNew && $requests < $maxRequests) {
            $requests++;
            $response = $this->client->fetchItems(array_merge($extraParams, [
                'hits' => 30,
                'page' => $currentPage,
            ]));
            $fetchedItems = $this->normalizeItemsResponse($response);
            $fetchedCount = count($fetchedItems);
            $apiCount += $fetchedCount;
            $checkedCount += $fetchedCount;
            if ($fetchedCount === 0) {
                $nextPage = 1;
                break;
            }

            $existing = $this->itemsExistByContentIds(array_map(static fn (array $item): string => (string)($item['content_id'] ?? ''), $fetchedItems));
            $saveItems = [];
            foreach ($fetchedItems as $item) {
                $title = (string)($item['title'] ?? '');
                $excluded = false;
                foreach ($excludeKeywords as $keyword) {
                    $keyword = trim((string)$keyword);
                    if ($keyword !== '' && mb_strpos($title, $keyword) !== false) {
                        $excluded = true;
                        break;
                    }
                }
                if ($excluded) {
                    $excludedCount++;
                    continue;
                }

                $contentId = (string)($item['content_id'] ?? '');
                $exists = isset($existing[$contentId]);
                if (!$exists && $newCount >= $targetNew) {
                    break;
                }
                $saveItems[] = $item;
                if ($exists) {
                    $updatedCount++;
                } else {
                    $newCount++;
                    $existing[$contentId] = true;
                }
            }
            if ($saveItems !== []) {
                $this->saveItemsWithStats($saveItems, 'items', false);
            }

            $pageCount = min(100, max(1, (int)($response['pageCount'] ?? 100)));
            $nextPage = $currentPage >= $pageCount ? 1 : $currentPage + 1;
            if ($nextPage === 1 || $fetchedCount < 30) {
                break;
            }
            $currentPage = $nextPage;
        }

        $message = sprintf(
            '楽天商品を同期しました。API取得: %d件 / 新規: %d件 / 更新: %d件 / 除外: %d件 / 次回page: %d',
            $apiCount,
            $newCount,
            $updatedCount,
            $excludedCount,
            $nextPage
        );
        $this->logSync('items', 1, $newCount, $message);

        return [
            'synced_count' => $newCount,
            'api_count' => $apiCount,
            'new_count' => $newCount,
            'updated_count' => $updatedCount,
            'excluded_count' => $excludedCount,
            'checked_count' => $checkedCount,
            'next_offset' => $nextPage,
            'next_page' => $nextPage,
            'message' => $message,
        ];
    }

    private function normalizePage(int $page): int
    {
        return min(100, max(1, $page));
    }

    private function normalizeItemsResponse(array $response): array
    {
        $rows = $response['items'] ?? [];
        if (!is_array($rows)) {
            return [];
        }

        $items = [];
        foreach ($rows as $entry) {
            $row = is_array($entry['item'] ?? null) ? $entry['item'] : $entry;
            if (!is_array($row)) {
                continue;
            }
            $itemCode = trim((string)($row['itemCode'] ?? ''));
            $title = trim((string)($row['itemName'] ?? ''));
            if ($itemCode === '' || $title === '') {
                continue;
            }
            $small = $this->firstImageUrl($row['smallImageUrls'] ?? []);
            $medium = $this->firstImageUrl($row['mediumImageUrls'] ?? []);
            $image = $medium !== '' ? $medium : $small;
            $genreId = trim((string)($row['genreId'] ?? ''));
            $genreName = trim((string)($response['genreInformation']['current']['nameJa'] ?? $response['genreInformation']['genre']['nameJa'] ?? 'アダルトグッズ'));
            $shopCode = trim((string)($row['shopCode'] ?? ''));
            $shopName = trim((string)($row['shopName'] ?? ''));
            $price = max(0, (int)($row['itemPrice'] ?? 0));
            $items[] = [
                'content_id' => $itemCode,
                'product_id' => $itemCode,
                'title' => $title,
                'service_code' => 'ichiba',
                'service_name' => '楽天市場',
                'floor_code' => 'adult-goods',
                'floor_name' => 'アダルトグッズ',
                'category_name' => 'アダルトグッズ',
                'volume' => '',
                'review_count' => max(0, (int)($row['reviewCount'] ?? 0)),
                'review_average' => max(0, (float)($row['reviewAverage'] ?? 0)),
                'url' => trim((string)($row['itemUrl'] ?? '')),
                'affiliate_url' => trim((string)($row['affiliateUrl'] ?? $row['itemUrl'] ?? '')),
                'image_list' => $image,
                'image_small' => $small !== '' ? $small : $image,
                'image_large' => $image,
                'sample_movie_url_476' => '',
                'sample_movie_url_560' => '',
                'sample_movie_url_644' => '',
                'sample_movie_url_720' => '',
                'sample_movie_pc_flag' => 0,
                'sample_movie_sp_flag' => 0,
                'price_min_text' => $price > 0 ? '¥' . number_format($price) : '',
                'list_price_text' => $price > 0 ? '¥' . number_format($price) : '',
                'release_date' => null,
                'actresses' => [],
                'genres' => $genreId !== '' ? [['id' => 'rakuten-genre:' . $genreId, 'name' => $genreName !== '' ? $genreName : 'アダルトグッズ']] : [],
                'campaigns' => ((int)($row['pointRate'] ?? 1)) > 1 ? [['id' => 'point-rate', 'name' => 'ポイント' . (int)$row['pointRate'] . '倍']] : [],
                'labels' => [],
                'directors' => [],
                'makers' => ($shopCode !== '' && $shopName !== '') ? [['id' => 'rakuten-shop:' . $shopCode, 'name' => $shopName]] : [],
                'series' => [],
                'authors' => [],
                'actors' => [],
                'raw' => $row,
            ];
        }
        return $items;
    }

    private function firstImageUrl(mixed $value): string
    {
        if (is_string($value)) {
            return trim($value);
        }
        if (!is_array($value)) {
            return '';
        }
        foreach ($value as $entry) {
            $url = is_array($entry) ? trim((string)($entry['imageUrl'] ?? $entry['url'] ?? '')) : trim((string)$entry);
            if ($url !== '') {
                return $url;
            }
        }
        return '';
    }

    private function saveItems(array $items, string $logType): int
    {
        $stats = $this->saveItemsWithStats($items, $logType, true);
        return (int)$stats['saved_count'];
    }

    private function saveItemsWithStats(array $items, string $logType, bool $writeLog): array
    {
        $count = 0;
        $newCount = 0;
        $updatedCount = 0;
        $this->pdo->beginTransaction();
        try {
            foreach ($items as $item) {
                $exists = $this->itemExistsByContentId((string)($item['content_id'] ?? ''));
                $itemId = $this->upsertItem($item);
                $this->rebuildItemRelations($itemId, $item);
                if (function_exists('generate_tags_for_item')) {
                    generate_tags_for_item([
                        'content_id' => $item['content_id'] ?? '',
                        'title' => $item['title'] ?? '',
                        'category_name' => $item['category_name'] ?? '',
                    ]);
                }
                $count++;
                if ($exists) {
                    $updatedCount++;
                } else {
                    $newCount++;
                }
            }
            $this->pdo->commit();
            if ($writeLog) {
                $this->logSync($logType, 1, $count, 'Item sync completed.');
            }
            return ['saved_count' => $count, 'new_count' => $newCount, 'updated_count' => $updatedCount];
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            if ($writeLog) {
                $this->logSync($logType, 0, 0, $e->getMessage());
            }
            throw $e;
        }
    }

    private function itemExistsByContentId(string $contentId): bool
    {
        if ($contentId === '') {
            return false;
        }
        $stmt = $this->pdo->prepare('SELECT 1 FROM items WHERE content_id = ? LIMIT 1');
        $stmt->execute([$contentId]);
        return (bool)$stmt->fetchColumn();
    }

    /**
     * @param string[] $contentIds
     * @return array<string, bool>
     */
    private function itemsExistByContentIds(array $contentIds): array
    {
        $contentIds = array_values(array_unique(array_filter(array_map('strval', $contentIds), static fn (string $contentId): bool => $contentId !== '')));
        if ($contentIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($contentIds), '?'));
        $stmt = $this->pdo->prepare("SELECT content_id FROM items WHERE content_id IN ({$placeholders})");
        $stmt->execute($contentIds);

        $exists = [];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) ?: [] as $contentId) {
            $exists[(string)$contentId] = true;
        }
        return $exists;
    }

    private function upsertSimple(string $table, string $codeColumn, string $code, string $name): void
    {
        $this->pdo->prepare("INSERT INTO {$table}({$codeColumn},name,updated_at) VALUES(?,?,NOW()) ON DUPLICATE KEY UPDATE name=VALUES(name),updated_at=NOW()")
            ->execute([$code, $name]);
    }

    private function upsertItem(array $item): int
    {
        $sql = 'INSERT INTO items(content_id,product_id,item_source,title,service_code,service_name,floor_code,floor_name,category_name,volume,review_count,review_average,url,affiliate_url,image_list,image_small,image_large,sample_movie_url_476,sample_movie_url_560,sample_movie_url_644,sample_movie_url_720,sample_movie_pc_flag,sample_movie_sp_flag,price_min_text,list_price_text,release_date,raw_json,updated_at)
                VALUES(:content_id,:product_id,:item_source,:title,:service_code,:service_name,:floor_code,:floor_name,:category_name,:volume,:review_count,:review_average,:url,:affiliate_url,:image_list,:image_small,:image_large,:u476,:u560,:u644,:u720,:pc,:sp,:price_min,:list_price,:release_date,:raw_json,NOW())
                ON DUPLICATE KEY UPDATE item_source=VALUES(item_source),title=VALUES(title),service_name=VALUES(service_name),floor_name=VALUES(floor_name),category_name=VALUES(category_name),volume=VALUES(volume),review_count=VALUES(review_count),review_average=VALUES(review_average),url=VALUES(url),affiliate_url=VALUES(affiliate_url),image_list=VALUES(image_list),image_small=VALUES(image_small),image_large=VALUES(image_large),sample_movie_url_476=VALUES(sample_movie_url_476),sample_movie_url_560=VALUES(sample_movie_url_560),sample_movie_url_644=VALUES(sample_movie_url_644),sample_movie_url_720=VALUES(sample_movie_url_720),sample_movie_pc_flag=VALUES(sample_movie_pc_flag),sample_movie_sp_flag=VALUES(sample_movie_sp_flag),price_min_text=VALUES(price_min_text),list_price_text=VALUES(list_price_text),release_date=VALUES(release_date),raw_json=VALUES(raw_json),updated_at=NOW()';
        $this->pdo->prepare($sql)->execute([
            'content_id' => $item['content_id'], 'product_id' => $item['product_id'], 'item_source' => 'rakuten_product', 'title' => $item['title'],
            'service_code' => $item['service_code'], 'service_name' => $item['service_name'], 'floor_code' => $item['floor_code'], 'floor_name' => $item['floor_name'],
            'category_name' => $item['category_name'], 'volume' => $item['volume'], 'review_count' => $item['review_count'], 'review_average' => $item['review_average'],
            'url' => $item['url'], 'affiliate_url' => $item['affiliate_url'], 'image_list' => $item['image_list'], 'image_small' => $item['image_small'], 'image_large' => $item['image_large'],
            'u476' => $item['sample_movie_url_476'], 'u560' => $item['sample_movie_url_560'], 'u644' => $item['sample_movie_url_644'], 'u720' => $item['sample_movie_url_720'],
            'pc' => $item['sample_movie_pc_flag'], 'sp' => $item['sample_movie_sp_flag'], 'price_min' => $item['price_min_text'], 'list_price' => $item['list_price_text'],
            'release_date' => $item['release_date'], 'raw_json' => json_encode($item['raw'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        $idStmt = $this->pdo->prepare('SELECT id FROM items WHERE content_id = ?');
        $idStmt->execute([$item['content_id']]);
        return (int) $idStmt->fetchColumn();
    }

    private function rebuildItemRelations(int $itemId, array $item): void
    {
        $tables = ['item_actresses', 'item_genres', 'item_campaigns', 'item_labels', 'item_directors', 'item_makers', 'item_series', 'item_authors', 'item_actors'];
        foreach ($tables as $table) {
            $this->pdo->prepare("DELETE FROM {$table} WHERE item_id = ?")->execute([$itemId]);
        }

        $this->insertRelation($itemId, 'item_actresses', 'actress_name', $item['actresses']);
        $this->insertRelation($itemId, 'item_genres', 'genre_name', $item['genres']);
        $this->insertRelation($itemId, 'item_campaigns', 'campaign_name', $item['campaigns']);
        $this->insertRelation($itemId, 'item_labels', 'label_name', $item['labels']);
        $this->insertRelation($itemId, 'item_directors', 'director_name', $item['directors']);
        $this->insertRelation($itemId, 'item_makers', 'maker_name', $item['makers']);
        $this->insertRelation($itemId, 'item_series', 'series_name', $item['series']);
        $this->insertRelation($itemId, 'item_authors', 'author_name', $item['authors']);
        $this->insertRelation($itemId, 'item_actors', 'actor_name', $item['actors'] ?? []);
    }

    private function insertRelation(int $itemId, string $table, string $nameCol, array $rows): void
    {
        $masterMap = [
            'item_genres' => 'genres',
            'item_makers' => 'makers',
            'item_series' => 'series_master',
            'item_authors' => 'authors',
        ];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $dmmId = trim((string)($row['id'] ?? ''));
            $name = (string) ($row['name'] ?? '');
            if ($name === '') {
                continue;
            }

            if ($dmmId === '') {
                $dmmId = 'name:' . sha1(mb_strtolower($name, 'UTF-8'));
            }

            $this->pdo->prepare("INSERT IGNORE INTO {$table}(item_id,dmm_id,{$nameCol}) VALUES(?,?,?)")
                ->execute([$itemId, $dmmId, $name]);

            $masterTable = $masterMap[$table] ?? null;
            if (is_string($masterTable) && $masterTable !== '' && $dmmId !== '') {
                $this->pdo->prepare("INSERT INTO {$masterTable}(dmm_id,name,updated_at) VALUES(?,?,NOW()) ON DUPLICATE KEY UPDATE name=VALUES(name), updated_at=NOW()")
                    ->execute([$dmmId, $name]);
            }
        }
    }

    public function logSync(string $type, int $isSuccess, int $count, string $message): void
    {
        $this->pdo->prepare('INSERT INTO sync_logs(sync_type,is_success,synced_count,message,created_at) VALUES(?,?,?,?,NOW())')
            ->execute([$type, $isSuccess, $count, mb_substr($message, 0, 1000)]);
    }

    private function ensureSchema(): void
    {
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS item_makers (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,item_id INT UNSIGNED NOT NULL,dmm_id VARCHAR(64) NULL,maker_name VARCHAR(255) NOT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY uk_item_maker (item_id,dmm_id),CONSTRAINT fk_item_maker_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS item_series (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,item_id INT UNSIGNED NOT NULL,dmm_id VARCHAR(64) NULL,series_name VARCHAR(255) NOT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY uk_item_series (item_id,dmm_id),CONSTRAINT fk_item_series_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS item_authors (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,item_id INT UNSIGNED NOT NULL,dmm_id VARCHAR(64) NULL,author_name VARCHAR(255) NOT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY uk_item_author (item_id,dmm_id),CONSTRAINT fk_item_author_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS item_actors (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,item_id INT UNSIGNED NOT NULL,dmm_id VARCHAR(64) NULL,actor_name VARCHAR(255) NOT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY uk_item_actor (item_id,dmm_id),CONSTRAINT fk_item_actor_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS sync_job_state (job_key VARCHAR(64) PRIMARY KEY,next_offset INT NOT NULL DEFAULT 1,next_initial VARCHAR(10) NULL,last_run_at DATETIME NULL,last_success TINYINT(1) NOT NULL DEFAULT 0,last_message TEXT NULL,lock_until DATETIME NULL,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

        $itemColumns = [];
        $itemStmt = $this->pdo->query('SHOW COLUMNS FROM items');
        foreach (($itemStmt ? $itemStmt->fetchAll(PDO::FETCH_ASSOC) : []) as $col) {
            $itemColumns[(string)($col['Field'] ?? '')] = true;
        }
        if (!isset($itemColumns['view_count'])) {
            $this->pdo->exec('ALTER TABLE items ADD COLUMN view_count INT NOT NULL DEFAULT 0');
        }
        if (!isset($itemColumns['item_source'])) {
            $this->pdo->exec('ALTER TABLE items ADD COLUMN item_source VARCHAR(32) NOT NULL DEFAULT "unknown" AFTER product_id');
        }
        try {
            $this->pdo->exec('CREATE INDEX idx_items_item_source_release ON items(item_source, release_date, id)');
        } catch (Throwable) {
        }

        $this->pdo->exec('CREATE TABLE IF NOT EXISTS page_views (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,item_id INT UNSIGNED NOT NULL,viewed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,ip_hash VARCHAR(64) NULL,user_agent VARCHAR(255) NULL,INDEX idx_page_views_item_date (item_id, viewed_at),CONSTRAINT fk_page_views_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS tags (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,name VARCHAR(100) NOT NULL UNIQUE,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS item_tags (item_id INT UNSIGNED NOT NULL,tag_id BIGINT UNSIGNED NOT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY (item_id, tag_id),INDEX idx_item_tags_tag (tag_id),CONSTRAINT fk_item_tags_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,CONSTRAINT fk_item_tags_tag FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS api_logs (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,api_name VARCHAR(64) NOT NULL,request_url TEXT NOT NULL,request_hash CHAR(64) NOT NULL,response_status INT NULL,response_body MEDIUMTEXT NULL,cache_hit TINYINT(1) NOT NULL DEFAULT 0,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,INDEX idx_api_logs_created (created_at),INDEX idx_api_logs_name (api_name),INDEX idx_api_logs_hash (request_hash)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    }
}
