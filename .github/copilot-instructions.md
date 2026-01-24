# Lex Bridge AI Guide

## Architecture & Flow

- index.php boots bootstrap.php, which loads config.php, defines API/DB constants, and exposes lexbridge_base_path()/lexbridge_base_uri() helpers used by the Home view and JavaScript routing.
- Luxullus\LexBridge\Application (src/Application.php) only renders Views/Home/home.php; all substantive data loads happen via front-end fetches to /api endpoints.
- api/index.php instantiates ApiKernel, which wires routes on ApiRouter; ApiRouter strips /api and trailing slashes, so register paths exactly (e.g. '/invoices', not '/invoices/').
- ApiKernel composes controllers through ControllerFactory; controllers delegate to services, which in turn rely on repositories for DB access and HttpClient for Lexware calls.

## Backend Conventions

- HttpClient (src/http/HttpClient.php) builds JSON requests against API_BASE_URL with Bearer API_KEY; new external calls should reuse it to keep headers and error handling consistent.
- Database access is centralized in Database::getConnection(); table names resolve through lexbridge_table() in config.php, so extend $tableNames instead of hard-coding schema names.
- Repository return shapes mix domain objects and associative arrays; API responses typically include isSuccess/error or statusCode keys—mirror existing patterns when adding endpoints.
- InvoiceRepository encapsulates stored-proc workflows (CALL create_invoice_from_selection, sp_create_invoices_for_pending_line_items) and UUID generation; reuse its helpers instead of duplicating SQL.
- OrderRepository eagerly checks for an optional verarbeitet flag and preloads article pricing snapshots; respect its normalization helpers when changing order or pricing logic.

## Front-End Notes

- public/js/lex-bridge.js boots TabManager and resolves URLs with LexBridge.resolveApiUrl(); base href/path is injected from HomeView via lexBridgeConfig.
- Tab content templates live in Views/\* and are cloned into the DOM; when adding UI, keep the template IDs in sync with TabManager configuration.
- public/js/pages/line-items.js drives customer/article comboboxes with debounced fetches to /api/customers/search and /api/articles/search, caches results (5 min TTL), and auto-persists selection via POST /api/line-items/update expecting {isSuccess,lineItem}.
- LineItemService sanitize helpers coerce numeric and date inputs; ensure new fields pass through similar guards before writing to repositories.
- Orders and invoices pages rely on LexBridge tab events and expect API payloads with top-level isSuccess plus domain-specific arrays (orders, invoices, etc.); keep response contracts stable to avoid breaking UI scripts.

## Dev Workflow & Environment

- config.php contains secrets and CORS origins; treat it as environment configuration and keep lexbridge_table mappings aligned with actual MySQL tables.
- Run composer dump-autoload after adding PHP classes so PSR-4 autoloading recognizes new namespaces.
- SQL files in the repo (e.g. sp_create_invoices_for_pending_line_items.optimized.sql) document required stored procedures; execute or adapt them when preparing databases.
- No PHPUnit tests are defined despite the dev dependency; manual verification via the SPA and direct API calls is expected unless you add coverage.
- New API features should be registered inside ApiKernel, expose them through the relevant controller/service/repository stack, and keep return values JSON-serializable arrays for ApiRouter->jsonResponse().
