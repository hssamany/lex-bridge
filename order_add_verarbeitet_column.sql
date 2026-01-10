-- Marks orders as processed once invoice line items are generated
ALTER TABLE orders
    ADD COLUMN verarbeitet TINYINT
(1) NOT NULL DEFAULT 0 AFTER article_number;
