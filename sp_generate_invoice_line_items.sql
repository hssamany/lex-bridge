-- Stored procedure to expand weekly Orders into invoice_line_items payloads
DELIMITER $$

DROP PROCEDURE IF EXISTS sp_generate_invoice_line_items $$
CREATE PROCEDURE sp_generate_invoice_line_items (
    IN p_delivery_from DATE,
    IN p_delivery_to DATE,
    IN p_customer_id INT
)
BEGIN
    DECLARE v_delivery_from DATE;
    DECLARE v_delivery_to DATE;

    IF p_delivery_from IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'liefer_datum_von is required';
    END IF;

    SET v_delivery_from = p_delivery_from;
    SET v_delivery_to = IFNULL(p_delivery_to, CURDATE());

    DROP TEMPORARY TABLE IF EXISTS tmp_order_lines;

    CREATE TEMPORARY TABLE tmp_order_lines (
        order_id INT NOT NULL,
        customer_id INT NOT NULL,
        article_id INT NULL,
        article_number VARCHAR(50) NULL,
        delivery_date DATE NOT NULL,
        quantity DECIMAL(15,4) NOT NULL,
        PRIMARY KEY (order_id, delivery_date)
    ) ENGINE = MEMORY;

    INSERT INTO tmp_order_lines (order_id, customer_id, article_id, article_number, delivery_date, quantity)
    SELECT
        o.Id,
        o.Kunde,
        o.article_id,
        o.article_number,
        DATE_ADD(
            STR_TO_DATE(CONCAT(o.Jahr, ' ', LPAD(o.KW, 2, '0'), ' Monday'), '%x %v %W'),
            INTERVAL d.offset_day DAY
        ) AS delivery_date,
        CASE d.day_code
            WHEN 'Mo' THEN o.Mo
            WHEN 'Di' THEN o.Di
            WHEN 'Mi' THEN o.Mi
            WHEN 'Do' THEN o.Do
            WHEN 'Fr' THEN o.Fr
        END AS quantity
    FROM orders o
    JOIN (
        SELECT 0 AS offset_day, 'Mo' AS day_code
        UNION ALL SELECT 1, 'Di'
        UNION ALL SELECT 2, 'Mi'
        UNION ALL SELECT 3, 'Do'
        UNION ALL SELECT 4, 'Fr'
    ) d ON TRUE
    WHERE (o.verarbeitet = 0 OR o.verarbeitet IS NULL)
      AND CASE d.day_code
            WHEN 'Mo' THEN o.Mo
            WHEN 'Di' THEN o.Di
            WHEN 'Mi' THEN o.Mi
            WHEN 'Do' THEN o.Do
            WHEN 'Fr' THEN o.Fr
          END IS NOT NULL
      AND ABS(COALESCE(CASE d.day_code
            WHEN 'Mo' THEN o.Mo
            WHEN 'Di' THEN o.Di
            WHEN 'Mi' THEN o.Mi
            WHEN 'Do' THEN o.Do
            WHEN 'Fr' THEN o.Fr
          END, 0)) > 0.0001
      AND DATE_ADD(
            STR_TO_DATE(CONCAT(o.Jahr, ' ', LPAD(o.KW, 2, '0'), ' Monday'), '%x %v %W'),
            INTERVAL d.offset_day DAY
          ) BETWEEN v_delivery_from AND v_delivery_to
      AND (p_customer_id IS NULL OR p_customer_id = 0 OR o.Kunde = p_customer_id);

    SELECT
        t.order_id,
        t.customer_id,
        t.article_id,
        t.article_number,
        t.delivery_date,
        t.quantity,
        a.name AS article_name,
        a.description AS article_description,
        a.unit_name,
        price.currency,
        price.net_amount,
        price.gross_amount,
        price.tax_rate_percentage,
        price.valid_from AS article_valid_from,
        price.valid_until AS article_valid_until,
        CASE
            WHEN price.net_amount IS NOT NULL THEN ROUND(t.quantity * price.net_amount, 2)
        END AS line_total_net,
        CASE
            WHEN price.gross_amount IS NOT NULL THEN ROUND(t.quantity * price.gross_amount, 2)
        END AS line_total_gross
    FROM tmp_order_lines t
    LEFT JOIN articles a
        ON a.id = t.article_id
        OR (t.article_id IS NULL AND a.article_number = t.article_number)
    LEFT JOIN prices price
        ON price.id = (
            SELECT pr.id
            FROM prices pr
            WHERE pr.article_id = a.id
              AND pr.valid_from <= t.delivery_date
              AND (pr.valid_until IS NULL OR pr.valid_until >= t.delivery_date)
            ORDER BY pr.valid_from DESC, pr.id DESC
            LIMIT 1
        )
    WHERE a.id IS NOT NULL
    ORDER BY t.customer_id, t.delivery_date;

    UPDATE orders
       SET verarbeitet = 1
     WHERE Id IN (SELECT DISTINCT order_id FROM tmp_order_lines);

    DROP TEMPORARY TABLE IF EXISTS tmp_order_lines;
END $$

DELIMITER ;
