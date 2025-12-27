-- Invoice sync log table (UPDATED - using UUID)
CREATE TABLE invoice_sync_log (
    id CHAR(36) PRIMARY KEY, -- UUID
    invoice_id CHAR(36) NOT NULL, -- UUID reference to invoices
    action ENUM('create', 'update', 'delete') NOT NULL,
    status ENUM('success', 'error') NOT NULL,
    
    request_data JSON,
    response_data JSON,
    lex_id VARCHAR(255),
    lex_resource_uri TEXT,
    lex_version INT,
    
    error_message TEXT,
    error_code VARCHAR(50),
    
    synced_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
    INDEX idx_invoice (invoice_id),
    INDEX idx_status (status),
    INDEX idx_synced_at (synced_at),
    INDEX idx_lex_id (lex_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;