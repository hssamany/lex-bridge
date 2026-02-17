# LexBridge Management System - User Documentation

## Table of Contents
1. [Getting Started](#getting-started)
2. [System Overview](#system-overview)
3. [Navigation & Tabs](#navigation--tabs)
4. [Line Items Management](#line-items-management)
5. [Invoices Management](#invoices-management)
6. [Orders Management](#orders-management)
7. [Contacts Management](#contacts-management)
8. [Key Features & Buttons](#key-features--buttons)
9. [Tips & Best Practices](#tips--best-practices)
10. [Troubleshooting](#troubleshooting)

---

## Getting Started

### Starting the Application

1. **Ensure Prerequisites:**
   - Apache/XAMPP is running (or your PHP web server)
   - MySQL database is running and accessible
   - Database is initialized with all migrations (see database/migrations/)

2. **Access the Application:**
   - Open your web browser and navigate to: `http://localhost/lex-bridge` (or your configured domain)
   - The application will load the main dashboard with tab-based navigation

3. **First-Time Setup:**
   - The system automatically initializes when you first access it
   - Verify that all data loads properly in each tab
   - If you see error messages, check that your database is properly configured in `config.php`

### System Requirements
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Modern web browser (Chrome, Firefox, Safari, Edge)
- JavaScript enabled in your browser

---

## System Overview

**LexBridge** is a management system designed to synchronize invoice and order data between your local database and the Lexware accounting system. It acts as a bridge for:
- Managing line items (invoice positions)
- Creating and tracking invoices
- Processing orders from Lexware
- Syncing contacts and articles

### Key Concepts

**Line Items:** Individual positions/rows that will make up an invoice (e.g., catering services, articles, quantities)

**Invoices:** Formal documents created from selected line items, ready to be transmitted to Lexware

**Orders:** Purchase orders from Lexware that can be processed to generate line items

**Customers:** Client entities managed in the system

**Articles:** Products/services available for selection when creating line items

---

## Navigation & Tabs

The application uses a tab-based interface at the top of the page. Click on any tab to switch between sections:

### Available Tabs

1. **Line Items** 
   - Manage individual invoice positions
   - Create invoices from selected items
   - Sync articles with Lexware

2. **Invoices**
   - View all created invoices
   - Track transmission status
   - Transfer invoices to Lexware

3. **Orders**
   - Browse orders from Lexware
   - Generate line items from selected orders
   - Track order processing status

4. **Contacts**
   - Manage customer contact information
   - Sync contacts with Lexware

---

## Line Items Management

### What are Line Items?

Line items represent individual positions that will be included in an invoice. Each line item contains:
- Customer name
- Article/description
- Quantity
- Net price
- Gross price (including tax)
- Tax percentage
- Creation date and time

### Accessing Line Items Tab

Click on the **"Line Items"** tab at the top of the page to view all line items.

### Creating Line Items

1. **Find the Filter/Create Section** at the top of the Line Items tab
2. **Select a Customer:**
   - Click in the customer field
   - Type the customer name or number
   - A dropdown will appear with matching customers
   - Click to select

3. **Select an Article:**
   - Click in the article field
   - Type the article name or number
   - A dropdown will appear with matching articles
   - Click to select

4. **Set Quantity:**
   - Enter the number of items/units
   - Price will be calculated automatically based on article pricing

5. **Date Filters (Optional):**
   - Filter line items by creation date range
   - Select "From" and "To" dates if needed

6. **Submit:**
   - Click the form submit button to create and display line items

### Line Items Table

The table displays all line items with these columns:

| Column | Meaning |
|--------|---------|
| ☑ (Checkbox) | Select multiple items to create an invoice |
| **Pos.** | Position/line number |
| **Bezeichnung** | Item name/description |
| **Menge** | Quantity |
| **Netto** | Net price (before tax) |
| **Brutto** | Gross price (including tax) |
| **Steuer %** | Tax percentage applied |
| **Erstellt am** | Date created |
| **Uhrzeit** | Time created |

### Line Items Toolbar Buttons

#### "Rechn. Erstellen" (Create Invoice)
- **Purpose:** Create an invoice from selected line items
- **How to Use:**
  1. Check the checkbox next to each line item you want to include
  2. Click "Rechn. Erstellen" button (appears highlighted when items are selected)
  3. The system will group items by customer and create an invoice
  4. A confirmation message will appear when complete
- **Status:** Button is disabled when no items are selected

#### "Artikeln synchronisieren" (Sync Articles)
- **Purpose:** Download and update the article list from Lexware
- **How to Use:**
  1. Click the "↻ Artikeln synchronisieren" button
  2. The system will fetch the latest articles from Lexware
  3. Articles are cached for 5 minutes to improve performance
  4. Pricing information is also updated
- **Frequency:** Run this weekly or when you add new articles to Lexware

### Select All Checkbox

- Located in the header row of the table
- Check to select all line items on the page
- Uncheck to deselect all
- Individual checkboxes can be toggled independently

### Totals

- **"Gesammt: X"** displays the total count of line items loaded

### Filtering & Searching

**By Date Range:**
- Use "From" and "To" date fields to narrow results
- Leave blank to show all items

**By Customer:**
- Use the customer dropdown in the filter form
- Shows customer name and Lexware number

**Clear Filters:**
- Leave all filter fields empty and submit to show all line items

---

## Invoices Management

### What are Invoices?

Invoices are formal billing documents created from selected line items and ready for transmission to Lexware. They contain:
- Customer information
- All selected line items
- Total amounts (net, gross, tax)
- Invoice date
- Transmission status

### Accessing Invoices Tab

Click on the **"Invoices"** tab to view all invoices.

### Invoices Table

| Column | Meaning |
|--------|---------|
| **Action** | Buttons to transfer the invoice |
| **Customer** | Company name of the customer |
| **Date** | Invoice creation date |
| **Items** | Number of line items in the invoice |
| **Status** | Current state (pending, transmitted, error) |
| **Attempts** | Number of transmission attempts |
| **Total** | Gross total amount in EUR |

### Invoice Buttons

#### "▶" (Transfer/Transmit)
- **Purpose:** Send the invoice to Lexware
- **How to Use:**
  1. Locate the invoice in the table
  2. Click the "▶" button in the Action column
  3. The system will attempt to transmit the invoice
  4. You'll see a confirmation message indicating success or failure
- **What Happens:**
  - Invoice is marked with transmission status
  - Lexware receives the invoice data
  - If it fails, you can try again (retry count increases)

#### "↻ Refresh" (Reload Invoices)
- **Purpose:** Fetch and display the latest invoice list
- **How to Use:**
  1. Click the refresh form button
  2. The list will reload from the database
  3. New invoices created in Line Items tab will appear here

### Invoice Status

**Pending:** Invoice created but not yet transmitted to Lexware

**Transmitted:** Successfully sent to Lexware

**Error:** Transmission failed (check Attempts count for retry history)

### Creating Invoices

Invoices are created from the **Line Items** tab:
1. Select line items by checking their checkboxes
2. Click "Rechn. Erstellen" button
3. The invoice is automatically created and appears in this tab

---

## Orders Management

### What are Orders?

Orders are purchase requisitions or work orders from Lexware. They contain:
- Customer information
- Article/service requirements
- Weekly demand pattern (quantities by day)
- Order date
- Processing status

### Accessing Orders Tab

Click on the **"Orders"** tab to view all orders.

### Orders Table

| Column | Meaning |
|--------|---------|
| ☑ (Checkbox) | Select orders to generate line items |
| **Kunde** | Customer name |
| **Lex-Nr.** | Lexware customer number |
| **KW** | Calendar week |
| **Mo., Di., Mi., Do., Fr.** | Quantities for Monday through Friday |
| **Bestel.dat** | Order date |
| **Artikel-Nr.** | Lexware article number |
| **Verarbt.** | Processing status (yes/no) |

### Filtering Orders

**By Date Range:**
- Use "From" and "To" date fields
- Required: At least specify a date range to load orders
- Filters show orders created/modified within the date range

**By Customer:**
- Use the customer dropdown
- Shows customer name and ID
- Filters to show only that customer's orders

**Apply Filters:**
- Click the form submit button to apply filters and load matching orders

### Orders Toolbar Button

#### "Positionen Erstellen" (Generate Line Items)
- **Purpose:** Convert selected orders into line items
- **How to Use:**
  1. Filter orders to find what you need
  2. Check the checkbox next to each order to process
  3. Click "Positionen Erstellen" button (appears highlighted when orders are selected)
  4. System will create line items based on order quantities
  5. New line items appear in the **Line Items** tab
- **Status:** Button is disabled when no orders are selected

### Processing Status

**Verarbt. (Verarbeitet):** Shows whether an order has been processed
- ✓ = Processed (line items already generated)
- ✗ = Not yet processed

### Select All Checkbox

- Located in the header row
- Check to select all orders on the current page
- Useful for bulk processing

### Totals

- **"Gesammt: X"** displays the total count of orders loaded

---

## Contacts Management

### What are Contacts?

Contacts represent customer entities in your system with their information stored locally and synchronized with Lexware.

### Accessing Contacts Tab

Click on the **"Contacts"** tab to manage customer information.

### Contact Management Features

- **View Contacts:** Browse all customer contact information
- **Sync Contacts:** Synchronize your local customer database with Lexware
- **Manage Customer-Article Relations:** Link specific articles to customers

### Sync Contacts Button

- **Purpose:** Fetch and update customer information from Lexware
- **How to Use:**
  1. Click the sync contacts button/form
  2. System fetches latest customer data from Lexware
  3. Local database is updated with customer information
- **Frequency:** Run weekly or when customer information changes in Lexware

---

## Key Features & Buttons

### Global Features

#### Toast Notifications
- **Location:** Bottom right of the screen
- **Purpose:** Confirm actions and alert you to issues
- **Types:**
  - ✓ **Success:** Action completed successfully (green)
  - ⚠ **Warning:** Something may need attention (yellow)
  - ✗ **Error:** Something went wrong (red)
  - ℹ **Info:** General information (blue)
- **Auto-Dismiss:** Notifications disappear after a few seconds or click X to close

#### Status Bar
- Shows current system status and recent operations
- Updates as you interact with the system

### Search Features

#### Customer Search
- **Available In:** Line Items and Orders tabs
- **How It Works:**
  1. Click in a customer field
  2. Start typing the customer name or ID
  3. A dropdown appears with matching results
  4. Click to select
- **Caching:** Results are cached for 5 minutes to improve performance

#### Article Search
- **Available In:** Line Items tab
- **How It Works:**
  1. Click in an article field
  2. Start typing the article name or number
  3. Dropdown shows matches from your synchronized articles
  4. Click to select
- **Note:** Run "Artikeln synchronisieren" first to populate article list

---

## Tips & Best Practices

### Workflow Recommendations

**1. Daily Operations:**
   - Check the **Orders** tab for new orders from Lexware
   - Process selected orders to generate line items
   - Review line items and correct any issues

**2. Weekly Tasks:**
   - Sync articles and contacts with Lexware
   - Review pending invoices
   - Create and transmit invoices to Lexware

**3. Before Creating Invoices:**
   - Filter and review line items
   - Verify customer and article information is correct
   - Ensure quantities and prices are accurate

**4. After Transmitting Invoices:**
   - Verify transmission status in Invoices tab
   - If transmission fails, check the Attempts count
   - Retry transmission if needed

### Data Management

- **Back Up Your Database:** Regularly backup your MySQL database
- **Keep Articles Updated:** Run article sync weekly to stay current
- **Update Contacts:** Monthly contact sync keeps customer data fresh
- **Archive Old Data:** Consider archiving old orders/invoices periodically

### Performance Tips

- **Use Date Filters:** When loading large datasets, filter by date range
- **Check Select-All Carefully:** Verify selections before bulk operations
- **Clear Browser Cache:** If you see outdated data, clear cache and refresh
- **Avoid Duplicates:** Check existing line items before creating new ones

---

## Troubleshooting

### Common Issues & Solutions

#### "API Configuration Missing" Error
- **Cause:** `config.php` is not properly configured
- **Solution:** 
  1. Check `config.php` for API_KEY and API_BASE_URL
  2. Verify database credentials
  3. Ensure environment is set correctly (development/production)

#### Customer/Article Dropdown Not Working
- **Cause:** Search service not responding or articles not synced
- **Solution:**
  1. Click "Artikeln synchronisieren" to sync articles
  2. Ensure your Lexware API key is valid
  3. Check browser console for error messages (F12)

#### Invoice Not Transmitting
- **Cause:** Network issue, API error, or invalid invoice data
- **Solution:**
  1. Check the invoice data is complete
  2. Verify Lexware API connection
  3. Retry transmission by clicking "▶" button again
  4. Check browser network tab for detailed error (F12 → Network)

#### Orders Not Loading
- **Cause:** Missing date filter or database issue
- **Solution:**
  1. Select both "From" and "To" dates in filter
  2. Ensure orders exist in database for that date range
  3. Click refresh/filter button
  4. Check database connection in `config.php`

#### Line Items Not Appearing After Selection
- **Cause:** Filter settings or customer/article mismatch
- **Solution:**
  1. Clear all filter fields
  2. Click submit to reload all items
  3. Select only one customer and try again
  4. Check browser console for errors (F12)

#### Selections Not Saved
- **Cause:** Browser session timeout or connection lost
- **Solution:**
  1. Refresh the page and log back in if needed
  2. Reselect items and complete action immediately
  3. Check your internet connection

### Getting Help

If you encounter persistent issues:

1. **Check Browser Console:**
   - Press F12 to open Developer Tools
   - Click "Console" tab
   - Look for error messages

2. **Review Server Logs:**
   - Check `api/debug.php` if available for API errors
   - Review PHP error logs from your server

3. **Verify Configuration:**
   - Check `config.php` environment settings
   - Ensure database is running
   - Verify Lexware API key is valid

4. **Database Troubleshooting:**
   - Verify MySQL is running
   - Check database credentials
   - Ensure all migrations have been applied (see `database/migrations/README.md`)

### Tips for Debugging

- **Use Date Filtering:** Narrow your search to find problematic items
- **Process One Item at a Time:** Isolate issues by testing single items first
- **Check Status Messages:** Read toast notifications carefully for clues
- **Clear Cache:** Browser cache can cause stale data issues
- **Verify Source Data:** Check that data exists in Lexware before expecting it to sync

---

## Advanced Information

### Data Synchronization Flow

```
Lexware System
      ↓
API Connection
      ↓
LexBridge Database
      ↓
Web Interface
      ↓
User Actions
      ↓
Line Items/Invoices/Orders
```

### Supported Operations

| Operation | Source | Destination |
|-----------|--------|-------------|
| Read Customers | Lexware | Local DB |
| Read Articles | Lexware | Local DB |
| Read Orders | Lexware | Local DB |
| Write Invoices | Local DB | Lexware |
| Write Contacts | Local DB | Lexware |

### Account & Permissions

- No user authentication system currently implemented
- All users have full access to all features
- Ensure only trusted users have access to the application URL

### Data Retention

- Line items are retained indefinitely
- Invoices are retained for audit trail
- Orders can be archived after processing
- Contact information is updated, not deleted

---

## Summary

**LexBridge** streamlines your workflow by:
- ✓ Centralizing order and invoice management
- ✓ Automating data synchronization with Lexware
- ✓ Providing clear status tracking for all operations
- ✓ Enabling bulk operations for efficiency
- ✓ Maintaining accurate financial records

**Key Remember:**
1. Always verify selections before bulk operations
2. Sync articles and contacts regularly
3. Review line items before creating invoices
4. Use date filters for better performance
5. Check notifications for operation status

For additional support, refer to the system administrator or consult the technical documentation in the repository.

---

**Version:** 1.0.0  
**Last Updated:** February 2026  
**System Name:** LexBridge Management System
