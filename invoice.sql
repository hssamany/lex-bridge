-- Main invoices table (UPDATED - using UUID as primary key)
CREATE TABLE invoices (
    id CHAR(36) PRIMARY KEY, -- UUID
    contact_id INT NOT NULL, -- Foreign key to contacts table
    voucher_date DATE NOT NULL,
    archived BOOLEAN DEFAULT FALSE,
    title VARCHAR(255) DEFAULT 'Rechnung',
    introduction TEXT,
    remark TEXT,
    
    -- Total price
    currency VARCHAR(3) DEFAULT 'EUR',
    total_net_amount DECIMAL(10, 2),
    total_gross_amount DECIMAL(10, 2),
    
    -- Tax conditions
    tax_type ENUM('net', 'gross') DEFAULT 'net',
    
    -- Payment conditions
    payment_term_label VARCHAR (255),
    payment_term_duration INT,
    payment_discount_percentage DECIMAL (5, 2),
    payment_discount_range INT,
    
    -- Shipping conditions
    shipping_date DATE,
    shipping_type ENUM ('delivery', 'pickup', 'service') DEFAULT 'delivery',
    
    -- Enhanced status tracking
    status ENUM('draft','ready','transmitting','transmitted','transmission_error','paid','cancelled') DEFAULT 'draft',
    
    -- Lex API response fields
    lex_id VARCHAR (255) UNIQUE,
    lex_resource_uri TEXT,
    lex_version INT DEFAULT 0,
    lex_created_date TIMESTAMP NULL,
    lex_updated_date TIMESTAMP NULL,
    
    -- Error tracking
    last_error_message TEXT,
    last_error_code VARCHAR(50),
    transmission_attempts INT DEFAULT 0,
    last_transmission_attempt TIMESTAMP NULL,
    
    -- Local timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    transmitted_at TIMESTAMP NULL,
    
    -- FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE RESTRICT,
    INDEX idx_contact (contact_id),
    INDEX idx_status (status),
	INDEX idx_voucher_date (voucher_date),
    INDEX idx_lex_id (lex_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;