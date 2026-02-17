-- Make invoice_id nullable to allow line items without invoices
-- This is needed when creating line items from orders before invoicing
ALTER TABLE `invoice_line_items`
MODIFY COLUMN `invoice_id` CHAR(36) NULL;
