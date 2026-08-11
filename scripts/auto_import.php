<?php
declare(strict_types=1);

$projectRoot = realpath(__DIR__ . '/..') ?: dirname(__DIR__);
chdir($projectRoot);

require_once __DIR__ . '/../lib/scheduler.php';
require_once __DIR__ . '/../lib/app_features.php';
require_once __DIR__ . '/../lib/access_analytics.php';

function auto_import_config_diagnostics(): string
{
    $configPath = realpath(__DIR__ . '/../config.local.php') ?: (__DIR__ . '/../config.local.php');
    $exists = is_file($configPath) ? 'yes' : 'no';
    $readable = is_readable($configPath) ? 'yes' : 'no';
    $cwd = getcwd() ?: '';

    return sprintf('cwd=%s config.local.php=%s exists=%s readable=%s', $cwd, $configPath, $exists, $readable);
}

function main(): int
{
    try {
        $result = maybe_run_scheduled_jobs();
        rss_widget_bootstrap();
        rss_refresh_stale_sources(1000, 1800, 2);
        analytics_maybe_cleanup_old_logs(730, 2000, true);
        $status = (string)($result['status'] ?? 'unknown');
        $syncedCount = (int)($result['synced_count'] ?? 0);
        $message = trim((string)($result['message'] ?? ''));
        echo '[' . date('Y-m-d H:i:s') . "] maybe_run_scheduled_jobs() status={$status} synced={$syncedCount}";
        if ($message !== '') {
            echo " message={$message}";
        }
        echo "\n";
        return 0;
    } catch (Throwable $e) {
        $diagnostics = auto_import_config_diagnostics();
        error_log('[auto_import] ' . $e->getMessage() . ' [' . $diagnostics . ']');
        fwrite(STDERR, $e->getMessage() . ' [' . $diagnostics . ']' . "\n");
        return 1;
    }
}

if (PHP_SAPI === 'cli' && realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    exit(main());
}
