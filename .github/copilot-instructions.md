# Copilot Instructions for AI Agents

## Project Overview
This is a PHP-based web application for managing invoices, customers, contacts, and line items. The architecture is modular, separating API logic, core application logic, and public assets. The project uses a custom MVC-like structure, not a standard framework.

## Key Architectural Patterns
- **API Layer**: All API endpoints are under `api/` (see `ApiRouter.php`, `ApiKernel.php`). Routing and request handling are custom.
- **Controllers**: Business logic is in `src/controllers/`, e.g., `InvoiceController.php`, `CustomerController.php`.
- **Services**: Domain logic is in `src/services/`, e.g., `InvoiceService.php`.
- **Repositories**: Data access is in `src/repositories/`, e.g., `InvoiceRepository.php`.
- **Models**: Data structures are in `src/models/`.
- **Views**: Rendered PHP views are in `views/`.
- **Public Assets**: Frontend JS/CSS is in `public/` (see `public/js/`, `public/css/`).

## Data Flow
- HTTP requests enter via `index.php` or `api/index.php`.
- API requests are routed by `ApiRouter.php` to controllers, which use services and repositories.
- Database access is via `src/database/Database.php` and repository classes.
- Frontend JS (e.g., `public/js/lex-bridge.js`) interacts with the API endpoints.

## Developer Workflows
- **No standard build system**: PHP and JS are run as-is. No transpilation or bundling.
- **Database**: SQL files in the root (e.g., `invoice.sql`) are for schema or data seeding.
- **Debugging**: Use `api/debug.php` for API debugging.
- **Entry Points**: Main entry is `index.php` (web) and `api/index.php` (API).

## Project-Specific Conventions
- **Custom MVC**: Not using Laravel/Symfony; all routing, DI, and logic are hand-rolled.
- **File Naming**: Singular for models/services/repositories (e.g., `Invoice.php`, `InvoiceService.php`).
- **Frontend Components**: JS components are in `public/js/components/` with separate `.js`, `.css`, `.html` files per component.
- **No automated tests**: No test framework or test files present.

## Integration Points
- **Frontend ↔ Backend**: JS in `public/js/` calls API endpoints under `/api/`.
- **Database**: Accessed via repository classes; see `src/database/Database.php` for connection details.

## Examples
- To add a new API endpoint: create a controller method, add a route in `ApiRouter.php`, and implement logic in a service/repository.
- To add a new frontend feature: create a JS component in `public/js/components/`, update `lex-bridge.js` to initialize it, and connect to the API as needed.

## Key Files/Directories
- `api/ApiRouter.php`, `api/ApiKernel.php`: API routing
- `src/controllers/`, `src/services/`, `src/repositories/`, `src/models/`: Core logic
- `public/js/`, `public/css/`: Frontend assets
- `views/`: PHP views
- `invoice.sql`, `customer.sql`, etc.: Database schema/data

---
For questions or unclear patterns, review the relevant directory or ask for clarification.
