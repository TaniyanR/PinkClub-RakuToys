<?php

declare(strict_types=1);

require dirname(__DIR__) . '/lib/bootstrap.php';
$id = max(0, (int)($_GET['id'] ?? 0));
$stmt = db()->prepare('SELECT * FROM items WHERE id = ? AND availability = 1 LIMIT 1');
$stmt->execute([$id]);
$item = $stmt->fetch();
if (!$item) { http_response_code(404); exit('商品が見つかりません。'); }
$title = (string)$item['item_name'];
$affiliateUrl = (string)($item['affiliate_url'] ?: $item['item_url']);
?><!doctype html><html lang="ja"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=h($title)?>｜<?=h((string)app_config('app.name'))?></title><meta name="description" content="<?=h(mb_substr((string)($item['catchcopy'] ?: $title),0,120))?>"><style>body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;margin:0;background:#f7f7f8;color:#222}main{max-width:980px;margin:auto;padding:24px}.box{background:#fff;border-radius:12px;padding:20px}.product{display:grid;grid-template-columns:minmax(260px,42%) 1fr;gap:28px}.product img{width:100%;object-fit:contain}.price{font-size:1.8rem;font-weight:700}.cta{display:inline-block;background:#bf0000;color:#fff;text-decoration:none;padding:14px 22px;border-radius:8px;font-weight:700}.meta{line-height:1.9;color:#555}@media(max-width:700px){.product{grid-template-columns:1fr}}</style></head><body><main><p><a href="./">← 商品一覧へ</a></p><article class="box product"><div><?php if($item['image_url']): ?><img src="<?=h((string)$item['image_url'])?>" alt="<?=h($title)?>"><?php endif ?></div><div><h1><?=h($title)?></h1><?php if($item['catchcopy']): ?><p><?=h((string)$item['catchcopy'])?></p><?php endif ?><p class="price">¥<?=number_format((int)$item['item_price'])?></p><div class="meta">レビュー ★ <?=number_format((float)$item['review_average'],2)?>（<?=number_format((int)$item['review_count'])?>件）<br>ショップ：<?=h((string)$item['shop_name'])?><br><?php if((int)$item['postage_flag']===0): ?>送料無料<br><?php endif ?>アフィリエイト料率：<?=h((string)$item['affiliate_rate'])?>%</div><p><a class="cta" href="<?=h($affiliateUrl)?>" rel="nofollow sponsored noopener" target="_blank">楽天市場で商品を見る</a></p><small>価格・在庫・送料などは楽天市場の商品ページで最新情報をご確認ください。</small></div></article></main></body></html>
