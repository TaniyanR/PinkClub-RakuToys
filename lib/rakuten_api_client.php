<?php

declare(strict_types=1);

final class RakutenApiClient
{
    public function __construct(
        private readonly string $applicationId,
        private readonly string $accessKey,
        private readonly string $affiliateId,
        private readonly string $endpoint
    ) {
    }

    public function fetchItems(array $params = []): array
    {
        if (trim($this->applicationId) === '' || trim($this->accessKey) === '') {
            throw new RuntimeException('楽天アプリID / アクセスキーが未設定です。');
        }

        $query = array_filter(array_merge([
            'applicationId' => $this->applicationId,
            'affiliateId' => $this->affiliateId,
            'format' => 'json',
            'formatVersion' => 2,
            'keyword' => 'アダルトグッズ',
            'hits' => 30,
            'page' => 1,
            'availability' => 1,
            'imageFlag' => 1,
        ], $params), static fn (mixed $value): bool => $value !== null && $value !== '');

        $query['hits'] = min(30, max(1, (int)($query['hits'] ?? 30)));
        $query['page'] = min(100, max(1, (int)($query['page'] ?? 1)));
        $query['sort'] = $this->normalizeSort((string)($query['sort'] ?? 'standard'));

        $url = rtrim($this->endpoint, '?') . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        $safeQuery = $this->maskSensitiveParams($query);
        $safeUrl = rtrim($this->endpoint, '?') . '?' . http_build_query($safeQuery, '', '&', PHP_QUERY_RFC3986);
        $requestHash = hash('sha256', $url . "\n" . $this->accessKey);

        $cached = $this->fetchCachedResponse($requestHash);
        if ($cached !== null) {
            $this->insertApiLog('IchibaItemSearch', $safeUrl, $requestHash, 200, json_encode($cached, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}', true);
            return $cached;
        }

        if (!function_exists('curl_init')) {
            throw new RuntimeException('楽天APIとの通信にはPHP cURL拡張が必要です。');
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FAILONERROR => false,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'accessKey: ' . $this->accessKey,
            ],
        ]);

        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            $this->insertApiLog('IchibaItemSearch', $safeUrl, $requestHash, 0, json_encode(['error' => $error], JSON_UNESCAPED_UNICODE) ?: '{}', false);
            throw new RuntimeException('楽天API通信エラー: ' . $error);
        }

        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $this->insertApiLog('IchibaItemSearch', $safeUrl, $requestHash, $httpCode, $response, false);

        $decoded = json_decode($response, true);
        if ($httpCode >= 400) {
            $detail = is_array($decoded)
                ? trim((string)($decoded['error_description'] ?? $decoded['error'] ?? ''))
                : '';
            $guidance = match ($httpCode) {
                400 => 'リクエスト条件と認証情報を確認してください。',
                404 => 'APIエンドポイントまたは検索対象が見つかりません。',
                429 => 'アクセス制限中です。時間を空けて再実行してください。',
                500, 503 => '楽天APIで一時的な障害が発生しています。時間を空けて再実行してください。',
                default => '',
            };
            throw new RuntimeException('楽天API HTTP ' . $httpCode
                . ($detail !== '' ? ': ' . $detail : '')
                . ($guidance !== '' ? ' ' . $guidance : ''));
        }
        if (!is_array($decoded)) {
            throw new RuntimeException('楽天APIレスポンスをJSONとして読み取れませんでした。');
        }
        if (isset($decoded['error'])) {
            throw new RuntimeException('楽天APIエラー: ' . (string)($decoded['error_description'] ?? $decoded['error']));
        }

        return $decoded;
    }

    private function normalizeSort(string $sort): string
    {
        return match ($sort) {
            'rank', 'standard' => 'standard',
            'date', '-updateTimestamp' => '-updateTimestamp',
            'review', '-reviewCount' => '-reviewCount',
            '+itemPrice', '-itemPrice', '+affiliateRate', '-affiliateRate',
            '+reviewCount', '+reviewAverage', '-reviewAverage',
            '+updateTimestamp' => $sort,
            default => 'standard',
        };
    }

    private function maskSensitiveParams(array $query): array
    {
        foreach (['applicationId', 'affiliateId'] as $key) {
            if (array_key_exists($key, $query)) {
                $query[$key] = '***';
            }
        }
        return $query;
    }

    private function fetchCachedResponse(string $requestHash): ?array
    {
        if (!function_exists('db')) {
            return null;
        }

        try {
            $stmt = db()->prepare('SELECT response_body FROM api_logs WHERE request_hash = :request_hash AND response_status = 200 AND cache_hit = 0 AND created_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE) ORDER BY id DESC LIMIT 1');
            $stmt->execute([':request_hash' => $requestHash]);
            $body = $stmt->fetchColumn();
        } catch (Throwable) {
            return null;
        }

        if (!is_string($body) || $body === '') {
            return null;
        }
        $decoded = json_decode($body, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function insertApiLog(string $apiName, string $requestUrl, string $requestHash, int $status, string $responseBody, bool $cacheHit): void
    {
        if (!function_exists('db')) {
            return;
        }

        $body = mb_substr($responseBody, 0, 65535);
        try {
            $stmt = db()->prepare('INSERT INTO api_logs (api_name, endpoint, request_params, request_url, request_hash, response_status, status_code, response_body, cache_hit, is_success, message, created_at) VALUES (:api_name, :endpoint, :request_params, :request_url, :request_hash, :response_status, :status_code, :response_body, :cache_hit, :is_success, :message, NOW())');
            $stmt->execute([
                ':api_name' => $apiName,
                ':endpoint' => $apiName,
                ':request_params' => json_encode(['url' => $requestUrl], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ':request_url' => $requestUrl,
                ':request_hash' => $requestHash,
                ':response_status' => $status,
                ':status_code' => $status,
                ':response_body' => $body,
                ':cache_hit' => $cacheHit ? 1 : 0,
                ':is_success' => ($status >= 200 && $status < 400) ? 1 : 0,
                ':message' => $cacheHit ? 'cache' : (($status >= 200 && $status < 400) ? 'ok' : 'error'),
            ]);
            return;
        } catch (Throwable $e) {
            error_log('api_logs extended insert failed: ' . $e->getMessage());
        }

        try {
            $stmt = db()->prepare('INSERT INTO api_logs (api_name, request_url, request_hash, response_status, response_body, cache_hit, created_at) VALUES (:api_name, :request_url, :request_hash, :response_status, :response_body, :cache_hit, NOW())');
            $stmt->execute([
                ':api_name' => $apiName,
                ':request_url' => $requestUrl,
                ':request_hash' => $requestHash,
                ':response_status' => $status,
                ':response_body' => $body,
                ':cache_hit' => $cacheHit ? 1 : 0,
            ]);
        } catch (Throwable $e) {
            error_log('api_logs legacy insert failed: ' . $e->getMessage());
        }
    }
}
