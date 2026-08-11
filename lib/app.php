<?php

declare(strict_types=1);

require_once __DIR__ . '/rakuten_api_client.php';
require_once __DIR__ . '/rakuten_sync_service.php';
require_once __DIR__ . '/site_settings.php';
require_once __DIR__ . '/api_credentials.php';
require_once __DIR__ . '/config.php';


function settings_get(): array
{
    $defaults = app_config()['rakuten'] ?? [];

    $envApiId = trim((string)(getenv('RAKUTEN_APPLICATION_ID') ?: ''));
    $envAccessKey = trim((string)(getenv('RAKUTEN_ACCESS_KEY') ?: ''));
    $envAffiliateId = trim((string)(getenv('RAKUTEN_AFFILIATE_ID') ?: ''));

    $itemCred = api_credential_get('items');
    $dbApiId = trim((string)($itemCred['api_id'] ?? ''));
    $dbAccessKey = trim((string)($itemCred['access_key'] ?? ''));
    $dbAffiliateId = trim((string)($itemCred['affiliate_id'] ?? ''));

    return [
        'api_id' => $dbApiId !== '' ? $dbApiId : ($envApiId !== '' ? $envApiId : ''),
        'access_key' => $dbAccessKey !== '' ? $dbAccessKey : $envAccessKey,
        'affiliate_id' => $dbAffiliateId !== '' ? $dbAffiliateId : ($envAffiliateId !== '' ? $envAffiliateId : ''),
        'keyword' => trim(site_setting_get('rakuten_search_keyword', (string)($defaults['keyword'] ?? 'アダルトグッズ'))),
        'item_sync_batch' => settings_allowed_item_sync_batch(settings_int('item_sync_batch', 30)),
        'item_sync_enabled' => settings_bool('item_sync_enabled', false),
        'item_sync_interval_minutes' => settings_int('item_sync_interval_minutes', 60),
        'last_item_sync_at' => site_setting_get('last_item_sync_at', ''),
        'item_sync_offset' => settings_int('item_sync_offset', 1),
        'item_sync_test_offset' => settings_int('item_sync_test_offset', 1),
    ];
}

function settings_int(string $key, int $default): int
{
    $value = site_setting_get($key, (string)$default);
    if (!preg_match('/^-?\d+$/', $value)) {
        return $default;
    }
    return (int)$value;
}

function settings_allowed_item_sync_batch(int $value): int
{
    $allowed = [1, 10, 20, 30, 60, 90, 120, 150, 300];
    if (!in_array($value, $allowed, true)) {
        return 30;
    }
    return $value;
}

function settings_bool(string $key, bool $default): bool
{
    return settings_int($key, $default ? 1 : 0) === 1;
}

function settings_save(string $apiId, string $accessKey, string $affiliateId, int $itemSyncBatch = 30): void
{
    $allowed = [1, 10, 20, 30, 60, 90, 120, 150, 300];
    if (!in_array($itemSyncBatch, $allowed, true)) {
        $itemSyncBatch = 30;
    }

    api_credential_set('items', $apiId, $accessKey, $affiliateId);
    $payload = [
        'item_sync_batch' => (string)$itemSyncBatch,
    ];

    site_setting_set_many($payload);
}

function rakuten_client_for_type(string $apiType): RakutenApiClient
{
    $cred = api_credential_get($apiType);
    $endpoint = (string)(app_config()['rakuten']['endpoint'] ?? 'https://openapi.rakuten.co.jp/ichibams/api/IchibaItem/Search/20260701');
    return new RakutenApiClient((string)($cred['api_id'] ?? ''), (string)($cred['access_key'] ?? ''), (string)($cred['affiliate_id'] ?? ''), $endpoint);
}

function rakuten_client_from_settings(): RakutenApiClient
{
    return rakuten_client_for_type('items');
}

function rakuten_sync_service(?string $apiType = null): RakutenSyncService
{
    return new RakutenSyncService($apiType === null ? rakuten_client_from_settings() : rakuten_client_for_type($apiType), db());
}
