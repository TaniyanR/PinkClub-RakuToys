<?php

declare(strict_types=1);

function save_item(PDO $pdo, array $item): void
{
    $image = $item['mediumImageUrls'][0] ?? $item['smallImageUrls'][0] ?? null;
    if (is_array($image)) {
        $image = $image['imageUrl'] ?? null;
    }

    $sql = "INSERT INTO items
        (item_code,item_name,catchcopy,item_price,item_url,affiliate_url,image_url,shop_code,shop_name,shop_url,review_count,review_average,affiliate_rate,postage_flag,availability,genre_id,raw_json,last_seen_at)
        VALUES
        (:item_code,:item_name,:catchcopy,:item_price,:item_url,:affiliate_url,:image_url,:shop_code,:shop_name,:shop_url,:review_count,:review_average,:affiliate_rate,:postage_flag,:availability,:genre_id,:raw_json,NOW())
        ON DUPLICATE KEY UPDATE
        item_name=VALUES(item_name),catchcopy=VALUES(catchcopy),item_price=VALUES(item_price),item_url=VALUES(item_url),affiliate_url=VALUES(affiliate_url),image_url=VALUES(image_url),shop_code=VALUES(shop_code),shop_name=VALUES(shop_name),shop_url=VALUES(shop_url),review_count=VALUES(review_count),review_average=VALUES(review_average),affiliate_rate=VALUES(affiliate_rate),postage_flag=VALUES(postage_flag),availability=VALUES(availability),genre_id=VALUES(genre_id),raw_json=VALUES(raw_json),last_seen_at=NOW()";

    $pdo->prepare($sql)->execute([
        ':item_code' => (string)($item['itemCode'] ?? ''),
        ':item_name' => (string)($item['itemName'] ?? ''),
        ':catchcopy' => (string)($item['catchcopy'] ?? ''),
        ':item_price' => (int)($item['itemPrice'] ?? 0),
        ':item_url' => (string)($item['itemUrl'] ?? ''),
        ':affiliate_url' => (string)($item['affiliateUrl'] ?? $item['itemUrl'] ?? ''),
        ':image_url' => $image,
        ':shop_code' => (string)($item['shopCode'] ?? ''),
        ':shop_name' => (string)($item['shopName'] ?? ''),
        ':shop_url' => (string)($item['shopAffiliateUrl'] ?? $item['shopUrl'] ?? ''),
        ':review_count' => (int)($item['reviewCount'] ?? 0),
        ':review_average' => (float)($item['reviewAverage'] ?? 0),
        ':affiliate_rate' => (float)($item['affiliateRate'] ?? 0),
        ':postage_flag' => (int)($item['postageFlag'] ?? 0),
        ':availability' => (int)($item['availability'] ?? 1),
        ':genre_id' => isset($item['genreId']) ? (int)$item['genreId'] : null,
        ':raw_json' => json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);
}

function search_items(PDO $pdo, array $filters, int $limit = 30, int $offset = 0): array
{
    $where = ['availability = 1'];
    $params = [];
    if (($filters['q'] ?? '') !== '') {
        $where[] = '(item_name LIKE :q OR catchcopy LIKE :q)';
        $params[':q'] = '%' . $filters['q'] . '%';
    }
    if (($filters['min_price'] ?? '') !== '') { $where[] = 'item_price >= :min_price'; $params[':min_price'] = (int)$filters['min_price']; }
    if (($filters['max_price'] ?? '') !== '') { $where[] = 'item_price <= :max_price'; $params[':max_price'] = (int)$filters['max_price']; }
    if (($filters['min_review'] ?? '') !== '') { $where[] = 'review_average >= :min_review'; $params[':min_review'] = (float)$filters['min_review']; }
    if (!empty($filters['free_shipping'])) { $where[] = 'postage_flag = 0'; }

    $orders = [
        'review' => 'review_average DESC, review_count DESC',
        'reviews' => 'review_count DESC, review_average DESC',
        'price_asc' => 'item_price ASC',
        'price_desc' => 'item_price DESC',
        'affiliate' => 'affiliate_rate DESC',
        'new' => 'last_seen_at DESC',
    ];
    $order = $orders[$filters['sort'] ?? 'reviews'] ?? $orders['reviews'];
    $sql = 'SELECT * FROM items WHERE ' . implode(' AND ', $where) . ' ORDER BY ' . $order . ' LIMIT :limit OFFSET :offset';
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) { $stmt->bindValue($key, $value); }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}
