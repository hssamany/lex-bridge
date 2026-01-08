CREATE TABLE prices
(
    id BIGINT
    UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    article_id BIGINT UNSIGNED NOT NULL,

    net_amount DECIMAL
    (10,2) NOT NULL,
    gross_amount DECIMAL
    (10,2) NOT NULL,
    tax_rate_percentage DECIMAL
    (5,2) NOT NULL,

    currency CHAR
    (3) NOT NULL DEFAULT 'EUR',

    valid_from DATE NOT NULL DEFAULT
    (CURRENT_DATE),
    valid_until DATE NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON
    UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY
    (article_id) REFERENCES articles
    (id) ON
    DELETE CASCADE,

    INDEX idx_article_id (article_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
