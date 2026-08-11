CREATE DATABASE IF NOT EXISTS pinkclub_rakutoys CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE pinkclub_rakutoys;

CREATE TABLE IF NOT EXISTS items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    item_code VARCHAR(255) NOT NULL,
    item_name TEXT NOT NULL,
    catchcopy TEXT NULL,
    item_price INT UNSIGNED NOT NULL DEFAULT 0,
    item_url TEXT NOT NULL,
    affiliate_url TEXT NULL,
    image_url TEXT NULL,
    shop_code VARCHAR(255) NULL,
    shop_name VARCHAR(255) NULL,
    shop_url TEXT NULL,
    review_count INT UNSIGNED NOT NULL DEFAULT 0,
    review_average DECIMAL(3,2) NOT NULL DEFAULT 0.00,
    affiliate_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    postage_flag TINYINT(1) NOT NULL DEFAULT 0,
    availability TINYINT(1) NOT NULL DEFAULT 1,
    genre_id BIGINT UNSIGNED NULL,
    raw_json JSON NULL,
    first_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_items_item_code (item_code),
    KEY idx_items_price (item_price),
    KEY idx_items_review (review_average, review_count),
    KEY idx_items_affiliate_rate (affiliate_rate),
    KEY idx_items_genre (genre_id),
    FULLTEXT KEY ft_items_name_catchcopy (item_name, catchcopy)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS import_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    keyword VARCHAR(255) NOT NULL,
    page_no INT UNSIGNED NOT NULL DEFAULT 1,
    fetched_count INT UNSIGNED NOT NULL DEFAULT 0,
    status ENUM('success','error') NOT NULL,
    message TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_import_logs_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
