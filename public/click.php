<?php

declare(strict_types=1);
require dirname(__DIR__) . '/lib/bootstrap.php';
require dirname(__DIR__) . '/lib/admin.php';
$id=max(0,(int)($_GET['id']??0));
$stmt=db()->prepare('SELECT id,item_url,affiliate_url FROM items WHERE id=? AND availability=1 LIMIT 1');
$stmt->execute([$id]);
$item=$stmt->fetch();
if(!$item){http_response_code(404);exit('商品が見つかりません。');}
$url=(string)($item['affiliate_url']?:$item['item_url']);
if(!preg_match('#^https://#i',$url)){http_response_code(400);exit('不正なリンクです。');}
try{
    $log=db()->prepare('INSERT INTO affiliate_clicks(item_id,visitor_hash) VALUES(?,?)');
    $log->execute([(int)$item['id'],visitor_hash()]);
}catch(Throwable $e){}
header('Location: '.$url, true, 302);
exit;
