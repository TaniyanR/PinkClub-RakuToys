<?php

declare(strict_types=1);

require dirname(__DIR__) . '/lib/bootstrap.php';
require dirname(__DIR__) . '/lib/analytics.php';

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    http_response_code(204);
    exit;
}

try {
    record_page_view(
        db(),
        (string)($_POST['path'] ?? '/'),
        (string)($_POST['referrer'] ?? '')
    );
} catch (Throwable $e) {
    error_log('[analytics] ' . $e->getMessage());
}

http_response_code(204);
