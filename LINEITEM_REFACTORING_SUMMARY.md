h# LineItem Layer Refactoring - Separation of Concerns

## Overview

Completed refactoring of LineItemService and LineItemRepository to establish proper separation of concerns between business logic and data access layers, following the same successful pattern applied to Invoice, Article, and Customer layers.

## Changes Made

### LineItemRepository.php (Simplified: Pure Data Access)

**Removed Business Logic:**

- Removed CASE statement calculations for `line_total_net` and `line_total_gross`
- Removed duplicate parameter bindings (`net_amount_calc_check`, `gross_amount_calc_value`, etc.)
- Repository now accepts pre-calculated totals from service layer

**Repository Responsibilities (Data Access Only):**

- ✅ SQL queries (SELECT with JOINs, UPDATE)
- ✅ Parameter binding
- ✅ Raw array returns
- ✅ Database error handling
- ❌ NO business logic calculations
- ❌ NO data validation

**Key Methods:**

- `findLineItems()` - Fetches line items with filters, JOINs customer/invoice data
- `findLineItemById()` - Retrieves single line item by ID
- `updateLineItem()` - Pure UPDATE statement, saves provided data

### LineItemService.php (Enhanced: Business Logic Added)

**Business Logic Added:**

1. **Line Item Not Found Validation**
   - `updateLineItem()` now checks if line item exists before updating
   - Returns structured error response: `{isSuccess: false, error: 'Line item not found'}`

2. **Total Calculation Logic**
   - `calculateLineTotal()` - Calculates `amount * quantity` with proper rounding
   - Preserves existing totals when amount or quantity is null
   - Handles fallback values correctly

3. **Data Sanitization (Already Existed)**
   - `sanitizeString()` - Trims strings, returns empty string for null
   - `sanitizeNullableString()` - Trims strings, preserves null
   - `sanitizeCurrency()` - Uppercases, validates 3-character codes
   - `sanitizeDecimal()` - Normalizes German/US formats, rounds to 2 decimals
   - `sanitizeDateTime()` - Converts to standard YYYY-MM-DD HH:MM:SS format

4. **Data Enrichment**
   - `getLineItems()` maps raw database arrays to enriched API responses
   - Type casting (int, float, string)
   - Field aliasing (`company_name` → `customer_name`)
   - Safe access with `??` operators

**Service Layer Responsibilities:**

- ✅ Input validation (line item existence)
- ✅ Data sanitization (currency, decimals, dates, strings)
- ✅ Business calculations (line totals)
- ✅ Data transformation (arrays → API responses)
- ✅ Error handling with structured responses

### Test Coverage

**LineItemRepositoryTest.php** (6 tests - 100% passing)

Updated for new customer schema:

- Changed `kundenNummer` → `Nummer`
- Changed `company_name` → `Name`
- Updated all INSERT statements to match new schema

Tests now provide pre-calculated totals since calculation moved to service:

- `testUpdateLineItemUpdatesArticleFieldsAndTotals` - Provides `line_total_net`, `line_total_gross` in test data
- `testUpdateLineItemLeavesTotalsWhenAmountsMissing` - Provides existing totals when amounts are null

**Test Coverage:**

- ✅ findLineItems with filters (date range, customer_id)
- ✅ Result limiting (200 items max)
- ✅ findLineItemById (success and not found)
- ✅ updateLineItem persistence
- ✅ Schema compatibility with new customer table structure

**LineItemServiceTest.php** (10 tests - NEW! 100% passing)

Comprehensive business logic testing:

1. **Data Retrieval & Transformation**
   - `testGetLineItems_ReturnsEnrichedArray` - Validates API response structure
   - `testGetLineItems_AppliesFilters` - Tests customer_id filtering

2. **Calculation Logic**
   - `testUpdateLineItem_CalculatesTotalsWhenAmountsProvided` - Tests `amount * quantity`
   - `testUpdateLineItem_PreservesTotalsWhenAmountsNull` - Verifies fallback logic
   - `testUpdateLineItem_PreservesTotalsWhenQuantityMissing` - Handles missing quantity

3. **Validation**
   - `testUpdateLineItem_ReturnsErrorWhenLineItemNotFound` - Tests error handling

4. **Sanitization**
   - `testUpdateLineItem_SanitizesCurrency` - Tests uppercasing (usd → USD)
   - `testUpdateLineItem_SanitizesDecimalValues` - Tests German format (12,50 → 12.5)
   - `testUpdateLineItem_SanitizesDateTimeValues` - Tests date normalization
   - `testUpdateLineItem_HandlesEmptyStringsAsNull` - Tests null coercion

## Architecture Comparison

### Before Refactoring

```
Controller → Service (sanitization only) → Repository (sanitization + SQL + calculations)
```

**Issues:**

- Repository contained business logic (CASE statements for calculations)
- No validation for line item existence
- Tests couldn't test calculation logic separately from data access

### After Refactoring

```
Controller → Service (validation + calculations + sanitization) → Repository (pure SQL)
```

**Benefits:**

- Clear separation: Service = business logic, Repository = data access
- Testable calculation logic in service layer
- Validation before data access operations
- Repository methods are simple, predictable SQL operations

## Test Results

### LineItem Layer: 16/16 tests passing (100%)

- **LineItemRepositoryTest:** 6/6 passing (33 assertions)
- **LineItemServiceTest:** 10/10 passing (37 assertions)

### Total Coverage: 70 assertions across 16 test cases

## API Contracts

### LineItemService::updateLineItem()

**Input:**

```php
updateLineItem(string $lineItemId, array $data): array
```

**Success Response:**

```php
[
    'isSuccess' => true,
    'lineItem' => [/* raw array from repository */]
]
```

**Error Response:**

```php
[
    'isSuccess' => false,
    'error' => 'Line item not found'
]
```

**Calculation Logic:**

- If `net_amount` and `quantity` provided → `line_total_net = net_amount * quantity`
- If `gross_amount` and `quantity` provided → `line_total_gross = gross_amount * quantity`
- If amount or quantity is null → preserve existing totals
- All calculations rounded to 2 decimal places

## Key Improvements

1. **Separation of Concerns**
   - Repository: Pure SQL operations, no business logic
   - Service: All validation, calculation, sanitization

2. **Better Testability**
   - Calculation logic testable without database
   - Repository tests focus on SQL correctness
   - Service tests focus on business rules

3. **Schema Compatibility**
   - Updated all tests for new customer table structure
   - `Nummer` instead of `kundenNummer`
   - `Name` instead of `company_name`

4. **Error Handling**
   - Service validates line item exists before update
   - Structured error responses with `isSuccess` flag

5. **Maintainability**
   - Clear boundaries between layers
   - Each layer has single responsibility
   - Tests document expected behavior

## Migration Notes

### For Existing Code Using LineItemService:

**No breaking changes** - All public API methods remain the same:

- `getLineItems()` - Works exactly as before
- `updateLineItem()` - Same signature, enhanced with existence validation

### For Code Calling Repository Directly:

**Breaking change** - `updateLineItem()` now requires totals:

**Before:**

```php
$repository->updateLineItem('li-123', [
    'net_amount' => 10.0,
    'gross_amount' => 11.9,
    // Totals were calculated in repository
]);
```

**After:**

```php
$repository->updateLineItem('li-123', [
    'net_amount' => 10.0,
    'gross_amount' => 11.9,
    'line_total_net' => 30.0,   // Must be provided
    'line_total_gross' => 35.7, // Must be provided
]);
```

**Recommendation:** Always use `LineItemService` instead of calling repository directly.

## Related Work

This refactoring follows the same patterns successfully applied to:

- ✅ InvoiceService / InvoiceRepository (26 tests passing)
- ✅ ArticleService / ArticleRepository
- ✅ CustomerService / CustomerRepository

All follow the same principle: **Service = Business Logic, Repository = Data Access**
