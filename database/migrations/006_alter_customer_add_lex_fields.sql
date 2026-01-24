-- Adds Lexware reference columns to the customer table for sync tracking.
ALTER TABLE `customer`
ADD COLUMN IF NOT EXISTS `lex_customer_number` VARCHAR(64) NULL AFTER `customer_number`,
ADD COLUMN IF NOT EXISTS `lex_customer_id` VARCHAR(64) NULL AFTER `lex_customer_number`,
ADD COLUMN IF NOT EXISTS `lex_contact_id` VARCHAR(64) NULL AFTER `lex_customer_id`;