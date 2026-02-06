-- Stores time-ranged price snapshots for each article.
CREATE TABLE
    IF NOT EXISTS `prices` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `article_id` BIGINT UNSIGNED NOT NULL,
        `net_amount` DECIMAL(10, 2) NOT NULL,
        `gross_amount` DECIMAL(10, 2) NOT NULL,
        `tax_rate_percentage` DECIMAL(5, 2) NOT NULL,
        `currency` CHAR(3) NOT NULL DEFAULT 'EUR',
        `valid_from` DATE NOT NULL,
        `valid_until` DATE NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT `fk_prices_article` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`) ON DELETE CASCADE,
        KEY `idx_article_id` (`article_id`)
    ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;