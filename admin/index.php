<?php

declare(strict_types=1);
require dirname(__DIR__) . '/lib/bootstrap.php';
require dirname(__DIR__) . '/lib/admin.php';
require_admin();
$pdo=db();
$items=(int)$pdo->query('SELECT COUNT(*) FROM items')->fetchColumn();
$todayPv=(int)$pdo->query("SELECT COUNT(*) FROM page_views WHERE created_at>=CURDATE()")->fetchColumn();
$todayUu=(int)$pdo->query("SELECT COUNT(DISTINCT visitor_hash) FROM page_views WHERE created_at>=CURDATE()")->fetchColumn();
$todayClicks=(int)$pdo->query("SELECT COUNT(*) FROM affiliate_clicks WHERE created_at>=CURDATE()")->fetchColumn();
$lastImport=$pdo->query('SELECT * FROM import_logs ORDER BY id DESC LIMIT 1')->fetch();
?><!doctype html><html lang="ja"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>管理画面</title><style>body{font-family:sans-serif;margin:0;background:#f0f0f1;color:#1d2327}header{background:#1d2327;color:#fff;padding:14px 20px}main{max-width:1100px;margin:auto;padding:22px}.nav a{margin-right:15px}.cards{display:grid;grid-template-columns:repeat(4,1fr);gap:14px}.card{background:#fff;padding:18px;border:1px solid #dcdcde;border-radius:6px}.num{font-size:2rem;font-weight:700}@media(max-width:700px){.cards{grid-template-columns:1fr 1fr}}</style></head><body><header><strong>PinkClub-RakuToys 管理</strong></header><main><p class="nav"><a href="index.php">ダッシュボード</a><a href="settings.php">設定</a><a href="logs.php">同期ログ</a><a href="../public/" target="_blank">サイト表示</a></p><div class="cards"><div class="card">商品数<div class="num"><?=number_format($items)?></div></div><div class="card">本日PV<div class="num"><?=number_format($todayPv)?></div></div><div class="card">本日UU<div class="num"><?=number_format($todayUu)?></div></div><div class="card">楽天クリック<div class="num"><?=number_format($todayClicks)?></div></div></div><div class="card" style="margin-top:14px"><h2>最終同期</h2><?php if($lastImport):?><p><?=h((string)$lastImport['created_at'])?> / <?=h((string)$lastImport['keyword'])?> / <?=h((string)$lastImport['status'])?> / <?=number_format((int)$lastImport['fetched_count'])?>件</p><?php else:?><p>まだ同期されていません。</p><?php endif?></div></main></body></html>