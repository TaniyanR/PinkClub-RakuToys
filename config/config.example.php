<?php

declare(strict_types=1);

return [
    'app' => [
        'name' => 'PinkClub-RakuToys',
        'base_url' => 'http://localhost/PinkClub-RakuToys/public',
        'timezone' => 'Asia/Tokyo',
        'debug' => false,
    ],
    'db' => [
        'dsn' => 'mysql:host=127.0.0.1;port=3306;dbname=pinkclub_rakutoys;charset=utf8mb4',
        'user' => 'root',
        'password' => '',
    ],
    'rakuten' => [
        'application_id' => '',
        'access_key' => '',
        'affiliate_id' => '',
        'endpoint' => 'https://openapi.rakuten.co.jp/ichibams/api/IchibaItem/Search/20260701',
        'keywords' => [
            'アダルトグッズ',
            'ローター',
            'バイブ',
            '吸引',
            'ラブグッズ',
        ],
        'hits' => 30,
        'pages_per_keyword' => 3,
        'sort' => '-reviewCount',
    ],
];
