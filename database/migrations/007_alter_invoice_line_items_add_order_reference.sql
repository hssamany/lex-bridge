ALTER TABLE `invoice_line_items`
ADD COLUMN IF NOT EXISTS `customer_number` VARCHAR(64) NULL AFTER `invoice_id`,
ADD COLUMN IF NOT EXISTS `order_id` INT UNSIGNED NULL AFTER `customer_number`,
ADD COLUMN IF NOT EXISTS `order_delivery_date` DATE NULL AFTER `order_id`,
ADD CONSTRAINT `fk_invoice_line_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`Id`) ON DELETE SET NULL,
ADD KEY `idx_order_reference` (`order_id`, `order_delivery_date`),
ADD UNIQUE KEY `uniq_order_day` (`order_id`, `order_delivery_date`);