# Entity Relationship Diagram (ERD) — SEMS

## Overview

The Sales and Expense Monitoring System (SEMS) uses a relational database
with **6 tables** and **4 foreign key relationships**. The design follows
basic normalization (1NF, 2NF, 3NF) — no repeating groups, no partial
dependencies, no transitive dependencies.

## Text-Based ERD

```
┌─────────────────────────────────────────────────────────────────────────┐
│                              USERS                                     │
├─────────────────────────────────────────────────────────────────────────┤
│  PK  id            INT  AUTO_INCREMENT  (Primary Key)                   │
│      username      VARCHAR(50)   UNIQUE  NOT NULL                       │
│      password      VARCHAR(255)  NOT NULL  (bcrypt hash)                │
│      role          ENUM('admin','staff')  DEFAULT 'staff'               │
│      full_name     VARCHAR(100)  NOT NULL                               │
│      created_at    TIMESTAMP  DEFAULT CURRENT_TIMESTAMP                 │
└─────────────────────────────────────────────────────────────────────────┘
        │
        │ (user_id — who recorded the transaction)
        │
        ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                              SALES                                      │
├─────────────────────────────────────────────────────────────────────────┤
│  PK  id            INT  AUTO_INCREMENT  (Primary Key)                   │
│      sale_date     DATETIME  NOT NULL                                   │
│      total_amount  DECIMAL(10,2)  NOT NULL  (sum of line item subtotals)│
│  FK  user_id       INT  NULL  → users.id  (ON DELETE SET NULL)          │
└─────────────────────────────────────────────────────────────────────────┘
        │
        │ (sale_id — one sale has many line items)
        │
        ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                           SALE_ITEMS                                    │
├─────────────────────────────────────────────────────────────────────────┤
│  PK  id            INT  AUTO_INCREMENT  (Primary Key)                   │
│  FK  sale_id       INT  NOT NULL  → sales.id  (ON DELETE CASCADE)       │
│  FK  product_id    INT  NOT NULL  → products.id                          │
│      quantity      INT  NOT NULL                                         │
│      unit_price    DECIMAL(10,2)  NOT NULL                               │
│      subtotal      DECIMAL(10,2)  NOT NULL  (quantity × unit_price)     │
└─────────────────────────────────────────────────────────────────────────┘
        │
        │ (product_id — which product was sold)
        │
        ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                             PRODUCTS                                    │
├─────────────────────────────────────────────────────────────────────────┤
│  PK  id            INT  AUTO_INCREMENT  (Primary Key)                   │
│      name          VARCHAR(100)  NOT NULL                               │
│      price         DECIMAL(10,2)  NOT NULL                             │
│      stock_qty     INT  NOT NULL  DEFAULT 0                             │
└─────────────────────────────────────────────────────────────────────────┘


┌─────────────────────────────────────────────────────────────────────────┐
│                      EXPENSE_CATEGORIES                               │
├─────────────────────────────────────────────────────────────────────────┤
│  PK  id            INT  AUTO_INCREMENT  (Primary Key)                   │
│      name          VARCHAR(100)  UNIQUE  NOT NULL                       │
└─────────────────────────────────────────────────────────────────────────┘
        │
        │ (category_id — what type of expense)
        │
        ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                            EXPENSES                                     │
├─────────────────────────────────────────────────────────────────────────┤
│  PK  id            INT  AUTO_INCREMENT  (Primary Key)                   │
│  FK  category_id   INT  NOT NULL  → expense_categories.id               │
│      description   VARCHAR(255)  NOT NULL                               │
│      amount        DECIMAL(10,2)  NOT NULL                               │
│      expense_date  DATETIME  NOT NULL                                   │
│  FK  user_id       INT  NULL  → users.id  (ON DELETE SET NULL)          │
└─────────────────────────────────────────────────────────────────────────┘
```

## Relationship Summary

| Relationship | Cardinality | Foreign Key | On Delete |
|---|---|---|---|
| users → sales | One-to-Many | sales.user_id → users.id | SET NULL |
| sales → sale_items | One-to-Many | sale_items.sale_id → sales.id | CASCADE |
| products → sale_items | One-to-Many | sale_items.product_id → products.id | (default RESTRICT) |
| expense_categories → expenses | One-to-Many | expenses.category_id → expense_categories.id | (default RESTRICT) |
| users → expenses | One-to-Many | expenses.user_id → users.id | SET NULL |

## Design Notes

- **users.role** uses `ENUM('admin', 'staff')` — the two user types required
  by the project brief. The role is set server-side only; the public signup
  form always creates `staff` accounts.
- **users.password** stores bcrypt hashes (`$2b$10$...`), never plain text.
- **sales.total_amount** is a derived value (sum of `sale_items.subtotal`).
  It is computed server-side from authoritative database prices, never
  trusted from client input.
- **sale_items** is a junction table that resolves the many-to-many
  relationship between sales and products, while also storing the
  per-line quantity, unit price, and subtotal at the time of sale.
- **expense_categories** is a lookup table — expenses reference a category
  by ID rather than storing free-text category names, preventing
  duplication and typos.
- **user_id** on `sales` and `expenses` is nullable with `ON DELETE SET NULL`,
  so deleting a user does not destroy their transaction history.
- **sale_items.sale_id** uses `ON DELETE CASCADE` so voiding a sale
  automatically removes its line items.

## Visual ERD (draw.io)

A visual version of this diagram is available in `ERD.drawio`.
Import it into [draw.io](https://draw.io) (File → Import From → Device).
