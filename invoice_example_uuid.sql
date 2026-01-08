-- Example INSERT statements using UUID for the invoice example
-- This demonstrates how to insert an invoice with line items using UUIDs

START TRANSACTION;

-- Generate UUIDs for this example
-- Invoice UUID: 'a1b2c3d4-e5f6-4a7b-8c9d-0e1f2a3b4c5d'
-- Line item 1 UUID: 'b2c3d4e5-f6a7-4b8c-9d0e-1f2a3b4c5d6e'
-- Line item 2 UUID: 'c3d4e5f6-a7b8-4c9d-0e1f-2a3b4c5d6e7f'

-- Step 1: Insert the main invoice record
INSERT INTO invoices
    (
    id,
    contact_id,
    voucher_date,
    archived,
    title,
    introduction,
    remark,
    currency,
    total_net_amount,
    total_gross_amount,
    tax_type,
    payment_term_label,
    payment_term_duration,
    payment_discount_percentage,
    payment_discount_range,
    shipping_date,
    shipping_type,
    status
    )
VALUES
    (
        'a1b2c3d4-e5f6-4a7b-8c9d-0e1f2a3b4c5d', -- UUID
        1, -- contact_id (needs to match existing contact in contacts table)
        '2023-02-22',
        0,
        'Rechnung',
        'Ihre bestellten Positionen stellen wir Ihnen hiermit in Rechnung',
        'Vielen Dank für Ihren Einkauf',
        'EUR',
        5.00,
        5.00,
        'net',
        '10 Tage - 3 %, 30 Tage netto',
        30,
        3.00,
        10,
        '2023-04-22',
        'delivery',
        'draft'
);

-- Step 2: Insert line items with UUIDs
-- Line item 1: Product
INSERT INTO invoice_line_items
    (
    id,
    invoice_id,
    line_order,
    type,
    name,
    quantity,
    unit_name,
    currency,
    net_amount,
    tax_rate_percentage,
    discount_percentage,
    line_total_net,
    line_total_gross
    )
VALUES
    (
        'b2c3d4e5-f6a7-4b8c-9d0e-1f2a3b4c5d6e', -- UUID for line item
        'a1b2c3d4-e5f6-4a7b-8c9d-0e1f2a3b4c5d', -- UUID reference to invoice
        1,
        'custom',
        'Energieriegel Testpaket',
        1.000,
        'Stück',
        'EUR',
        5.00,
        0.00,
        0.00,
        5.00,
        5.00
);

-- Line item 2: Text
INSERT INTO invoice_line_items
    (
    id,
    invoice_id,
    line_order,
    type,
    name,
    description
    )
VALUES
    (
        'c3d4e5f6-a7b8-4c9d-0e1f-2a3b4c5d6e7f', -- UUID for line item
        'a1b2c3d4-e5f6-4a7b-8c9d-0e1f2a3b4c5d', -- UUID reference to invoice
        2,
        'text',
        'Strukturieren Sie Ihre Belege durch Text-Elemente.',
        'Das hilft beim Verständnis'
);

COMMIT;

-- Query to retrieve the invoice with line items
SELECT
    i.*,
    c.customer_number,
    c.company_name,
    (
        SELECT JSON_ARRAYAGG(
            JSON_OBJECT(
                'id', li.id,
                'lineOrder', li.line_order,
                'type', li.type,
                'name', li.name,
                'quantity', li.quantity,
                'netAmount', li.net_amount,
                'lineTotalNet', li.line_total_net
            )
        )
    FROM invoice_line_items li
    WHERE li.invoice_id = i.id
    ORDER BY li.line_order
    ) as line_items
FROM invoices i
    LEFT JOIN contacts c ON i.contact_id = c.id
WHERE i.id = 'a1b2c3d4-e5f6-4a7b-8c9d-0e1f2a3b4c5d';
