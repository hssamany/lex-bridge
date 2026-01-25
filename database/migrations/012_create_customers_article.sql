-- Links each customer to a single default article without altering the existing customer schema.
CREATE TABLE
    IF NOT EXISTS `customers_article` (
        `customer_id` INT UNSIGNED NOT NULL,
        `article_id` INT UNSIGNED NOT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`customer_id`),
        UNIQUE KEY `uniq_article_id` (`article_id`),
        CONSTRAINT `fk_customers_article_customer` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT `fk_customers_article_article` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
    ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;