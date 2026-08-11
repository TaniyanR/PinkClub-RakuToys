<?php

declare(strict_types=1);

require_once __DIR__ . '/../public/_bootstrap.php';
auth_require_admin();

$title = 'Settings';
$settings = settings_get();
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate_or_fail(post('_csrf'));
    $action = (string) post('action');
    $apiId = trim((string) post('api_id', ''));
    $accessKey = trim((string) post('access_key', ''));
    $affiliateId = trim((string) post('affiliate_id', ''));
    settings_save($apiId, $accessKey, $affiliateId);

    try {
        $client = rakuten_client_from_settings();
        if ($action === 'test') {
            $client->fetchItems(['hits' => 1, 'page' => 1, 'keyword' => (string)($settings['keyword'] ?? 'アダルトグッズ')]);
            $result = '接続テスト成功（楽天商品検索API疎通OK）';
        } else {
            $result = '設定を保存しました。';
        }
    } catch (Throwable $e) {
        $result = 'エラー: ' . $e->getMessage();
    }
    $settings = settings_get();
}

require __DIR__ . '/includes/header.php';
?>
<section class="admin-card">
  <h1>Settings</h1>
  <?php if ($result): ?><div class="admin-notice admin-notice--success"><p><?= e($result) ?></p></div><?php endif; ?>
  <form method="post">
    <?= csrf_input() ?>
    <label>楽天アプリID
      <input name="api_id" value="<?= e($settings['api_id'] ?? '') ?>">
    </label>
    <label>アクセスキー
      <input type="password" name="access_key" value="<?= e($settings['access_key'] ?? '') ?>" autocomplete="new-password">
    </label>
    <label>楽天アフィリエイトID
      <input name="affiliate_id" value="<?= e($settings['affiliate_id'] ?? '') ?>">
    </label>
    <div class="admin-actions">
      <button name="action" value="save" type="submit">保存</button>
      <button class="button-secondary" name="action" value="test" type="submit">接続テスト</button>
    </div>
  </form>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
