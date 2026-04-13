# LexBridge Workspace Instructions

AI agents working in this workspace should follow these conventions to maintain consistency with the existing architecture and development patterns.

## Quick Reference

**Primary source of truth:** [.github/copilot-instructions.md](.github/copilot-instructions.md) — all AI agents must follow this guide.

**Architecture & documentation:**

- [DEVELOPER_DOCUMENTATION.md](DEVELOPER_DOCUMENTATION.md) — system design, layer breakdown, setup
- [ORDER_LAYER_ANALYSIS.md](ORDER_LAYER_ANALYSIS.md) — architectural decisions and pragmatic separation patterns
- [LINEITEM_REFACTORING_SUMMARY.md](LINEITEM_REFACTORING_SUMMARY.md) — refactoring history and decisions
- [REFACTORING_SUMMARY.md](REFACTORING_SUMMARY.md) — system-wide refactoring context
- [USER_DOCUMENTATION.md](USER_DOCUMENTATION.md) — end-user feature guide

---

## Backend Coding Patterns

### API Route Registration & Controllers

All API routes must be:

1. Registered in `Api/ApiKernel.php` via `$router->post()`, `$router->get()`, etc.
2. Implemented as exact paths (e.g., `/invoices`, not `/invoices/`)
3. Handled by a controller instantiated via `ControllerFactory`
4. Return responses through `$router->jsonResponse($data)`

```php
// Example: In Api/ApiKernel.php
$router->post('/invoices/create', fn() =>
    $this->factory->makeInvoiceController()->create()
);

// Example: In Controllers/InvoiceController.php
public function create(): array {
    return $this->invoiceService->createFromSelection();
}
```

**Response shape:** Always include `isSuccess` boolean; add domain-specific data (arrays, objects) at top level:

```php
return [
    'isSuccess' => true,
    'invoices' => [...],
    'error' => null
];
```

### Service Layer

- Services encapsulate business logic; receive dependencies via constructor
- Input sanitization and validation happen **before** repository calls
- Services wrap repository calls and map arrays to domain objects when needed
- Use `InputFilter` for sanitizing user input (see `src/Utils/InputFilter.php`)

```php
public function updateLineItem(array $data): array {
    $data = $this->sanitize($data);  // Coerce types, dates, currency
    $existing = $this->repository->findById($data['id']);
    if (!$existing) return ['isSuccess' => false, 'error' => 'Not found'];

    return $this->repository->update($data);
}
```

### Repository Layer

- Repositories own all SQL; mix of domain objects (`Invoice`, `Customer`) and associative arrays
- **Exception:** `OrderRepository` is pragmatically designed as a domain service (see [ORDER_LAYER_ANALYSIS.md](ORDER_LAYER_ANALYSIS.md)) — keep complex business logic there
- Table names must use `lexbridge_table()` from `config.php`, never hard-code
- Database access via `Database::getConnection()` singleton
- Stored procedures should be documented in `database/migrations/` and executed once per environment

```php
// ✅ Correct
$table = lexbridge_table('invoices');
$conn = Database::getConnection();

// ❌ Wrong
$conn->query("SELECT * FROM invoices");  // Hard-coded name
```

### Handling External APIs (Lexware)

- All Lexware API calls go through `HttpClient` (see `src/Http/HttpClient.php`)
- HttpClient manages headers (Bearer token), error logging, retry logic
- **Do not instantiate HTTP clients elsewhere**; reuse HttpClient pattern

```php
// ✅ Correct: Inject HttpClient, use it consistently
public function __construct(private HttpClient $http) {}
$response = $this->http->post('/invoices', $payload);

// ❌ Wrong: Ad-hoc requests
$curl = curl_init(...);  // Breaks consistency
```

---

## Frontend Coding Patterns

### Tab System & URL Resolution

- **Never hard-code paths.** Use `LexBridge.resolveApiUrl()` for API calls and `LexBridge.resolveInAppUrl()` for internal links
- Tab templates live in `Views/` and are cloned into DOM; template IDs must match `TabManager` configuration
- Tab events and data flows use `LexBridge` namespace; page controllers register under `window.lexBridge`

```javascript
// ✅ Correct
fetch(LexBridge.resolveApiUrl("customers/search?q=" + term));

// ❌ Wrong
fetch("/api/customers/search?q=" + term); // Breaks in subdirectories
```

### Search & Data Caching

- Customer and article searches use 5-minute TTL cache from `line-items.js`
- Debounce all search inputs (e.g., 300ms) to reduce API load
- Cache keys are based on search term + filter context

```javascript
// Pattern from line-items.js: debounced fetches with internal cache
const cachedResults = this.cache.get(term);
if (cachedResults && !this.cache.isExpired(term)) {
  return cachedResults;
}
// Otherwise fetch and cache...
```

### Response Contracts

- **Orders and invoices pages expect responses with `isSuccess` + arrays:**
  ```javascript
  { isSuccess: true, orders: [...], invoices: [...] }
  ```
- **Breaking response contracts breaks UI.** Test API changes against `public/js/pages/*.js` before merging

---

## Development Workflow

### Setup

1. Clone repo and copy `config.php` from `.github/` or existing environment
2. Set `API_BASE_URL`, `API_KEY`, `DB_HOST`, `DB_NAME`, etc. in `config.php`
3. Create database and run migrations:
   ```bash
   mysql -u root < database/migrations/*.sql
   ```
4. Install Composer dependencies:
   ```bash
   composer install
   composer dump-autoload  # After adding new namespaced classes
   ```

### Build & Test

```bash
# Run unit tests
composer test

# Development server (e.g., XAMPP)
# Place project in htdocs/ and access via http://localhost/lex-bridge/
```

### Adding New Features

**Checklist:**

1. ✅ Register route in `Api/ApiKernel.php`
2. ✅ Implement controller in `Controllers/` via `ControllerFactory`
3. ✅ Add service logic in `Services/`
4. ✅ Add data access in `Repositories/` (respect table naming via `lexbridge_table()`)
5. ✅ Use `InputFilter` for user input; return JSON arrays from repositories
6. ✅ Add response data to API response at top level (not nested)
7. ✅ Update frontend UI templates in `Views/`; clip template IDs to match `TabManager`
8. ✅ Test via manual SPA verification or add PHPUnit test to `tests/`
9. ✅ Run `composer dump-autoload` after adding namespaced classes

### Adding New Database Tables

1. Create migration SQL file in `database/migrations/` (e.g., `020_create_my_table.sql`)
2. Add table name mapping to `$tableNames` in `config.php`:
   ```php
   $tableNames = [
       'my_table' => 'my_table_prod_name'  // Production override
   ];
   ```
3. Use `lexbridge_table('my_table')` in repositories
4. Document the schema and any stored procedures in the migration file

---

## Special Considerations

### Order Processing

- `OrderRepository::generateLineItemsFromOrders()` is domain-complex; avoid splitting it across files
- Order processing includes date math (ISO weeks → delivery dates), price lookups, and transaction management
- See [ORDER_LAYER_ANALYSIS.md](ORDER_LAYER_ANALYSIS.md) for rationale

### LineItem Totals & Calculations

- `LineItemCalculator` provides precision helpers for currency/decimal calculations
- `LineItemService` sanitizes inputs before writing (dates, amounts, quantities)
- Ensure all numerical fields pass through `LineItemCalculator` before storage

### Testing Philosophy

- PHPUnit tests reside in `tests/`, organized by layer (`Services/`, `Repositories/`)
- **Current state:** Limited test coverage; manual SPA verification is primary validation
- If adding tests, follow existing patterns (see `tests/Services/LineItemServiceTest.php`)
- Tests expect `APP_ENV=testing` (set in `phpunit.xml`)

---

## Common Pitfalls & Solutions

| Issue                                           | Solution                                                                    |
| ----------------------------------------------- | --------------------------------------------------------------------------- |
| New class not recognized after adding namespace | Run `composer dump-autoload`                                                |
| API calls fail in subdirectory installs         | Always use `LexBridge.resolveApiUrl()` for URLs                             |
| Database table not found                        | Verify table name in `$tableNames` in `config.php`; use `lexbridge_table()` |
| Frontend template not showing                   | Check template ID matches `TabManager` config in `lex-bridge.js`            |
| HTTP calls to Lexware not logging               | Use `HttpClient` class; never instantiate cURL directly                     |
| Hard-coded `/api/` paths in JavaScript          | Replace with `LexBridge.resolveApiUrl()`                                    |
| Numerical precision errors in pricing           | Use `LineItemCalculator::calculateLineTotal()`                              |

---

## Code Style & Conventions

### PHP

- PSR-4 namespace prefix: `Luxullus\LexBridge`
- Strict types: `declare(strict_types=1);` at file top
- Type hints: favor explicit parameter/return types
- Constructor injection: pass dependencies, never access globals

### JavaScript

- Modules scoped under `window.lexBridge.*` for page classes
- Use `LexBridge.resolveApiUrl()` and `LexBridge.resolveInAppUrl()` for all paths
- Debounce event handlers (search, filters) at 300ms default
- Cache search results with TTL; invalidate on state changes

### Database

- Table names snake_case; column names camelCase or snake_case (consistent per table)
- Migrations are immutable; new changes go in new numbered files
- Always include `created_at`, `updated_at` timestamps where audit trails matter

---

## Reference Files

| File/Directory                          | Purpose                                                 |
| --------------------------------------- | ------------------------------------------------------- |
| `.github/copilot-instructions.md`       | **Master guide** for all AI coding patterns             |
| `src/Application.php`                   | App bootstrap, renders Home view                        |
| `Api/ApiKernel.php`                     | Route registration hub                                  |
| `Api/ApiRouter.php`                     | HTTP request → controller dispatch                      |
| `src/Controllers/ControllerFactory.php` | DI for controller instantiation                         |
| `src/Database/Database.php`             | PDO connection singleton                                |
| `src/Http/HttpClient.php`               | Lexware API client                                      |
| `config.php`                            | Environment configuration (keep out of version control) |
| `database/migrations/`                  | SQL schemas and stored procedures                       |
| `public/js/lex-bridge.js`               | Main SPA bootstrap and URL resolution                   |
| `Views/Home/home.php`                   | Home page template, injects `lexBridgeConfig`           |

---

## Questions for Agent

If you encounter an ambiguous requirement:

1. **Scope:** Is this a new API endpoint, a UI change, or a data-layer refactor?
2. **Integration:** Which existing services/repositories does this touch?
3. **Response shape:** What JSON structure do frontend consumers expect?
4. **Testing:** How will this be validated (manual SPA, unit test, or both)?

Refer to [.github/copilot-instructions.md](.github/copilot-instructions.md) first; escalate architectural questions by documenting decisions in a new `DECISION_*.md` file for future reference.
