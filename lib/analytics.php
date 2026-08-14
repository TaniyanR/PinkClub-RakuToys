<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function analytics_is_automated_request(?string $userAgent = null): bool
{
    $userAgent ??= (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
    if ($userAgent === '' || preg_match('/bot|crawler|spider|slurp|bingpreview|headless|curl|wget|python|scrapy/i', $userAgent) === 1) {
        return true;
    }
    foreach (['HTTP_PURPOSE', 'HTTP_SEC_PURPOSE', 'HTTP_X_MOZ'] as $header) {
        if (preg_match('/\b(prefetch|prerender|preview)\b/i', (string)($_SERVER[$header] ?? '')) === 1) {
            return true;
        }
    }
    return false;
}

function visitor_hash(): string
{
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
    return hash('sha256', date('Y-m-d') . '|' . $ip . '|' . $ua . '|' . (string)app_config('app.name'));
}

function record_page_view(PDO $pdo, string $path, string $referrer): void
{
    if (PHP_SAPI === 'cli' || analytics_is_automated_request()) {
        return;
    }
    $stmt = $pdo->prepare('INSERT INTO page_views(path,visitor_hash,referrer,user_agent) VALUES(?,?,?,?)');
    $stmt->execute([
        mb_substr($path !== '' ? $path : '/', 0, 2048),
        visitor_hash(),
        mb_substr($referrer, 0, 2048),
        mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 1000),
    ]);
}

function render_analytics_beacon(): void
{
    ?>
<script>
(function () {
  if (window.__pcrtAnalyticsSent) return;
  window.__pcrtAnalyticsSent = true;
  var data = new FormData();
  data.append('path', location.pathname + location.search);
  data.append('referrer', document.referrer || '');
  if (navigator.sendBeacon && navigator.sendBeacon('analytics.php', data)) return;
  if (window.fetch) fetch('analytics.php', {method:'POST', body:data, credentials:'same-origin', keepalive:true}).catch(function(){});
}());
</script>
    <?php
}
