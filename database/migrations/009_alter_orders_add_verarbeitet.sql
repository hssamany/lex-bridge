-- Flags order rows as processed after converting them into invoice line items.
ALTER TABLE `orders`
ADD COLUMN IF NOT EXISTS `verarbeitet` TINYINT (1) NOT NULL DEFAULT 0 AFTER `article_number`;