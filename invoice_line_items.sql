-- Invoice line items table (UPDATED - using UUID)
CREATE TABLE invoice_line_items (

    id CHAR(36) PRIMARY KEY, -- UUID
    invoice_id CHAR(36) NOT NULL, -- UUID reference to invoices
    line_order INT NOT NULL,
    
    type ENUM('custom', 'text', 'material', 'service') NOT NULL,
    name VARCHAR (255) NOT NULL,
    description TEXT,
    quantity DECIMAL (10, 3) 
	DEFAULT 1,
    unit_name VARCHAR(50),
    
    currency VARCHAR (3) DEFAULT 'EUR',
    net_amount DECIMAL (10, 2),
    tax_rate_percentage DECIMAL (5, 2),
    discount_percentage DECIMAL (5, 2) DEFAULT 0,
    
    line_total_net DECIMAL (10, 2),
    line_total_gross DECIMAL (10, 2),
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (invoice_id) REFERENCES invoices (id) ON DELETE CASCADE,
    INDEX idx_invoice (invoice_id),
    INDEX idx_line_order (invoice_id, line_order)
	
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;