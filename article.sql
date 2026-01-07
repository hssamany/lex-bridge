CREATE TABLE articles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Lexware reference
    lexware_article_id CHAR(36) NOT NULL UNIQUE COMMENT 'UUID from Lexware',

    -- Article identification
    article_number VARCHAR(50) NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,

    -- Pricing
    net_price DECIMAL(10,2) NOT NULL,
    gross_price DECIMAL(10,2) NOT NULL,
    tax_rate DECIMAL(5,2) NOT NULL COMMENT 'e.g. 19.00',

    -- Quantity & unit
    unit_name VARCHAR(50) NOT NULL DEFAULT 'piece',

    -- Status
    active BOOLEAN NOT NULL DEFAULT TRUE,

    -- Audit fields
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Indexes
    INDEX idx_article_number (article_number),
    INDEX idx_lexware_article_id (lexware_article_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
