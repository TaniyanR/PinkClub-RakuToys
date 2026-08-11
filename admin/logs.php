<?php

declare(strict_types=1);
require dirname(__DIR__) . '/lib/bootstrap.php';
require dirname(__DIR__) . '/lib/admin.php';
require_admin();
$logs=db()->query('SELECT * FROM import_logs ORDER BY id DESC LIMIT 200')->fetchAll();
?><!doctype html><html lang="ja"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>同期ログ</title><style>body{font-family:sans-serif;background:#f0f0f1;margin:0}main{max-width:1100px;margin:auto;padding:22px}table{width:100%;border-collapse:collapse;background:#fff}th,td{padding:9px;border:1px solid #dcdcde;text-align:left;font-size:.9rem}.success{color:#008a20}.error{color:#b32d2e}</style></head><body><main><p><a href="index.php">← 管理トップ</a></p><h1>同期ログ</h1><table><thead><tr><th>日時</th><th>キーワード</th><th>ページ</th><th>件数</th><th>状態</th><th>メッセージ</th></tr></thead><tbody><?php foreach($logs as $log):?><tr><td><?=h((string)$log['created_at'])?></td><td><?=h((string)$log['keyword'])?></td><td><?=number_format((int)$log['page_no'])?></td><td><?=number_format((int)$log['fetched_count'])?></td><td class="<?=h((string)$log['status'])?>"><?=h((string)$log['status'])?></td><td><?=h((string)($log['message']??''))?></td></tr><?php endforeach?></tbody></table></main></body></html>