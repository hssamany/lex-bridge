-- Stored procedure to generate invoices for line items lacking an invoice reference
DELIMITER $
$

DROP PROCEDURE IF EXISTS sp_create_invoices_for_pending_line_items
$$
CREATE PROCEDURE sp_create_invoices_for_pending_line_items (
    IN p_voucher_date DATE
)
BEGIN
    DECLARE v_voucher_date DATE;
DECLARE v_pending_count INT DEFAULT 0;

DECLARE EXIT HANDLER FOR SQLEXCEPTION
BEGIN
    ROLLBACK;
    DROP TEMPORARY TABLE
    IF EXISTS tmp_skipped_line_items;
DROP TEMPORARY TABLE
IF EXISTS tmp_pending_line_items;
DROP TEMPORARY TABLE
IF EXISTS tmp_customer_invoices;
        RESIGNAL;
END;

SET v_voucher_date
= IFNULL
(p_voucher_date, CURDATE
());

IF p_voucher_date IS NOT NULL AND p_voucher_date < DATE '2000-01-01' THEN
        SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT
= 'voucher date must be on or after 2000-01-01';
END
IF;

    START TRANSACTION;

DROP TEMPORARY TABLE
IF EXISTS tmp_skipped_line_items;
CREATE TEMPORARY TABLE tmp_skipped_line_items
(
        line_item_id CHAR
(36) NOT NULL PRIMARY KEY
    ) ENGINE = MEMORY;

INSERT IGNORE
INTO tmp_skipped_line_items
(line_item_id)
SELECT li.id
FROM invoice_line_items li
    LEFT JOIN customer c ON c.customer_number = li.customer_number
WHERE (li.invoice_id IS NULL OR li.invoice_id = '')
    AND c.id IS NULL;

DROP TEMPORARY TABLE
IF EXISTS tmp_pending_line_items;
CREATE TEMPORARY TABLE tmp_pending_line_items
(
        line_item_id CHAR
(36) NOT NULL PRIMARY KEY,
        customer_id INT NOT NULL,
        customer_number VARCHAR
(64) NULL,
        currency CHAR
(3) NULL,
        computed_line_total_net DECIMAL
(18,2) NULL,
        computed_line_total_gross DECIMAL
(18,2) NULL,
        existing_line_order INT NULL,
        generated_line_order INT NOT NULL
    ) ENGINE = MEMORY;

SET @prev_customer := NULL;
SET @row_number := 0;

INSERT INTO tmp_pending_line_items
    (
    line_item_id,
    customer_id,
    customer_number,
    currency,
    computed_line_total_net,
    computed_line_total_gross,
    existing_line_order,
    generated_line_order
    )
SELECT
    src.line_item_id,
    src.customer_id,
    src.customer_number,
    src.currency,
    src.computed_line_total_net,
    src.computed_line_total_gross,
    src.existing_line_order,
    src.generated_line_order
FROM (
        SELECT
        li.id AS line_item_id,
            c.id AS customer_id,
            c.customer_number,
            NULLIF(li.currency, '') AS currency,
            CASE
                WHEN li.line_total_net IS NOT NULL THEN li.line_total_net
                WHEN li.quantity IS NOT NULL AND li.net_amount IS NOT NULL THEN ROUND(li.quantity * li.net_amount, 2)
                ELSE NULL
            END AS computed_line_total_net,
            CASE
                WHEN li.line_total_gross IS NOT NULL THEN li.line_total_gross
                WHEN li.quantity IS NOT NULL AND li.gross_amount IS NOT NULL THEN ROUND(li.quantity * li.gross_amount, 2)
                ELSE NULL
            END AS computed_line_total_gross,
            li.line_order AS existing_line_order,
            (@row_number := IF(@prev_customer = c.id, @row_number + 1, 1)) AS generated_line_order,
    @prev_customer := c.id AS prev_customer_marker
        FROM invoice_line_items li
    INNER JOIN customer c ON c.customer_number = li.customer_number
WHERE li.invoice_id IS NULL OR li.invoice_id = ''
ORDER BY c.id, li.created_at, li.id
) AS src;

SELECT COUNT(*)
INTO v_pending_count
FROM tmp_pending_line_items;

IF v_pending_count = 0 THEN
COMMIT;

SELECT
    CAST(NULL AS CHAR(36)) AS invoice_id,
    CAST(NULL AS SIGNED) AS customer_id,
    CAST(NULL AS CHAR(64)) AS customer_number,
    CAST(NULL AS CHAR(3)) AS currency,
    0 AS line_item_count,
    0.00 AS total_net_amount,
    0.00 AS total_gross_amount
WHERE 1 = 0;

ELSE
DROP TEMPORARY TABLE
IF EXISTS tmp_customer_invoices;
CREATE TEMPORARY TABLE tmp_customer_invoices
(
            customer_id INT NOT NULL,
            customer_number VARCHAR
(64) NULL,
            currency CHAR
(3) NOT NULL,
            total_net_amount DECIMAL
(18,2) NOT NULL,
            total_gross_amount DECIMAL
(18,2) NOT NULL,
            line_item_count INT NOT NULL,
            invoice_id CHAR
(36) NOT NULL PRIMARY KEY
        ) ENGINE = MEMORY;

INSERT INTO tmp_customer_invoices
    (
    customer_id,
    customer_number,
    currency,
    total_net_amount,
    total_gross_amount,
    line_item_count,
    invoice_id
    )
SELECT
    customer_id,
    MAX(customer_number) AS customer_number,
    COALESCE(MAX(NULLIF(currency, '')), 'EUR') AS currency,
    COALESCE(SUM(computed_line_total_net), 0) AS total_net_amount,
    COALESCE(SUM(computed_line_total_gross), 0) AS total_gross_amount,
    COUNT(*) AS line_item_count,
    UUID() AS invoice_id
FROM tmp_pending_line_items
GROUP BY customer_id;

DELETE FROM tmp_customer_invoices WHERE line_item_count = 0;

IF NOT EXISTS (SELECT 1
FROM tmp_customer_invoices) THEN
COMMIT;

SELECT
    CAST(NULL AS CHAR(36)) AS invoice_id,
    CAST(NULL AS SIGNED) AS customer_id,
    CAST(NULL AS CHAR(64)) AS customer_number,
    CAST(NULL AS CHAR(3)) AS currency,
    0 AS line_item_count,
    0.00 AS total_net_amount,
    0.00 AS total_gross_amount
WHERE 1 = 0;
ELSE
INSERT INTO invoices
    (
    id,
    contact_id,
    voucher_date,
    currency,
    total_net_amount,
    total_gross_amount,
    tax_type,
    status,
    created_at,
    updated_at
    )
SELECT
    invoice_id,
    customer_id,
    v_voucher_date,
    currency,
    total_net_amount,
    total_gross_amount,
    'net',
    'draft',
    NOW(),
    NOW()
FROM tmp_customer_invoices;

UPDATE invoice_line_items li
            INNER JOIN tmp_pending_line_items t
ON t.line_item_id = li.id
            INNER JOIN tmp_customer_invoices ci ON ci.customer_id = t.customer_id
SET li
.invoice_id = ci.invoice_id,
                li.line_order = CASE
                    WHEN li.line_order IS NULL OR li.line_order <= 0 THEN t.generated_line_order
                    ELSE li.line_order
END,
                li.line_total_net = COALESCE
(t.computed_line_total_net, li.line_total_net),
                li.line_total_gross = COALESCE
(t.computed_line_total_gross, li.line_total_gross),
                li.updated_at = NOW
()
            WHERE li.invoice_id IS NULL OR li.invoice_id = '';

INSERT IGNORE
INTO tmp_skipped_line_items
(line_item_id)
SELECT t.line_item_id
FROM tmp_pending_line_items t
    INNER JOIN invoice_line_items li ON li.id = t.line_item_id
WHERE li.invoice_id IS NULL OR li.invoice_id = '';

UPDATE tmp_customer_invoices ci
SET ci
.line_item_count =
(
                    SELECT COUNT(*)
FROM invoice_line_items li
WHERE li.invoice_id = ci.invoice_id
                )
,
                ci.total_net_amount =
(
                    SELECT COALESCE(SUM(li.line_total_net), 0)
FROM invoice_line_items li
WHERE li.invoice_id = ci.invoice_id
                )
,
                ci.total_gross_amount =
(
                    SELECT COALESCE(SUM(li.line_total_gross), 0)
FROM invoice_line_items li
WHERE li.invoice_id = ci.invoice_id
                );

DELETE i
            FROM invoices i
    INNER JOIN tmp_customer_invoices ci ON ci.invoice_id = i.id
            WHERE ci.line_item_count = 0;

DELETE FROM tmp_customer_invoices WHERE line_item_count = 0;

UPDATE invoices i
            INNER JOIN tmp_customer_invoices ci
ON ci.invoice_id = i.id
SET i
.total_net_amount = ci.total_net_amount,
                i.total_gross_amount = ci.total_gross_amount,
                i.updated_at = NOW
();

COMMIT;

SELECT
    ci.invoice_id,
    ci.customer_id,
    ci.customer_number,
    ci.currency,
    ci.line_item_count,
    ci.total_net_amount,
    ci.total_gross_amount
FROM tmp_customer_invoices ci;
END
IF;

    END
IF;

    SELECT line_item_id
FROM tmp_skipped_line_items;

DROP TEMPORARY TABLE
IF EXISTS tmp_skipped_line_items;
DROP TEMPORARY TABLE
IF EXISTS tmp_pending_line_items;
DROP TEMPORARY TABLE
IF EXISTS tmp_customer_invoices;
END $$

DELIMITER ;
