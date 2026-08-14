<?php

declare(strict_types=1);
require dirname(__DIR__) . '/lib/bootstrap.php';
require dirname(__DIR__) . '/lib/admin.php';
header('X-Robots-Tag: noindex, nofollow', true);
header('Cache-Control: private, no-store, max-age=0', true);
$id=max(0,(int)($_GET['id']??0));
$stmt=db()->prepare('SELECT id,item_url,affiliate_url FROM items WHERE id=? AND availability=1 LIMIT 1');
$stmt->execute([$id]);
$item=$stmt->fetch();
if(!$item){http_response_code(404);exit('商品が見つかりません。');}
$url=(string)($item['affiliate_url']?:$item['item_url']);
if(!preg_match('#^https://#i',$url)){http_response_code(400);exit('不正なリンクです。');}
if (!analytics_is_automated_request()) {
    try {
        $visitorHash = visitor_hash();
        $duplicate = db()->prepare(
            'SELECT 1 FROM affiliate_clicks WHERE item_id=? AND visitor_hash=? AND created_at>=CURDATE() AND created_at<CURDATE()+INTERVAL 1 DAY LIMIT 1'
        );
        $duplicate->execute([(int)$item['id'], $visitorHash]);
        if ($duplicate->fetchColumn() === false) {
            $log = db()->prepare('INSERT INTO affiliate_clicks(item_id,visitor_hash) VALUES(?,?)');
            $log->execute([(int)$item['id'], $visitorHash]);
        }
    } catch (Throwable $e) {
        error_log('[affiliate_click] ' . $e->getMessage());
    }
}
header('Location: '.$url, true, 302);
exit;
