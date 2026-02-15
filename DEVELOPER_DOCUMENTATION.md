# LexBridge - Developer Documentation

## Table of Contents
1. [Architecture Overview](#architecture-overview)
2. [Project Structure](#project-structure)
3. [Setup & Installation](#setup--installation)
4. [Configuration](#configuration)
5. [Database Schema](#database-schema)
6. [API Endpoints](#api-endpoints)
7. [Backend Components](#backend-components)
8. [Frontend Architecture](#frontend-architecture)
9. [Development Workflow](#development-workflow)
10. [Adding New Features](#adding-new-features)
11. [Testing & Debugging](#testing--debugging)
12. [Deployment](#deployment)

---

## Architecture Overview

### High-Level Design

LexBridge follows a **Service-Oriented Architecture** with separation of concerns:

```
┌─────────────────────────────────────────────────────────┐
│              Frontend (JavaScript/SPA)                   │
│  ├─ Page Controllers (home.js, invoices.js, etc)        │
│  ├─ Components (TabManager, ToastNotifier)              │
│  └─ Modules (Search, Persistence)                       │
└────────────────────────┬────────────────────────────────┘
                         │ AJAX Requests
┌────────────────────────▼────────────────────────────────┐
│              API Layer (/api/*)                         │
│  ├─ ApiKernel (Route Registration)                      │
│  └─ ApiRouter (Request Handling & Response)             │
└────────────────────────┬────────────────────────────────┘
                         │ Dependency Injection
┌────────────────────────▼────────────────────────────────┐
│         Controller Layer                                 │
│  ├─ InvoiceController                                   │
│  ├─ LineItemController                                  │
│  ├─ OrderController                                     │
│  └─ ... (see Backend Components)                        │
└────────────────────────┬────────────────────────────────┘
                         │ Delegation
┌────────────────────────▼────────────────────────────────┐
│         Service Layer                                    │
│  ├─ InvoiceService                                      │
│  ├─ LineItemService                                     │
│  └─ ... (Business Logic)                                │
└────────────────────────┬────────────────────────────────┘
                         │ Data Access
┌────────────────────────▼────────────────────────────────┐
│         Repository Layer                                 │
│  ├─ InvoiceRepository                                   │
│  ├─ LineItemRepository                                  │
│  └─ ... (Database Operations)                           │
└────────────────────────┬────────────────────────────────┘
                         │ PDO Connection
┌────────────────────────▼────────────────────────────────┐
│              Database (MySQL)                            │
│  └─ tables: invoices, line_items, orders, etc          │
└─────────────────────────────────────────────────────────┘

┌────────────────────────────────────────────────────────┐
│         External: Lexware API (HttpClient)              │
│  └─ REST API for invoice/contact transmission          │
└────────────────────────────────────────────────────────┘
```

### Key Principles

- **Single Responsibility:** Each class has one reason to change
- **Dependency Injection:** Services receive dependencies via constructor
- **Repository Pattern:** Data access abstracted through repositories
- **Service Layer:** Business logic separated from HTTP concerns
- **Factory Pattern:** ControllerFactory creates controllers with dependencies

---

## Project Structure

```
lex-bridge/
├── index.php                      # Entry point (SPA shell)
├── bootstrap.php                  # App initialization
├── config.php                     # Configuration & environment
├── composer.json                  # Dependencies
├── phpunit.xml                    # Test configuration
│
├── api/
│   ├── index.php                  # API entry point
│   ├── ApiKernel.php              # Route registration
│   ├── ApiRouter.php              # Request routing & response handling
│   └── debug.php                  # API debugging endpoint
│
├── public/
│   ├── css/
│   │   └── styles.css             # Styling
│   ├── js/
│   │   ├── app.js                 # Application entry point
│   │   ├── lex-bridge.js          # Main LexBridge class
│   │   ├── components/
│   │   │   ├── tab-manager/
│   │   │   ├── toast-notifier/
│   │   │   └── line-item-editor-dialog.js
│   │   ├── modules/
│   │   │   ├── customer-search-controller.js
│   │   │   ├── article-search-controller.js
│   │   │   ├── line-item-persistence.js
│   │   │   └── article-selection-manager.js
│   │   ├── pages/
│   │   │   ├── home.js
│   │   │   ├── invoices.js
│   │   │   ├── line-items.js
│   │   │   └── orders.js
│   │   └── utils/
│   │       ├── ui-helpers.js
│   │       └── customer-search.js
│   └── favicon.ico
│
├── src/
│   ├── Application.php             # Main app class (serves SPA shell)
│   ├── constants/
│   │   ├── ContentType.php
│   │   ├── HttpHeader.php
│   │   ├── HttpMethod.php
│   │   └── HttpStatus.php
│   ├── controllers/
│   │   ├── ControllerFactory.php
│   │   ├── InvoiceController.php
│   │   ├── LineItemController.php
│   │   ├── OrderController.php
│   │   ├── ArticleController.php
│   │   ├── CustomerController.php
│   │   └── ContactController.php
│   ├── database/
│   │   └── Database.php            # PDO connection management
│   ├── http/
│   │   ├── HttpClient.php          # Lexware API client
│   │   └── HttpResponse.php        # HTTP response wrapper
│   ├── models/
│   │   ├── Invoice.php
│   │   ├── InvoiceLineItem.php
│   │   ├── Customer.php
│   │   ├── Contact.php
│   │   └── ... (other models)
│   ├── repositories/
│   │   ├── InvoiceRepository.php
│   │   ├── LineItemRepository.php
│   │   ├── OrderRepository.php
│   │   ├── ArticleRepository.php
│   │   ├── CustomerRepository.php
│   │   └── ... (other repositories)
│   ├── services/
│   │   ├── InvoiceService.php
│   │   ├── LineItemService.php
│   │   ├── OrderService.php
│   │   ├── ArticleService.php
│   │   ├── CustomerService.php
│   │   ├── ContactService.php
│   │   ├── LineItemCalculator.php
│   │   └── ... (other services)
│   └── Views/
│       ├── Home/
│       │   ├── home.php            # SPA shell template
│       │   └── HomeView.php
│       ├── invoices/
│       ├── line-items/
│       ├── orders/
│       ├── contacts/
│       └── shared/
│
├── database/
│   └── migrations/
│       ├── 001_create_articles.sql
│       ├── 002_create_prices.sql
│       ├── 003_create_invoices.sql
│       ├── 004_create_invoice_line_items.sql
│       ├── 005_create_invoice_sync_log.sql
│       ├── 007_alter_invoice_line_items_add_order_reference.sql
│       ├── 009_alter_orders_add_verarbeitet.sql
│       ├── 010_sp_generate_invoice_line_items.sql
│       ├── 011_sp_create_invoices_for_pending_line_items.sql
│       ├── 012_create_customers_article.sql
│       └── README.md
│
├── tests/
│   ├── Repositories/
│   │   ├── ArticleRepositoryTest.php
│   │   ├── CustomerRepositoryTest.php
│   │   ├── InvoiceRepositoryTest.php
│   │   ├── LineItemRepositoryTest.php
│   │   └── OrderRepositoryTest.php
│   └── Services/
│       ├── ContactServiceTest.php
│       └── LineItemCalculatorTest.php
│
├── vendor/                        # Composer dependencies
├── USER_DOCUMENTATION.md          # User-facing documentation
└── DEVELOPER_DOCUMENTATION.md     # This file
```

---

## Setup & Installation

### Prerequisites

- **PHP 7.4+** (tested with 7.4, 8.0, 8.1+)
- **MySQL 5.7+** or **MariaDB 10.2+**
- **Apache** with mod_rewrite enabled (or XAMPP)
- **Composer** for dependency management
- **curl** PHP extension enabled

### Installation Steps

1. **Clone or Download Repository:**
   ```bash
   cd /path/to/your/webroot
   # Extract LexBridge into a folder
   ```

2. **Install Dependencies:**
   ```bash
   cd lex-bridge
   composer install
   ```

3. **Configure Environment:**
   ```bash
   cp config.php config.php.example
   # Edit config.php with your database and API credentials
   ```

4. **Set Up Database:**
   ```bash
   # Use MySQL client or phpMyAdmin
   # Apply migrations in order (see database/migrations/README.md)
   mysql -u root -p < database/migrations/001_create_articles.sql
   mysql -u root -p < database/migrations/002_create_prices.sql
   # ... continue for all migrations
   ```

5. **Verify Installation:**
   - Navigate to `http://localhost/lex-bridge/` in your browser
   - Confirm the interface loads without errors
   - Check browser console (F12) for any JavaScript errors

### Directory Permissions

Ensure web server can write to logs (if applicable):
```bash
chmod -R 755 /path/to/lex-bridge
# If using separate log directory:
chmod -R 775 /path/to/lex-bridge/logs
```

---

## Configuration

### config.php Structure

```php
$environmentConfigs = [
    'production' => [
        'apiKey' => 'YOUR_LEXWARE_API_KEY',
        'baseUrl' => 'https://api.lexware.io/v1',
        'dbHost' => 'db.example.com',
        'dbPort' => '3306',
        'dbName' => 'lexbridge',
        'dbUsername' => 'user',
        'dbPassword' => 'password',
        'allowedOrigins' => ['https://yourdomain.com'],
    ],
    'development' => [
        // ... dev configuration
    ],
    'testing' => [
        // ... test configuration
    ],
];

$tableNames = [
    'invoices' => 'invoices',
    'invoice_line_items' => 'invoice_line_items',
    // ... other table mappings
];
```

### Environment Variables

Set `APP_ENV` via:
- `.env` file (using Dotenv)
- Server environment variable
- PHP configuration

```bash
# .env example
APP_ENV=development
# or
export APP_ENV=production
```

### Table Name Mapping

The `$tableNames` array in config.php allows flexible table naming:

```php
$tableNames = [
    'invoices' => 'invoices',
    'invoice_line_items' => 'invoice_line_items',
    'articles' => 'articles',
    'prices' => 'prices',
    'customer' => 'customer',
    'customers_article' => 'customers_article',
    'orders' => 'orders',
];
```

Use the `lexbridge_table()` helper function in code:
```php
$table = lexbridge_table('invoices'); // Returns 'invoices' (or custom name)
```

---

## Database Schema

### Core Tables

#### `articles` Table
Stores article/product catalog synced from Lexware.

```sql
CREATE TABLE `articles` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `lexware_article_id` CHAR(36) NOT NULL UNIQUE,  -- UUID
    `article_number` VARCHAR(50) NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `net_price` DECIMAL(10, 2) NOT NULL,
    `gross_price` DECIMAL(10, 2) NOT NULL,
    `tax_rate` DECIMAL(5, 2) NOT NULL,              -- e.g., 19.00
    `unit_name` VARCHAR(50) NOT NULL DEFAULT 'piece',
    `active` BOOLEAN NOT NULL DEFAULT TRUE,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE,
    INDEX `idx_article_number` (`article_number`),
    INDEX `idx_lexware_article_id` (`lexware_article_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### `invoices` Table
Stores invoices created locally, ready for transmission to Lexware.

```sql
CREATE TABLE `invoices` (
    `id` CHAR(36) NOT NULL PRIMARY KEY,             -- UUID v4
    `contact_id` INT NOT NULL,                      -- Customer ID
    `voucher_date` DATE NOT NULL,
    `archived` BOOLEAN NOT NULL DEFAULT FALSE,
    `title` VARCHAR(255) NOT NULL DEFAULT 'Rechnung',
    `introduction` TEXT NULL,
    `remark` TEXT NULL,
    `currency` VARCHAR(3) NOT NULL DEFAULT 'EUR',
    `total_net_amount` DECIMAL(10, 2) NULL,
    `total_gross_amount` DECIMAL(10, 2) NULL,
    `tax_type` ENUM('net', 'gross') NOT NULL DEFAULT 'net',
    
    -- Payment terms
    `payment_term_label` VARCHAR(255) NULL,
    `payment_term_duration` INT NULL,
    `payment_discount_percentage` DECIMAL(5, 2) NULL,
    `payment_discount_range` INT NULL,
    
    -- Shipping
    `shipping_date` DATE NULL,
    `shipping_type` ENUM('delivery', 'pickup', 'service') NOT NULL DEFAULT 'delivery',
    
    -- Status tracking
    `status` ENUM('draft', 'ready', 'transmitting', 'transmitted', 
                  'transmission_error', 'paid', 'cancelled') NOT NULL DEFAULT 'draft',
    
    -- Lexware sync data
    `lex_id` VARCHAR(255) NULL,
    `lex_resource_uri` TEXT NULL,
    `lex_version` INT NOT NULL DEFAULT 0,
    `lex_created_date` TIMESTAMP NULL,
    `lex_updated_date` TIMESTAMP NULL,
    
    -- Error tracking
    `last_error_message` TEXT NULL,
    `last_error_code` VARCHAR(50) NULL,
    `transmission_attempts` INT NOT NULL DEFAULT 0,
    `last_transmission_attempt` TIMESTAMP NULL,
    `transmitted_at` TIMESTAMP NULL,
    
    -- Timestamps
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE,
    
    UNIQUE KEY `uniq_lex_id` (`lex_id`),
    KEY `idx_contact_id` (`contact_id`),
    KEY `idx_status` (`status`),
    KEY `idx_voucher_date` (`voucher_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

#### `invoice_line_items` Table
Individual positions/items within an invoice.

```sql
CREATE TABLE `invoice_line_items` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `invoice_id` CHAR(36) NULL,                     -- FK to invoices
    `article_id` BIGINT UNSIGNED NOT NULL,          -- FK to articles
    `customer_id` INT NOT NULL,
    `order_id` BIGINT UNSIGNED NULL,                -- FK to orders (optional)
    `quantity` DECIMAL(10, 2) NOT NULL,
    `position` INT NOT NULL,
    `net_price` DECIMAL(10, 2) NOT NULL,
    `gross_price` DECIMAL(10, 2) NOT NULL,
    `tax_percentage` DECIMAL(5, 2) NOT NULL,
    `description` TEXT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE,
    
    KEY `idx_invoice_id` (`invoice_id`),
    KEY `idx_customer_id` (`customer_id`),
    KEY `idx_article_id` (`article_id`),
    KEY `idx_order_id` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

#### `orders` Table
Orders from Lexware with daily quantities for processing.

```sql
CREATE TABLE `orders` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `customer_id` INT NOT NULL,
    `article_id` BIGINT UNSIGNED NOT NULL,
    `monday` DECIMAL(10, 2) NOT NULL DEFAULT 0,
    `tuesday` DECIMAL(10, 2) NOT NULL DEFAULT 0,
    `wednesday` DECIMAL(10, 2) NOT NULL DEFAULT 0,
    `thursday` DECIMAL(10, 2) NOT NULL DEFAULT 0,
    `friday` DECIMAL(10, 2) NOT NULL DEFAULT 0,
    `calendar_week` INT NOT NULL,
    `order_date` DATE NOT NULL,
    `verarbeitet` BOOLEAN NOT NULL DEFAULT FALSE,  -- Processed flag
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE,
    
    KEY `idx_customer_id` (`customer_id`),
    KEY `idx_article_id` (`article_id`),
    KEY `idx_calendar_week` (`calendar_week`),
    KEY `idx_verarbeitet` (`verarbeitet`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

#### `customer` Table
Customer data synced from Lexware.

```sql
CREATE TABLE `customer` (
    `id` INT NOT NULL PRIMARY KEY,
    `lex_contact_id` CHAR(36) NOT NULL UNIQUE,
    `company_name` VARCHAR(255) NOT NULL,
    `contact_person` VARCHAR(255) NULL,
    `email` VARCHAR(255) NULL,
    `phone` VARCHAR(20) NULL,
    `address` VARCHAR(255) NULL,
    `city` VARCHAR(100) NULL,
    `postal_code` VARCHAR(10) NULL,
    `country` VARCHAR(100) NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE,
    
    KEY `idx_lex_contact_id` (`lex_contact_id`),
    KEY `idx_company_name` (`company_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Relationships

```
┌──────────────┐
│  customer    │
└────┬─────────┘
     │ id (PK)
     │
     │──────────────────┐
     │                  │
     │ (1:N)    (1:N)   │
     │                  │
┌────▼──────────┐   ┌──▼────────────────┐
│ invoices      │   │ invoice_line_items │
└────┬──────────┘   └──┬─────────────────┘
     │ contact_id       │ customer_id
     │                  │ article_id
     │                  │ invoice_id (FK)
     │                  │
     │                  │ (N:1)
     │                  │
     └──────────┬───────┘
                │
            (1:N)
                │
         ┌──────▼─────────┐
         │  articles      │
         └────────────────┘
                │ id (PK)
                │
         (1:N)  │
                │
         ┌──────▼──────┐
         │  orders     │
         └─────────────┘
```

### Key Indexes

- `invoices.id` - UUID primary key
- `invoices.lex_id` - Unique Lexware reference
- `invoices.status` - Filter by transmission status
- `invoice_line_items.invoice_id` - Join with invoices
- `invoice_line_items.customer_id` - Filter by customer
- `articles.article_number` - Search articles
- `customer.company_name` - Search customers

---

## API Endpoints

### Overview

All endpoints are RESTful JSON APIs under `/api/` path. Responses are JSON encoded.

### Response Format

**Success Response:**
```json
{
  "isSuccess": true,
  "invoices": [...],
  "orders": [...]
}
```

**Error Response:**
```json
{
  "isSuccess": false,
  "error": "Error message",
  "statusCode": 400
}
```

### Endpoints

#### Line Items Management

**GET /api/line-items**
- **Purpose:** Retrieve line items with optional filters
- **Query Parameters:**
  - `customer_id` (int) - Filter by customer
  - `created_at_from` (date: YYYY-MM-DD) - Date range start
  - `created_at_to` (date: YYYY-MM-DD) - Date range end
- **Response:**
  ```json
  {
    "isSuccess": true,
    "lineItems": [
      {
        "id": 1,
        "customer_id": 100,
        "article_id": 5,
        "quantity": 10,
        "net_price": 50.00,
        "gross_price": 59.50,
        "tax_percentage": 19.00,
        "created_at": "2024-02-04 10:30:00"
      }
    ]
  }
  ```

**POST /api/line-items/update**
- **Purpose:** Create or update a line item
- **Request Body:**
  ```json
  {
    "customer_id": 100,
    "article_id": 5,
    "quantity": 10,
    "net_price": 50.00,
    "tax_percentage": 19.00
  }
  ```
- **Response:**
  ```json
  {
    "isSuccess": true,
    "lineItem": { ... }
  }
  ```

#### Invoices

**GET /api/invoices**
- **Purpose:** Retrieve all invoices
- **Response:**
  ```json
  {
    "success": true,
    "invoices": [
      {
        "id": "uuid-string",
        "company_name": "Customer ABC",
        "voucher_date": "2024-02-04",
        "item_count": 5,
        "status": "transmitted",
        "transmission_attempts": 1,
        "total_gross_amount": 500.00
      }
    ]
  }
  ```

**POST /api/invoices/create**
- **Purpose:** Create a new invoice from line items
- **Request Body:**
  ```json
  {
    "customer_id": 100,
    "line_item_ids": [1, 2, 3],
    "currency": "EUR"
  }
  ```
- **Response:**
  ```json
  {
    "isSuccess": true,
    "invoice": { ... }
  }
  ```

**POST /api/invoices/transfer**
- **Purpose:** Transmit invoice to Lexware
- **Request Body:**
  ```json
  {
    "invoice_id": "uuid-string"
  }
  ```
- **Response:**
  ```json
  {
    "statusCode": 200,
    "isSuccess": true,
    "invoice": { ... }
  }
  ```

#### Orders

**GET /api/orders**
- **Purpose:** Retrieve orders with date filtering
- **Query Parameters:**
  - `geaendertAm_from` (date) - **Required** - Start date
  - `geaendertAm_to` (date) - **Required** - End date
  - `customer_id` (int) - Optional - Filter by customer
- **Response:**
  ```json
  {
    "isSuccess": true,
    "orders": [
      {
        "id": 1,
        "customer_id": 100,
        "article_id": 5,
        "monday": 10,
        "tuesday": 5,
        ...
        "verarbeitet": false
      }
    ]
  }
  ```

**POST /api/orders/generate**
- **Purpose:** Generate line items from selected orders
- **Request Body:**
  ```json
  {
    "order_ids": [1, 2, 3]
  }
  ```
- **Response:**
  ```json
  {
    "isSuccess": true,
    "lineItems": [ ... ],
    "message": "Generated 3 line items"
  }
  ```

#### Articles

**GET /api/articles/search**
- **Purpose:** Search articles by name or number
- **Query Parameters:**
  - `q` (string) - Search query
- **Response:**
  ```json
  {
    "isSuccess": true,
    "articles": [
      {
        "id": 5,
        "article_number": "ART-001",
        "name": "Article Name",
        "net_price": 50.00,
        "gross_price": 59.50,
        "tax_rate": 19.00
      }
    ]
  }
  ```

**POST /api/articles/sync**
- **Purpose:** Sync articles from Lexware API
- **Query/Body Parameters:**
  - `page` (int) - Pagination (0-based)
- **Response:**
  ```json
  {
    "isSuccess": true,
    "articlesCount": 50,
    "message": "Synced 50 articles"
  }
  ```

#### Customers

**GET /api/customers/search**
- **Purpose:** Search customers by name or number
- **Query Parameters:**
  - `q` (string) - Search query
- **Response:**
  ```json
  {
    "isSuccess": true,
    "customers": [
      {
        "id": 100,
        "company_name": "Customer ABC",
        "contact_person": "John Doe",
        "email": "john@example.com"
      }
    ]
  }
  ```

#### Contacts

**GET /api/contacts**
- **Purpose:** Retrieve all contacts
- **Response:**
  ```json
  {
    "isSuccess": true,
    "contacts": [ ... ]
  }
  ```

**POST /api/contacts/sync**
- **Purpose:** Sync contacts from Lexware API
- **Response:**
  ```json
  {
    "isSuccess": true,
    "contactsCount": 25,
    "message": "Synced 25 contacts"
  }
  ```

---

## Backend Components

### Controller Layer

Controllers handle HTTP requests and delegate to services.

#### Example: InvoiceController

```php
namespace Luxullus\LexBridge\Controllers;

final class InvoiceController
{
    private InvoiceService $invoiceService;
    
    public function __construct(InvoiceService $invoiceService)
    {
        $this->invoiceService = $invoiceService;
    }
    
    public function getInvoices(): array
    {
        $invoices = $this->invoiceService->getInvoices();
        return [
            'success' => true,
            'invoices' => $invoices
        ];
    }
    
    public function transferInvoiceToLexware(string $invoiceId): array
    {
        $result = $this->invoiceService->transferInvoiceById($invoiceId);
        return [
            'statusCode' => $result['response']->getStatusCode(),
            'isSuccess' => $result['response']->isSuccess(),
            'error' => $result['response']->getError(),
            'invoice' => $result['invoice']
        ];
    }
}
```

**Key Points:**
- Receives dependencies via constructor
- Methods return arrays (JSON serializable)
- Delegates business logic to services
- Handles parameter validation/parsing

### Service Layer

Services contain business logic and orchestrate operations.

#### Example: InvoiceService

```php
namespace Luxullus\LexBridge\Services;

final class InvoiceService
{
    private HttpClient $client;
    private InvoiceRepository $invoiceRepository;
    
    public function __construct(HttpClient $client, InvoiceRepository $invoiceRepository)
    {
        $this->client = $client;
        $this->invoiceRepository = $invoiceRepository;
    }
    
    public function getInvoices(): array
    {
        return $this->invoiceRepository->findAll();
    }
    
    public function transferInvoiceById(string $invoiceId): array
    {
        // Fetch from DB
        $invoice = $this->invoiceRepository->findById($invoiceId);
        if (!$invoice) {
            return [
                'response' => new HttpResponse(404, null, 'Not found'),
                'invoice' => null
            ];
        }
        
        // Update status
        $this->invoiceRepository->updateStatus($invoiceId, 'transmitting');
        
        // Send to Lexware
        $response = $this->client->post('/invoices', $invoice->toLexwarePayload());
        
        // Update with result
        if ($response->isSuccess()) {
            $this->invoiceRepository->updateAfterTransmission($invoiceId, ...);
        } else {
            $this->invoiceRepository->updateWithError($invoiceId, ...);
        }
        
        return ['response' => $response, 'invoice' => $invoice->toArray()];
    }
    
    public function createInvoiceWithItems(int $customerId, ?string $currency, array $lineItems): array
    {
        // Business logic to create invoice
        $invoice = new Invoice();
        $invoice->id = Invoice::generateUuid();
        $invoice->contactId = $customerId;
        $invoice->currency = $currency ?? 'EUR';
        
        $this->invoiceRepository->save($invoice);
        
        // Associate line items
        foreach ($lineItems as $itemId) {
            $this->invoiceRepository->assignLineItem($invoice->id, $itemId);
        }
        
        return ['isSuccess' => true, 'invoice' => $invoice];
    }
}
```

**Key Points:**
- Contains business logic
- Coordinates between repositories and external APIs
- Handles transactions and error handling
- Returns structured arrays for controllers

### Repository Layer

Repositories encapsulate data access patterns.

#### Example: InvoiceRepository

```php
namespace Luxullus\LexBridge\Repositories;

class InvoiceRepository
{
    private PDO $db;
    private string $invoiceTable;
    
    public function __construct()
    {
        $this->db = Database::getConnection();
        $this->invoiceTable = lexbridge_table('invoices');
    }
    
    public function findById(string $id): ?Invoice
    {
        $sql = "SELECT * FROM {$this->invoiceTable} WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? Invoice::fromDatabase($row) : null;
    }
    
    public function findAll(array $filters = []): array
    {
        $sql = "SELECT * FROM {$this->invoiceTable}";
        $params = [];
        
        // Build WHERE clause from filters
        $where = [];
        if (!empty($filters['status'])) {
            $where[] = "status = :status";
            $params[':status'] = $filters['status'];
        }
        
        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }
        
        $sql .= " ORDER BY created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return array_map(
            fn($row) => Invoice::fromDatabase($row),
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );
    }
    
    public function save(Invoice $invoice): void
    {
        if ($invoice->id === null) {
            $this->insert($invoice);
        } else {
            $this->update($invoice);
        }
    }
    
    private function insert(Invoice $invoice): void
    {
        $sql = "INSERT INTO {$this->invoiceTable} 
                (id, contact_id, voucher_date, status, created_at)
                VALUES (:id, :contact_id, :voucher_date, :status, :created_at)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id' => $invoice->id,
            ':contact_id' => $invoice->contactId,
            ':voucher_date' => $invoice->voucherDate,
            ':status' => $invoice->status,
            ':created_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    public function updateStatus(string $invoiceId, string $status): void
    {
        $sql = "UPDATE {$this->invoiceTable} SET status = :status WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':status' => $status, ':id' => $invoiceId]);
    }
    
    public function updateAfterTransmission(string $invoiceId, array $lexwareData): void
    {
        $sql = "UPDATE {$this->invoiceTable} 
                SET status = 'transmitted',
                    lex_id = :lex_id,
                    transmission_attempts = transmission_attempts + 1,
                    last_transmission_attempt = NOW(),
                    transmitted_at = NOW()
                WHERE id = :id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':lex_id' => $lexwareData['id'] ?? null,
            ':id' => $invoiceId
        ]);
    }
}
```

**Key Points:**
- One repository per entity/aggregate
- Uses prepared statements (SQL injection prevention)
- Handles table name mapping via `lexbridge_table()`
- Returns model objects or arrays
- Encapsulates all SQL logic

### Models

Models represent domain entities with data transformation.

#### Example: Invoice

```php
namespace Luxullus\LexBridge\Models;

final class Invoice
{
    public ?string $id = null;
    public int $contactId;
    public string $voucherDate;
    public string $currency = 'EUR';
    public ?float $totalNetAmount = null;
    public ?float $totalGrossAmount = null;
    public string $status = 'draft';
    public int $transmissionAttempts = 0;
    
    // ... more fields
    
    public ?array $lineItems = null;  // Loaded on demand
    
    public static function generateUuid(): string
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            // ... UUID generation
        );
    }
    
    /**
     * Create Invoice from database row
     */
    public static function fromDatabase(array $row): self
    {
        $invoice = new self();
        $invoice->id = $row['id'] ?? null;
        $invoice->contactId = (int)$row['contact_id'];
        $invoice->voucherDate = $row['voucher_date'];
        $invoice->currency = $row['currency'] ?? 'EUR';
        $invoice->totalNetAmount = $row['total_net_amount'];
        $invoice->totalGrossAmount = $row['total_gross_amount'];
        $invoice->status = $row['status'] ?? 'draft';
        $invoice->transmissionAttempts = (int)($row['transmission_attempts'] ?? 0);
        
        return $invoice;
    }
    
    /**
     * Convert to Lexware API payload
     */
    public function toLexwarePayload(): array
    {
        return [
            'voucherDate' => $this->voucherDate,
            'title' => $this->title,
            'currency' => $this->currency,
            'totalNetAmount' => $this->totalNetAmount,
            'totalGrossAmount' => $this->totalGrossAmount,
            'lineItems' => array_map(
                fn($item) => $item->toLexwarePayload(),
                $this->lineItems ?? []
            )
        ];
    }
    
    /**
     * Convert to array for JSON response
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'contactId' => $this->contactId,
            'voucherDate' => $this->voucherDate,
            'currency' => $this->currency,
            'totalNetAmount' => $this->totalNetAmount,
            'totalGrossAmount' => $this->totalGrossAmount,
            'status' => $this->status,
            'transmissionAttempts' => $this->transmissionAttempts,
            'lineItems' => $this->lineItems
        ];
    }
}
```

**Key Points:**
- Pure data containers with validation
- Static factory methods for construction
- Transformation methods (toArray, toLexwarePayload)
- UUID generation for new entities

### HttpClient

Handles Lexware API communication.

```php
namespace Luxullus\LexBridge\Http;

final class HttpClient
{
    private string $apiKey;
    private string $baseUrl;
    private array $headers;
    
    public function __construct(string $apiKey, string $baseUrl)
    {
        $this->apiKey = $apiKey;
        $this->baseUrl = $baseUrl;
        $this->headers = [
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey
        ];
    }
    
    /**
     * Send GET request
     */
    public function get(string $endpoint): HttpResponse
    {
        $url = $this->baseUrl . $endpoint;
        $ch = curl_init($url);
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_errno($ch) ? curl_error($ch) : null;
        curl_close($ch);
        
        return new HttpResponse($code, $body, $error);
    }
    
    /**
     * Send POST request
     */
    public function post(string $endpoint, array $data): HttpResponse
    {
        $url = $this->baseUrl . $endpoint;
        $payload = json_encode($data);
        $ch = curl_init($url);
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_errno($ch) ? curl_error($ch) : null;
        curl_close($ch);
        
        return new HttpResponse($code, $body, $error);
    }
}

// Response wrapper
class HttpResponse
{
    private int $statusCode;
    private ?string $body;
    private ?string $error;
    
    public function __construct(int $statusCode, ?string $body, ?string $error = null)
    {
        $this->statusCode = $statusCode;
        $this->body = $body;
        $this->error = $error;
    }
    
    public function isSuccess(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }
    
    public function getStatusCode(): int { return $this->statusCode; }
    public function getBody(): ?string { return $this->body; }
    public function getError(): ?string { return $this->error; }
}
```

---

## Frontend Architecture

### Core JavaScript Classes

#### LexBridge (Main Class)

```javascript
class LexBridge {
    static version = '1.0.0';
    
    constructor() {
        this.tabManager = null;
        this.toastNotifier = null;
        this.invoicesPage = null;
        this.lineItemsPage = null;
        this.ordersPage = null;
        this.config = {
            apiEndpoint: '...',
            baseHref: '...',
            basePath: '...'
        };
    }
    
    async init() {
        this.initializeToastNotifier();
        await this.initializeTabManager();
        this.setupEventListeners();
        this.attachFormHandlers();
    }
    
    static resolveApiUrl(path) {
        // Resolves API endpoint paths
    }
    
    static resolveInAppUrl(path) {
        // Resolves application URL paths
    }
}
```

**Responsibilities:**
- Initializes all major components
- Manages configuration
- Provides URL resolution helpers
- Coordinates tab manager and pages

#### TabManager

Handles tab switching and page lifecycle.

```javascript
class TabManager {
    constructor(config) {
        this.tabs = [];
        this.pages = new Map();
    }
    
    registerTab(name, label, template) {
        // Register a tab
    }
    
    switchTab(name) {
        // Switch to tab, clone template, initialize page
    }
}
```

#### ToastNotifier

Displays temporary notification messages.

```javascript
class ToastNotifier {
    show(message, type = 'info', duration = 3000) {
        // Create toast element
        // Auto-dismiss after duration
    }
    
    success(message) { this.show(message, 'success'); }
    error(message) { this.show(message, 'error'); }
    warning(message) { this.show(message, 'warning'); }
}
```

### Page Controllers

Each tab has a corresponding page controller.

#### LineItemsPage

```javascript
class LineItemsPage {
    constructor(lexBridge) {
        this.lexBridge = lexBridge;
        this.filterForm = null;
        this.sendInvoiceButton = null;
        this.customerSearchController = null;
        this.initialize();
    }
    
    async handleFilterSubmit(form) {
        // Fetch line items from API with filters
        // Populate table
    }
    
    async handleCreateInvoiceFromSelection() {
        // Get selected checkboxes
        // POST to /api/invoices/create
        // Show toast notification
    }
    
    updateSendInvoiceButtonState() {
        // Enable/disable button based on selection
    }
}
```

#### InvoicesPage

```javascript
class InvoicesPage {
    constructor(lexBridge) {
        this.lexBridge = lexBridge;
        this.init();
    }
    
    async loadInvoices(page = 0) {
        // Fetch invoices from /api/invoices
        // Render table
    }
    
    async transferInvoice(invoiceId) {
        // POST to /api/invoices/transfer
        // Update status
    }
}
```

### Search Controllers

Handle customer and article search with caching.

```javascript
class CustomerSearchController {
    constructor() {
        this.cache = new Map();
        this.debounceTimer = null;
        this.setupInputListener();
    }
    
    setupInputListener() {
        const input = document.querySelector('[name="customer_search"]');
        input?.addEventListener('input', (e) => {
            this.debounceSearch(e.target.value);
        });
    }
    
    async search(query) {
        if (this.cache.has(query)) {
            return this.cache.get(query);
        }
        
        const response = await fetch(
            LexBridge.resolveApiUrl(`/customers/search?q=${encodeURIComponent(query)}`)
        );
        const data = await response.json();
        
        this.cache.set(query, data.customers);
        return data.customers;
    }
}
```

### Event Flow

```
User Action (click button/submit form)
        ↓
Page Controller Method
        ↓
Fetch API Endpoint
        ↓
Update DOM / Render
        ↓
Show Toast Notification
```

### Example: Creating Invoice

1. User checks line item checkboxes
2. User clicks "Rechn. Erstellen" button
3. LineItemsPage.handleCreateInvoiceFromSelection() runs
4. Collects selected line item IDs
5. POSTs to /api/invoices/create with IDs
6. API creates invoice and returns response
7. Toast shows success/error message
8. Table refreshes with new invoice

---

## Development Workflow

### Setting Up Development Environment

1. **Clone Repository:**
   ```bash
   git clone <repository-url> lex-bridge
   cd lex-bridge
   ```

2. **Install Dependencies:**
   ```bash
   composer install
   ```

3. **Configure for Development:**
   ```bash
   # Edit config.php
   # Set APP_ENV=development
   # Configure development database
   ```

4. **Create Development Database:**
   ```bash
   mysql -u root -p < database/migrations/001_create_articles.sql
   # ... apply all migrations
   ```

5. **Run Development Server:**
   ```bash
   # Using PHP built-in server
   php -S localhost:8000
   
   # Or use Apache with XAMPP
   # Place in htdocs/lex-bridge
   ```

### Directory Structure for Development

```
lex-bridge/
├── .git/                 # Version control
├── .env                  # Local environment variables
├── config.php            # Configuration (modified for dev)
└── ... (rest of project)
```

### Code Style Guidelines

- **PHP:** PSR-12 coding standard
- **JavaScript:** ES6+ with 'use strict'
- **SQL:** Use prepared statements, ALWAYS
- **Naming:**
  - Classes: PascalCase
  - Methods/Functions: camelCase
  - Constants: UPPER_SNAKE_CASE
  - Variables: camelCase

### Version Control

**Branching Strategy:**
```
main                     # Production
  ├─ develop            # Integration branch
  │   ├─ feature/...    # Feature branches
  │   └─ bugfix/...     # Bug fix branches
  └─ hotfix/...         # Production fixes
```

**Commit Messages:**
```
[TYPE] Short description

Detailed explanation if needed.

Fixes #123
```

Types: `feat`, `fix`, `docs`, `refactor`, `test`, `perf`

---

## Adding New Features

### Adding a New API Endpoint

1. **Create the Route in ApiKernel:**

```php
// api/ApiKernel.php
private function getCustomReportRouteRegistration(): void
{
    $this->router->get('/custom-report', function() {
        $controller = ControllerFactory::makeCustomReportController();
        $filters = isset($_GET['filters']) ? json_decode($_GET['filters'], true) : [];
        return $controller->getReport($filters);
    });
}

// Call in __construct():
$this->getCustomReportRouteRegistration();
```

2. **Create Service & Repository:**

```php
// src/Services/CustomReportService.php
namespace Luxullus\LexBridge\Services;

class CustomReportService
{
    private CustomReportRepository $repo;
    
    public function __construct(CustomReportRepository $repo)
    {
        $this->repo = $repo;
    }
    
    public function getReport(array $filters): array
    {
        // Business logic
        return $this->repo->findWithFilters($filters);
    }
}

// src/Repositories/CustomReportRepository.php
class CustomReportRepository
{
    public function findWithFilters(array $filters): array
    {
        // Database queries
    }
}
```

3. **Create Controller:**

```php
// src/Controllers/CustomReportController.php
class CustomReportController
{
    private CustomReportService $service;
    
    public function __construct(CustomReportService $service)
    {
        $this->service = $service;
    }
    
    public function getReport(array $filters): array
    {
        $data = $this->service->getReport($filters);
        return [
            'isSuccess' => true,
            'data' => $data
        ];
    }
}
```

4. **Update Factory:**

```php
// src/Controllers/ControllerFactory.php
public static function makeCustomReportController(): CustomReportController
{
    $repo = new CustomReportRepository();
    $service = new CustomReportService($repo);
    return new CustomReportController($service);
}
```

5. **Add Frontend Page (if needed):**

```javascript
// public/js/pages/custom-report.js
class CustomReportPage {
    constructor(lexBridge) {
        this.lexBridge = lexBridge;
        this.initialize();
    }
    
    async loadReport(filters) {
        const response = await fetch(
            LexBridge.resolveApiUrl('/custom-report?filters=' + encodeURIComponent(JSON.stringify(filters)))
        );
        const data = await response.json();
        // Render report
    }
}
```

### Adding a New Database Table

1. **Create Migration File:**

```sql
-- database/migrations/013_create_custom_table.sql
CREATE TABLE IF NOT EXISTS `custom_table` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE,
    
    KEY `idx_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

2. **Add to config.php:**

```php
$tableNames = [
    // ... existing
    'custom_table' => 'custom_table',  // or custom name
];
```

3. **Create Model:**

```php
// src/Models/CustomEntity.php
class CustomEntity
{
    public ?int $id = null;
    public string $name;
    public ?string $description = null;
    
    public static function fromDatabase(array $row): self
    {
        $entity = new self();
        $entity->id = $row['id'] ?? null;
        $entity->name = $row['name'];
        $entity->description = $row['description'];
        return $entity;
    }
}
```

4. **Create Repository:**

```php
// src/Repositories/CustomRepository.php
class CustomRepository
{
    private PDO $db;
    private string $table;
    
    public function __construct()
    {
        $this->db = Database::getConnection();
        $this->table = lexbridge_table('custom_table');
    }
    
    public function findAll(): array { ... }
    public function findById(int $id): ?CustomEntity { ... }
    public function save(CustomEntity $entity): void { ... }
}
```

5. **Apply Migration:**

```bash
mysql -u root -p < database/migrations/013_create_custom_table.sql
```

---

## Testing & Debugging

### Running Tests

```bash
# Run all tests
./vendor/bin/phpunit

# Run specific test class
./vendor/bin/phpunit tests/Services/InvoiceServiceTest.php

# Run with coverage report
./vendor/bin/phpunit --coverage-html coverage/
```

### Test File Structure

```php
namespace Tests\Services;

use PHPUnit\Framework\TestCase;
use Luxullus\LexBridge\Services\InvoiceService;

class InvoiceServiceTest extends TestCase
{
    private InvoiceService $service;
    
    protected function setUp(): void
    {
        // Test setup
        $this->service = new InvoiceService(...);
    }
    
    public function testGetInvoices(): void
    {
        $invoices = $this->service->getInvoices();
        $this->assertIsArray($invoices);
    }
    
    public function testTransferInvoice(): void
    {
        $result = $this->service->transferInvoiceById('test-uuid');
        $this->assertTrue($result['response']->isSuccess());
    }
}
```

### Browser Debugging

**Open Developer Tools:** Press F12

**Check Network Tab:**
- Monitor API requests
- Verify request/response payloads
- Check for HTTP errors

**Check Console Tab:**
- Review JavaScript errors
- Check LexBridge.config
- Log debug messages:
  ```javascript
  console.log('Debug:', window.LexBridgeApp.config);
  ```

### PHP Debugging

**Enable Error Logging:**

```php
// bootstrap.php
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/logs/php-errors.log');
```

**Add Debug Logging:**

```php
error_log('Invoice created: ' . $invoice->id);
error_log('API Response: ' . print_r($response, true));
```

**API Debug Endpoint:**

```
GET /api/debug.php
// Returns:
{
  "config": { ... },
  "database": { "connected": true },
  "lexware_api": { "status": "... " }
}
```

---

## Deployment

### Pre-Deployment Checklist

- [ ] All tests pass
- [ ] No console errors
- [ ] Database migrations applied
- [ ] Configuration for production environment
- [ ] SSL certificate installed
- [ ] API key secured and configured
- [ ] Database backups created
- [ ] Assets minified/optimized

### Production Deployment Steps

1. **Prepare Environment:**
   ```bash
   # Pull latest code
   git fetch origin
   git checkout main  # or release branch
   
   # Install dependencies
   composer install --no-dev
   
   # Clear caches
   composer dump-autoload -o
   ```

2. **Configure for Production:**
   ```php
   // config.php
   $appEnv = 'production';
   // Verify all production credentials
   ```

3. **Apply Database Migrations:**
   ```bash
   # Test on copy first!
   mysql -u prod_user -p prod_db < migrations/...
   ```

4. **Set Permissions:**
   ```bash
   chmod 755 lex-bridge/
   chmod 755 lex-bridge/public/
   # If using separate logs directory:
   chmod 775 lex-bridge/logs/
   ```

5. **Verify Installation:**
   - Test all major features
   - Check API endpoints
   - Verify Lexware integration
   - Monitor logs for errors

### Rollback Procedure

```bash
# Revert code to previous version
git revert <commit-hash>
git push origin main

# Restore database from backup
mysqldump -u user -p old_backup.sql | mysql -u user -p lexbridge

# Clear any caches
composer dump-autoload
```

### Monitoring

**Monitor These Metrics:**
- Application error logs
- API response times
- Database query performance
- Lexware API connectivity
- Invoice transmission success rate

**Log Locations:**
```
/var/log/apache2/error.log      # Apache errors
/var/log/php-errors.log         # PHP errors
/var/log/mysql/error.log        # MySQL errors
lex-bridge/logs/                # App logs (if configured)
```

---

## Performance Optimization

### Database Optimization

**Add Indexes for Frequent Queries:**
```sql
-- Already indexed in schema, but monitor:
EXPLAIN SELECT * FROM invoices WHERE status = 'transmitted' AND created_at > '2024-01-01';
```

**Batch Operations:**
```php
// Slow - multiple queries
foreach ($invoices as $invoice) {
    $this->repository->updateStatus($invoice->id, 'transmitted');
}

// Fast - single query
$ids = array_map(fn($i) => $i->id, $invoices);
$this->repository->updateStatusBatch($ids, 'transmitted');
```

### API Optimization

**Pagination:**
```php
// Articles sync endpoint supports pagination
POST /api/articles/sync?page=0
POST /api/articles/sync?page=1  // Next page
```

**Caching:**
```javascript
// Frontend caches search results for 5 minutes
if (this.cache.has(query)) {
    return this.cache.get(query);
}
```

### Frontend Optimization

**Lazy Loading:**
- Tab content is only loaded when tab is clicked
- Reduces initial page load time

**Event Delegation:**
- Use document-level listeners for dynamically added elements
- Reduces event listener overhead

---

## Helper Functions

### Global Helpers

```php
// Defined in bootstrap.php

/**
 * Get base path of application
 */
function lexbridge_base_path(): string
{
    // Returns path like "/lex-bridge/" or "/"
}

/**
 * Get base URI for URLs
 */
function lexbridge_base_uri(): string
{
    // Returns full URI with domain
}

/**
 * Get mapped table name
 */
function lexbridge_table(string $key): string
{
    global $tableNames;
    return $tableNames[$key] ?? $key;
}
```

---

## Troubleshooting for Developers

### "Class not found" Error

```
Error: Class 'Luxullus\LexBridge\...' not found
```

**Solution:**
```bash
composer dump-autoload
```

### Database Connection Error

```
Error: SQLSTATE[HY000]: General error: ...
```

**Check:**
1. Database credentials in config.php
2. MySQL service is running
3. Database exists and is accessible

### Lexware API Errors

```json
{
  "error": "Unauthorized",
  "statusCode": 401
}
```

**Check:**
1. API_KEY is valid in config.php
2. API_BASE_URL is correct
3. API key has proper permissions in Lexware

### CORS Issues (Frontend)

```
Cross-Origin Request Blocked
```

**Check:**
1. `allowedOrigins` in config.php includes your domain
2. Frontend is accessing correct API URL
3. Browser sends OPTIONS preflight request correctly

---

## Contributing Guidelines

1. Create feature branch: `git checkout -b feature/description`
2. Make changes following code style
3. Write/update tests
4. Add comments for complex logic
5. Create pull request with description
6. Request code review
7. Merge to develop, then to main for release

---

## Resources & References

- [Lexware API Documentation](https://www.lexoffice.de/api-docs/)
- [PHP PDO Documentation](https://www.php.net/manual/en/book.pdo.php)
- [JavaScript Fetch API](https://developer.mozilla.org/en-US/docs/Web/API/Fetch_API)
- [MySQL Documentation](https://dev.mysql.com/doc/)
- [PSR-12 Code Style](https://www.php-fig.org/psr/psr-12/)

---

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0.0 | Feb 2024 | Initial release |

---

**Last Updated:** February 2026  
**Maintained By:** LexBridge Development Team
