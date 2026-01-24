-- Ensures invoice line items store the originating customer number for grouping logic.
ALTER TABLE `invoice_line_items`
ADD COLUMN IF NOT EXISTS `customer_number` VARCHAR(64) NULL AFTER `invoice_id`;