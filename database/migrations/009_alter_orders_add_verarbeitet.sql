-- Flags order rows as processed after converting them into invoice line items.
ALTER TABLE `orders`
ADD COLUMN `verarbeitet` TINYINT (1) NOT NULL DEFAULT 0 AFTER `GeaendertAm`;