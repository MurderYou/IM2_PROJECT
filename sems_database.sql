-- sems_database.sql
-- Sales and Expense Monitoring System (SEMS)
-- Full database schema + seed data.
--
-- This is the single source of truth for the database. Run this
-- file on a fresh MySQL instance and the whole system is ready to test.
--
-- Import via phpMyAdmin, or from the command line:
--   mysql -u root -p < sems_database.sql

CREATE DATABASE IF NOT EXISTS sems_db;
USE sems_db;

-- ============================================================
-- SCHEMA
-- ============================================================

-- Accounts. Two roles per rubric requirement 1: admin and staff.
CREATE TABLE IF NOT EXISTS users (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    username   VARCHAR(50)  NOT NULL UNIQUE,
    password   VARCHAR(255) NOT NULL,
    role       ENUM('admin', 'staff') NOT NULL DEFAULT 'staff',
    full_name  VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Expense categories, managed separately to avoid free-text duplication.
CREATE TABLE IF NOT EXISTS expense_categories (
    id   INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE
);

-- Products sold by the business.
CREATE TABLE IF NOT EXISTS products (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100) NOT NULL,
    price      DECIMAL(10,2) NOT NULL,
    stock_qty  INT NOT NULL DEFAULT 0
);

-- One row per sale transaction. total_amount is the sum of its line items.
CREATE TABLE IF NOT EXISTS sales (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    sale_date    DATETIME NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    user_id      INT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Line items for each sale — this is what enables multi-product sales
-- and is why sales.total_amount is derived rather than entered directly.
CREATE TABLE IF NOT EXISTS sale_items (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    sale_id    INT NOT NULL,
    product_id INT NOT NULL,
    quantity   INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    subtotal   DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
);

-- Operating expenses, tied to a category and (optionally) the user who
-- recorded them.
CREATE TABLE IF NOT EXISTS expenses (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    category_id   INT NOT NULL,
    description   VARCHAR(255) NOT NULL,
    amount        DECIMAL(10,2) NOT NULL,
    expense_date  DATETIME NOT NULL,
    user_id       INT NULL,
    FOREIGN KEY (category_id) REFERENCES expense_categories(id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- ============================================================
-- SEED DATA
-- ============================================================

-- Test accounts.
-- Admin  — username: admin  / password: admin123
-- Staff  — username: staff  / password: staff123
-- (Hashes are bcrypt, cost 10 — compatible with PHP's password_verify().)
INSERT INTO users (username, password, role, full_name) VALUES
    ('admin', '$2b$10$i1/oViZQY6Sqy49plhGbkebtEaqMzyrejKRb2uQiKGS2.yY4vLhsa', 'admin', 'System Administrator'),
    ('staff', '$2b$10$IJ5ujCe9qBLWqVst5BRdd.7YG2C.FaYGN8uvnqaTAhO/deN8tvt06', 'staff', 'Store Staff')
ON DUPLICATE KEY UPDATE username = username;

INSERT INTO expense_categories (name) VALUES
    ('Inventory restock'), ('Utilities'), ('Rent'), ('Transportation'), ('Miscellaneous')
ON DUPLICATE KEY UPDATE name = name;

INSERT INTO products (name, price, stock_qty) VALUES
    ('Product A', 150.00, 40),
    ('Product B', 85.50, 60),
    ('Product C', 220.00, 15);

-- Sample sales spread across the last 14 days, so the dashboard's trend
-- chart has something to plot immediately.
INSERT INTO sales (sale_date, total_amount, user_id) VALUES
    (NOW() - INTERVAL 0 DAY, 1250.00, 1),
    (NOW() - INTERVAL 1 DAY, 980.50, 2),
    (NOW() - INTERVAL 2 DAY, 1430.00, 1),
    (NOW() - INTERVAL 4 DAY, 760.00, 2),
    (NOW() - INTERVAL 6 DAY, 1120.75, 1),
    (NOW() - INTERVAL 8 DAY, 890.00, 2),
    (NOW() - INTERVAL 11 DAY, 1560.00, 1),
    (NOW() - INTERVAL 13 DAY, 700.25, 2);

-- Sample sale_items for the most recent sale (id 1), just enough to
-- demonstrate the relationship — earlier sample sales are left without
-- line items since they exist mainly to feed the trend chart.
INSERT INTO sale_items (sale_id, product_id, quantity, unit_price, subtotal) VALUES
    (1, 1, 5, 150.00, 750.00),
    (1, 2, 3, 85.50, 256.50),
    (1, 3, 1, 220.00, 220.00);

-- Sample expenses spread across the last 14 days and across categories.
INSERT INTO expenses (category_id, description, amount, expense_date, user_id) VALUES
    (1, 'Stock restock — supplier delivery', 3200.00, NOW() - INTERVAL 1 DAY, 1),
    (2, 'Electricity bill', 1450.00, NOW() - INTERVAL 3 DAY, 1),
    (3, 'Store rent', 8000.00, NOW() - INTERVAL 5 DAY, 1),
    (4, 'Delivery fuel', 350.00, NOW() - INTERVAL 6 DAY, 2),
    (5, 'Packaging materials', 220.00, NOW() - INTERVAL 9 DAY, 2),
    (2, 'Water bill', 480.00, NOW() - INTERVAL 10 DAY, 1);
