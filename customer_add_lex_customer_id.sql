-- Adds a nullable Lexware customer reference column.
ALTER TABLE `customer`
ADD COLUMN
IF NOT EXISTS `lex_customer_number` VARCHAR
(64) NULL AFTER `customer_number`,
ADD COLUMN
IF NOT EXISTS `lex_customer_id` VARCHAR
(64) NULL AFTER `customer_number`;
;
