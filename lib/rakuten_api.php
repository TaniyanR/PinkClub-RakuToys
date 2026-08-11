<?php

declare(strict_types=1);

final class RakutenApiClient
{
    public function __construct(private array $config) {}

    public function search(string $keyword, int $page = 1): array
    {
        $params = [
            'applicationId' => (string)$this->config['application_id'],
            'accessKey' => (string)$this->config['access_key'],
            'affiliateId' => (string)$this->config['affiliate_id'],
            'keyword' => $keyword,
            'hits' => max(1, min(30, (int)($this->config['hits'] ?? 30))),
            'page' => max(1, min(100, $page)),
            'sort' => (string)($this->config['sort'] ?? '-reviewCount'),
            'availability' => 1,
            'imageFlag' => 1,
            'format' => 'json',
            'formatVersion' => 2,
        ];

        $url = rtrim((string)$this->config['endpoint'], '?') . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);
        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false || $status !== 200) {
            throw new RuntimeException('Rakuten API error: HTTP ' . $status . ($error ? ' / ' . $error : ''));
        }
        $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        if (isset($data['error'])) {
            throw new RuntimeException((string)($data['error_description'] ?? $data['error']));
        }
        return $data;
    }
}
