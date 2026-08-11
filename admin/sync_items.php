<?php

declare(strict_types=1);
require_once __DIR__ . '/../public/_bootstrap.php';
auth_require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate_or_fail(post('_csrf'));
    try {
        $count = rakuten_sync_service()->syncItems([
            'hits' => min(30, max(1, (int)post('hits', 30))),
            'page' => min(100, max(1, (int)post('page', 1))),
            'keyword' => trim((string)post('keyword', 'アダルトグッズ')) ?: 'アダルトグッズ',
        ]);
        flash_set('success', "商品同期: {$count}件");
    } catch (Throwable $e) {
        flash_set('error', '商品同期失敗: ' . $e->getMessage());
    }
    app_redirect('admin/sync_items.php');
}

$title = 'Items';
$logs = db()->query("SELECT * FROM sync_logs WHERE sync_type IN ('item','items') ORDER BY id DESC LIMIT 30")->fetchAll();
require __DIR__ . '/includes/header.php';
?>
<section class="admin-card">
  <h1>Items</h1>
  <form method="post">
    <?= csrf_input() ?>
    <label>検索キーワード
      <input name="keyword" value="アダルトグッズ">
    </label>
    <label>取得ページ
      <input type="number" name="page" value="1" min="1" max="100">
    </label>
    <label>取得件数
      <input type="number" name="hits" value="30" min="1" max="30">
    </label>
    <button type="submit">同期</button>
  </form>
</section>

<section class="admin-card">
  <h2>同期履歴</h2>
  <table class="admin-table">
    <tr><th>時刻</th><th>結果</th><th>件数</th><th>メッセージ</th></tr>
    <?php foreach ($logs as $l): ?>
      <tr><td><?= e($l['created_at']) ?></td><td><?= $l['is_success'] ? 'OK' : 'NG' ?></td><td><?= e($l['synced_count']) ?></td><td><?= e($l['message']) ?></td></tr>
    <?php endforeach; ?>
  </table>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
