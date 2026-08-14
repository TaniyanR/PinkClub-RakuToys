<?php

declare(strict_types=1);

require dirname(__DIR__) . '/lib/bootstrap.php';
require dirname(__DIR__) . '/lib/analytics.php';
require dirname(__DIR__) . '/lib/repository.php';

$filters = [
    'q' => trim((string)($_GET['q'] ?? '')),
    'min_price' => (string)($_GET['min_price'] ?? ''),
    'max_price' => (string)($_GET['max_price'] ?? ''),
    'min_review' => (string)($_GET['min_review'] ?? ''),
    'free_shipping' => isset($_GET['free_shipping']) ? 1 : 0,
    'sort' => (string)($_GET['sort'] ?? 'reviews'),
];
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 30;
$items = search_items(db(), $filters, $limit, ($page - 1) * $limit);
$appName = (string)app_config('app.name', 'PinkClub-RakuToys');
?><!doctype html>
<html lang="ja"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h($appName) ?>｜アダルトグッズ比較・検索</title>
<meta name="description" content="楽天市場のアダルトグッズを価格・レビュー・送料無料などで比較して探せる検索サイトです。">
<style>
body{margin:0;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:#f7f7f8;color:#222}header{background:#fff;border-bottom:1px solid #ddd;padding:18px}main{max-width:1180px;margin:auto;padding:20px}.search{background:#fff;padding:16px;border-radius:10px;display:grid;grid-template-columns:2fr repeat(3,1fr);gap:10px;margin-bottom:20px}.search input,.search select,.search button{padding:11px;border:1px solid #ccc;border-radius:7px}.search button{background:#222;color:#fff}.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:16px}.card{background:#fff;border-radius:10px;padding:12px}.card img{width:100%;aspect-ratio:1/1;object-fit:contain}.price{font-size:1.25rem;font-weight:700}.rating{font-size:.9rem}.badges{display:flex;gap:6px;flex-wrap:wrap}.badge{font-size:.75rem;background:#eee;border-radius:999px;padding:4px 7px}.card a{color:inherit;text-decoration:none}.pagination{margin:24px 0;display:flex;gap:10px}.pagination a{background:#fff;padding:9px 13px;border-radius:7px;text-decoration:none;color:#222}@media(max-width:700px){.search{grid-template-columns:1fr}.grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
</style></head><body>
<header><strong><?= h($appName) ?></strong> <span>楽天アダルトグッズ比較・検索</span></header>
<main>
<form class="search" method="get">
<input name="q" value="<?= h($filters['q']) ?>" placeholder="商品名・キーワード">
<input type="number" name="min_price" value="<?= h($filters['min_price']) ?>" placeholder="最低価格">
<input type="number" name="max_price" value="<?= h($filters['max_price']) ?>" placeholder="最高価格">
<select name="min_review"><option value="">レビュー指定なし</option><?php foreach(['3'=>'3.0以上','3.5'=>'3.5以上','4'=>'4.0以上','4.5'=>'4.5以上'] as $v=>$label): ?><option value="<?=h($v)?>" <?= $filters['min_review']===$v?'selected':'' ?>><?=h($label)?></option><?php endforeach ?></select>
<select name="sort"><option value="reviews" <?= $filters['sort']==='reviews'?'selected':'' ?>>レビュー件数順</option><option value="review" <?= $filters['sort']==='review'?'selected':'' ?>>評価順</option><option value="price_asc" <?= $filters['sort']==='price_asc'?'selected':'' ?>>価格が安い順</option><option value="price_desc" <?= $filters['sort']==='price_desc'?'selected':'' ?>>価格が高い順</option><option value="affiliate" <?= $filters['sort']==='affiliate'?'selected':'' ?>>料率順</option><option value="new" <?= $filters['sort']==='new'?'selected':'' ?>>新着順</option></select>
<label><input type="checkbox" name="free_shipping" value="1" <?= $filters['free_shipping']?'checked':'' ?>> 送料無料</label>
<button>検索する</button>
</form>
<div class="grid">
<?php foreach($items as $item): ?>
<article class="card"><a href="item.php?id=<?= (int)$item['id'] ?>">
<?php if($item['image_url']): ?><img loading="lazy" src="<?= h((string)$item['image_url']) ?>" alt="<?= h((string)$item['item_name']) ?>"><?php endif ?>
<h2 style="font-size:1rem"><?= h((string)$item['item_name']) ?></h2><div class="price">¥<?= number_format((int)$item['item_price']) ?></div>
<div class="rating">★ <?= number_format((float)$item['review_average'],2) ?>（<?= number_format((int)$item['review_count']) ?>件）</div>
<div class="badges"><?php if((int)$item['postage_flag']===0): ?><span class="badge">送料無料</span><?php endif ?><span class="badge">料率 <?= h((string)$item['affiliate_rate']) ?>%</span></div>
</a></article>
<?php endforeach ?>
</div>
<div class="pagination"><?php if($page>1): ?><a href="?<?= h(http_build_query(array_merge($_GET,['page'=>$page-1]))) ?>">← 前へ</a><?php endif ?><?php if(count($items)===$limit): ?><a href="?<?= h(http_build_query(array_merge($_GET,['page'=>$page+1]))) ?>">次へ →</a><?php endif ?></div>
</main><?php render_analytics_beacon(); ?></body></html>
