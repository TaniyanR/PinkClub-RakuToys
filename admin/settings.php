<?php

declare(strict_types=1);
require dirname(__DIR__) . '/lib/bootstrap.php';
require dirname(__DIR__) . '/lib/admin.php';
require_admin();
$pdo=db();
$message='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    foreach(['site_name','site_description','rakuten_application_id','rakuten_access_key','rakuten_affiliate_id','rakuten_keywords','rakuten_hits','rakuten_pages','rakuten_sort'] as $key){
        save_setting($pdo,$key,trim((string)($_POST[$key]??'')));
    }
    $message='保存しました。';
}
$defaults=[
'site_name'=>(string)app_config('app.name','PinkClub-RakuToys'),
'site_description'=>'楽天市場のアダルトグッズを価格・レビューなどで比較して探せるサイトです。',
'rakuten_application_id'=>(string)app_config('rakuten.application_id',''),
'rakuten_access_key'=>(string)app_config('rakuten.access_key',''),
'rakuten_affiliate_id'=>(string)app_config('rakuten.affiliate_id',''),
'rakuten_keywords'=>implode("\n",(array)app_config('rakuten.keywords',[])),
'rakuten_hits'=>(string)app_config('rakuten.hits',30),
'rakuten_pages'=>(string)app_config('rakuten.pages_per_keyword',3),
'rakuten_sort'=>(string)app_config('rakuten.sort','-reviewCount')];
foreach($defaults as $k=>$v){$defaults[$k]=setting($pdo,$k,$v);}
?><!doctype html><html lang="ja"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>設定</title><style>body{font-family:sans-serif;background:#f0f0f1;margin:0}main{max-width:900px;margin:auto;padding:22px}.box{background:#fff;padding:22px;border:1px solid #dcdcde}.row{margin:14px 0}label{font-weight:700;display:block;margin-bottom:5px}input,textarea,select{width:100%;box-sizing:border-box;padding:10px}button{background:#2271b1;color:#fff;border:0;padding:11px 18px;border-radius:4px}</style></head><body><main><p><a href="index.php">← 管理トップ</a></p><div class="box"><h1>サイト・楽天API設定</h1><?php if($message):?><p><?=h($message)?></p><?php endif?><form method="post"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><?php foreach(['site_name'=>'サイト名','site_description'=>'サイト説明','rakuten_application_id'=>'Application ID','rakuten_access_key'=>'Access Key','rakuten_affiliate_id'=>'Affiliate ID'] as $k=>$label):?><div class="row"><label><?=h($label)?></label><input name="<?=h($k)?>" value="<?=h($defaults[$k])?>"></div><?php endforeach?><div class="row"><label>取得キーワード（1行1件）</label><textarea rows="8" name="rakuten_keywords"><?=h($defaults['rakuten_keywords'])?></textarea></div><div class="row"><label>1回の取得件数（1〜30）</label><input type="number" min="1" max="30" name="rakuten_hits" value="<?=h($defaults['rakuten_hits'])?>"></div><div class="row"><label>キーワードごとのページ数（1〜100）</label><input type="number" min="1" max="100" name="rakuten_pages" value="<?=h($defaults['rakuten_pages'])?>"></div><div class="row"><label>楽天API並び順</label><input name="rakuten_sort" value="<?=h($defaults['rakuten_sort'])?>"></div><button>設定を保存</button></form></div></main></body></html>