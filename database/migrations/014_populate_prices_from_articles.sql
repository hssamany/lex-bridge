-- Populate prices table from articles table
-- This creates price records for articles that have prices but no price history

INSERT INTO prices (article_id, net_amount, gross_amount, tax_rate_percentage, currency, valid_from, valid_until)
SELECT 
    a.id AS article_id,
    a.net_price AS net_amount,
    a.gross_price AS gross_amount,
    a.tax_rate AS tax_rate_percentage,
    'EUR' AS currency,
    a.created_at AS valid_from,
    NULL AS valid_until
FROM articles a
WHERE a.net_price IS NOT NULL 
  AND a.net_price > 0
  AND NOT EXISTS (
    SELECT 1 FROM prices p WHERE p.article_id = a.id
  );
