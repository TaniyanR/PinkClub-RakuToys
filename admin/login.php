<?php

declare(strict_types=1);
require dirname(__DIR__) . '/lib/bootstrap.php';
require dirname(__DIR__) . '/lib/admin.php';
start_secure_session();
if (admin_logged_in()) { header('Location: index.php'); exit; }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (login_admin(db(), trim((string)($_POST['username'] ?? '')), (string)($_POST['password'] ?? ''))) {
        header('Location: index.php'); exit;
    }
    $error = 'ユーザー名またはパスワードが違います。';
}
?><!doctype html><html lang="ja"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>管理ログイン</title><style>body{font-family:sans-serif;background:#f0f0f1;margin:0}.box{max-width:360px;margin:10vh auto;background:#fff;padding:28px;border:1px solid #dcdcde;border-radius:8px}input,button{box-sizing:border-box;width:100%;padding:11px;margin:7px 0}button{background:#2271b1;color:#fff;border:0;border-radius:4px}.error{color:#b32d2e}</style></head><body><main class="box"><h1>PinkClub-RakuToys</h1><?php if($error):?><p class="error"><?=h($error)?></p><?php endif?><form method="post"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><label>ユーザー名<input name="username" autocomplete="username" required></label><label>パスワード<input type="password" name="password" autocomplete="current-password" required></label><button>ログイン</button></form></main></body></html>