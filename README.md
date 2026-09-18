# Sales and Expense Monitoring System (SEMS)

A web-based system that allows small business owners to record sales,
operating expenses, and other financial transactions, and to view
summaries that help them understand daily, weekly, and monthly business
performance.

---

## Table of Contents

1. [Overview](#overview)
2. [Features](#features)
3. [Technology Stack](#technology-stack)
4. [Database Schema](#database-schema)
5. [Installation](#installation)
6. [User Guide](#user-guide)
7. [Default Accounts](#default-accounts)
8. [Security Features](#security-features)
9. [File Structure](#file-structure)
10. [Requirements Compliance](#requirements-compliance)

---

## Overview

SEMS helps small business owners track their financial health in one
place. Instead of scattered spreadsheets or paper receipts, the system
provides a single interface to record sales (with multi-line-item
invoices), log operating expenses by category, and generate reports
that show profit estimation at a glance.

The system enforces role-based access: administrators can manage user
accounts and view all data, while staff can record transactions and
view reports but cannot manage users.

---

## Features

| Feature | Description |
|---|---|
| **Sales recording** | Record multi-product sales with automatic stock deduction and total calculation |
| **Expense recording** | Log expenses with category, description, amount, and date |
| **Expense categories** | Manage expense categories (add, delete with protection) |
| **Product management** | CRUD for products with stock tracking and low-stock alerts |
| **Daily reports** | Dashboard with today's sales/expenses and 14-day trend chart |
| **Monthly reports** | Reports page with date range presets and custom ranges |
| **Profit estimation** | Net profit = total sales − total expenses, shown on dashboard and reports |
| **Dashboard** | Summary cards, trend line chart, expense-by-category doughnut chart, recent activity feed |
| **Printable reports** | Browser-printable reports with print-specific CSS |
| **User management** | Admin-only user creation, editing, and deletion with safety guards |
| **Search & filtering** | Filter expenses by category and date range; filter reports by date presets |

---

## Technology Stack

| Layer | Technology |
|---|---|
| **Backend** | PHP 8+ (PDO, bcrypt, sessions) |
| **Database** | MySQL (InnoDB engine, foreign keys) |
| **Frontend** | HTML5, CSS3, Bootstrap 5.3, JavaScript (Chart.js for charts) |
| **Security** | Prepared statements, bcrypt password hashing, session regeneration |

---

## Database Schema

The database (`sems_db`) contains **6 tables** with proper primary keys,
foreign keys, and normalization (1NF, 2NF, 3NF).

### Tables

| Table | Purpose |
|---|---|
| `users` | System accounts (admin/staff) with bcrypt-hashed passwords |
| `products` | Product catalog with price and stock quantity |
| `sales` | Sale transactions (header) with total amount and recording user |
| `sale_items` | Line items for each sale (product, quantity, unit price, subtotal) |
| `expenses` | Operating expenses linked to a category and recording user |
| `expense_categories` | Lookup table for expense types |

### Relationships

```
users (1) ────< (many) sales
users (1) ────< (many) expenses
sales (1) ────< (many) sale_items >─── (1) products
expense_categories (1) ────< (many) expenses
```

- `sales.user_id` → `users.id` (ON DELETE SET NULL)
- `sale_items.sale_id` → `sales.id` (ON DELETE CASCADE)
- `sale_items.product_id` → `products.id` (RESTRICT)
- `expenses.category_id` → `expense_categories.id` (RESTRICT)
- `expenses.user_id` → `users.id` (ON DELETE SET NULL)

### ERD

- **Text-based ERD**: See [`ERD.md`](ERD.md)
- **Visual ERD**: See [`ERD.drawio`](ERD.drawio) (import into draw.io)
- **SQL schema**: See [`sems_database.sql`](sems_database.sql)

---

## Installation

### Prerequisites

- XAMPP (or any LAMP stack) with PHP 8+ and MySQL
- Web browser

### Steps

1. **Start Apache and MySQL** in XAMPP Control Panel.

2. **Create the database** (if not already present):
   ```bash
   mysql -u root -p < sems_database.sql
   ```
   Or import via phpMyAdmin:
   - Open `http://localhost/phpmyadmin`
   - Click "New" → enter `sems_db` → "Create"
   - Click "Import" → choose `sems_database.sql` → "Go"

3. **Verify the database connection** in `db.php`:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'sems_db');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   ```
   Adjust credentials if your MySQL setup differs.

4. **Access the system**:
   - Open `http://localhost/IM2_PROJECT/login.php`
   - Log in with the default accounts below.

---

## User Guide

### Login

Enter your username and password on the login page. If you don't have an
account yet, click "Create an account" to sign up as a **staff** user.
(Administrator accounts are created through the admin user management
page.)

### Dashboard

The dashboard provides an at-a-glance view of business performance:
- **Today's sales** and **Today's expenses** summary cards
- **This month's sales** and **This month's expenses** summary cards
- **14-day trend chart** showing sales vs. expenses over time
- **Expenses by category** doughnut chart (current month)
- **Recent activity** table showing the 6 most recent sales and expenses

### Products

- **Add a product**: Enter name, price, and starting stock quantity.
- **Edit a product**: Click "Edit" to change name, price, or stock.
- **Restock**: Use the "+ Add" form in the catalog table to add units
  to existing stock without overwriting the current count.
- **Delete a product**: Click "Delete" (blocked if the product appears
  in past sales).
- **Low stock**: Products with 10 or fewer units are highlighted in red.

### Sales

- **Record a sale**: Select products from the dropdown, enter quantities,
  and click "Save sale". The system automatically:
  - Looks up the authoritative price from the database
  - Calculates subtotals and grand total
  - Checks stock availability (rejects if insufficient)
  - Deducts stock from inventory
  - Saves everything in a single database transaction
- **Edit a sale**: Click "Edit" to modify line items. The system
  restores the original stock, re-validates, and re-deducts.
- **Void a sale**: Click "Void" to delete the sale and restore stock.

### Expenses

- **Record an expense**: Select a category, enter description, amount,
  and date, then click "Save expense".
- **Manage categories**: Add new categories or remove existing ones
  (removal is blocked if expenses use the category).
- **Filter**: Use the category dropdown and date range pickers to
  filter the expense history table.
- **Edit/Delete**: Click "Edit" to modify, or "Delete" to remove an
  expense.

### Reports

- **Date presets**: Click "Today", "This week", "This month", or
  "Custom range" for quick filtering.
- **Custom range**: Select specific "From" and "To" dates.
- **Report contents**:
  - Total sales, total expenses, and estimated net profit
  - Detailed sales list (date, recorded by, item count, total)
  - Detailed expenses list (date, category, description, amount)
  - Expense breakdown by category with percentages
- **Print**: Click "Print report" to print the report (sidebar and
  filters are automatically hidden in print mode).

### User Accounts (Admin only)

- **Create an account**: Enter full name, username, select role
  (admin or staff), and set a password.
- **Edit an account**: Modify name, username, role, or password.
  Leave the password field blank to keep the current password.
- **Delete an account**: Click "Delete" (you cannot delete your own
  account, and the last remaining admin cannot be deleted).
- The "User accounts" link only appears in the sidebar for admin users.

---

## Default Accounts

| Username | Password | Role |
|---|---|---|
| `admin` | `admin123` | Administrator |
| `staff` | `staff123` | Staff |

> **Important**: Change these default passwords after first login.

---

## Security Features

| Feature | Implementation |
|---|---|
| **Password hashing** | bcrypt (`password_hash` with `PASSWORD_BCRYPT`, cost 10) |
| **SQL injection prevention** | All queries use PDO prepared statements with named parameters |
| **Session fixation prevention** | `session_regenerate_id(true)` on successful login |
| **Role-based access control** | Admin-only pages check `$_SESSION['role']` on both the page and the process script |
| **Privilege escalation prevention** | Signup form hardcodes `role = 'staff'`; role is never accepted from user input |
| **Self-lockout prevention** | Cannot delete own account; cannot demote self from admin; cannot remove last admin |
| **Input validation** | Server-side validation on every form (required fields, format checks, duplicate detection, positive amounts, valid dates) |
| **Error handling** | All database operations wrapped in try/catch; raw errors logged server-side, user-friendly messages shown to users |
| **Stock integrity** | Sales use `SELECT ... FOR UPDATE` row locking and database transactions to prevent race conditions and overselling |
| **Price integrity** | Unit prices are always looked up from the database, never trusted from client-submitted form values |

---

## File Structure

```
IM2_PROJECT/
├── auth.php              # Login form processing (password_verify, session)
├── db.php                # PDO database connection
├── login.php             # Login page (UI)
├── logout.php            # Session destruction
├── signup.php            # Staff registration page (UI)
├── signup_process.php    # Staff registration processing
├── dashboard.php         # Dashboard with charts and summaries
├── sales.php             # Sales recording page (form + history)
├── sales_process.php     # Sales create/edit/void processing (transactions)
├── sale_edit.php         # Edit sale page (prefilled form)
├── expenses.php          # Expenses page (form + categories + history + filters)
├── expenses_process.php  # Expense create/update/delete + category management
├── products.php          # Products page (form + catalog with restock)
├── product_process.php   # Product create/update/delete/restock processing
├── users.php             # User management page (admin only)
├── users_process.php     # User create/update/delete processing (admin only)
├── reports.php           # Reports page (date range, summaries, printable)
├── style.css             # Shared CSS for all authenticated pages
├── sems_database.sql     # Database schema + seed data
├── ERD.md                # Text-based Entity Relationship Diagram
├── ERD.drawio            # Visual ERD (import into draw.io)
├── README.md             # This file
└── includes/
    └── sidebar.php       # Shared sidebar navigation
```

---

## Requirements Compliance

This system was built to meet all 12 minimum requirements for the
University of Cabuyao College of Computing Studies SDG project:

| # | Requirement | Status |
|---|---|---|
| 1 | User Authentication and Authorization | ✅ Login/logout, admin/staff roles, role-based page access |
| 2 | Database Management | ✅ MySQL, 6 normalized tables, foreign keys, ERD, sample data |
| 3 | CRUD Operations | ✅ Full CRUD on Products, Sales, Expenses, Categories, Users |
| 4 | Transaction Processing | ✅ Sales use DB transactions with stock validation |
| 5 | Search, Filtering & Record Retrieval | ✅ Expense filters, report date presets, sales history |
| 6 | Input Validation | ✅ Server-side + client-side validation on all forms |
| 7 | Error Handling | ✅ try/catch, user-friendly messages, graceful degradation |
| 8 | Dashboard & System Overview | ✅ Summary cards, trend chart, category chart, recent activity |
| 9 | Reports & Data Presentation | ✅ Date-range reports, net profit, printable via browser |
| 10 | Responsive & User-Friendly Interface | ✅ Bootstrap 5, responsive CSS, consistent design |
| 11 | System Security | ✅ bcrypt, prepared statements, session management, RBAC |
| 12 | System Documentation | ✅ This README, ERD.md, ERD.drawio, sems_database.sql |
