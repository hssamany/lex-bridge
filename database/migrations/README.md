# Database Migration Order

Apply the SQL scripts in this directory in numeric order (001 → 011). Each script is idempotent where possible and documents the purpose at the top:

1. 001_create_articles.sql
2. 002_create_prices.sql
3. 003_create_invoices.sql
4. 004_create_invoice_line_items.sql
5. 005_create_invoice_sync_log.sql
6. 006_alter_customer_add_lex_fields.sql
7. 007_alter_invoice_line_items_add_order_reference.sql
8. 008_alter_invoice_line_items_add_customer_number.sql
9. 009_alter_orders_add_verarbeitet.sql
10. 010_sp_generate_invoice_line_items.sql
11. 011_sp_create_invoices_for_pending_line_items.sql

> Tip: capture the database state after each step so the deployment can resume safely if interrupted.
