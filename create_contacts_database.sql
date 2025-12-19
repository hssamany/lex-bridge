-- Create database for Lex Bridge application

-- Create contacts table
CREATE TABLE Customer
(
    id INT
    AUTO_INCREMENT PRIMARY KEY,
    lex_contact_id VARCHAR
    (255) NOT NULL UNIQUE,
    organization_id VARCHAR
    (255) NOT NULL,
    version INT NOT NULL DEFAULT 0,
    customer_number INT NOT NULL,
    company_name VARCHAR
    (255) NOT NULL,
    allow_tax_free_invoices BOOLEAN NOT NULL DEFAULT FALSE,
    archived BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON
    UPDATE CURRENT_TIMESTAMP,
    INDEX idx_lex_contact_id (lex_contact_id),
    INDEX idx_organization_id (organization_id),
    INDEX idx_customer_number (customer_number),
    INDEX idx_archived (archived)
    ) 