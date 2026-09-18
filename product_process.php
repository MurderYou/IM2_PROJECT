<?php
/**
 * products_process.php
 * Handles create/update/delete/restock for products.
 *
 * "restock" is intentionally a separate, additive action (stock_qty =
 * stock_qty + amount) rather than requiring the user to look up the
 * current total and retype it in the edit form — that's both more
 * convenient and avoids accidentally overwriting stock changed by a
 * sale that happened between page loads.
 */

session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: products.php');
    exit;
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'create':
        handleCreate($pdo);
        break;
    case 'update':
        handleUpdate($pdo);
        break;
    case 'delete':
        handleDelete($pdo);
        break;
    case 'restock':
        handleRestock($pdo);
        break;
    default:
        header('Location: products.php');
        exit;
}

function validateProductInput(): array {
    $name  = trim($_POST['name'] ?? '');
    $price = $_POST['price'] ?? '';
    $stock = $_POST['stock_qty'] ?? '';

    $errors = [];
    if ($name === '') $errors[] = 'a product name';
    if ($price === '' || !is_numeric($price) || (float) $price <= 0) $errors[] = 'a valid price greater than zero';
    if ($stock === '' || !is_numeric($stock) || (int) $stock < 0) $errors[] = 'a valid stock quantity (zero or more)';

    if (!empty($errors)) {
        $_SESSION['products_error'] = 'Please provide ' . implode(', ', $errors) . '.';
        header('Location: products.php');
        exit;
    }

    return ['name' => $name, 'price' => (float) $price, 'stock_qty' => (int) $stock];
}

function handleCreate(PDO $pdo) {
    $data = validateProductInput();

    try {
        $stmt = $pdo->prepare('INSERT INTO products (name, price, stock_qty) VALUES (:name, :price, :stock_qty)');
        $stmt->execute($data);
        $_SESSION['products_success'] = 'Product added.';
        header('Location: products.php');
        exit;
    } catch (PDOException $e) {
        error_log('Product creation failed: ' . $e->getMessage());
        $_SESSION['products_error'] = 'Unable to add the product right now. Please try again.';
        header('Location: products.php');
        exit;
    }
}

function handleUpdate(PDO $pdo) {
    $productId = (int) ($_POST['product_id'] ?? 0);
    $data = validateProductInput();

    if ($productId <= 0) {
        $_SESSION['products_error'] = 'Invalid product reference.';
        header('Location: products.php');
        exit;
    }

    try {
        $stmt = $pdo->prepare('UPDATE products SET name = :name, price = :price, stock_qty = :stock_qty WHERE id = :id');
        $stmt->execute(array_merge($data, ['id' => $productId]));
        $_SESSION['products_success'] = 'Product updated.';
        header('Location: products.php');
        exit;
    } catch (PDOException $e) {
        error_log('Product update failed: ' . $e->getMessage());
        $_SESSION['products_error'] = 'Unable to update the product right now. Please try again.';
        header('Location: products.php');
        exit;
    }
}

function handleDelete(PDO $pdo) {
    $productId = (int) ($_POST['product_id'] ?? 0);

    if ($productId <= 0) {
        $_SESSION['products_error'] = 'Invalid product reference.';
        header('Location: products.php');
        exit;
    }

    try {
        $stmt = $pdo->prepare('DELETE FROM products WHERE id = :id');
        $stmt->execute(['id' => $productId]);
        $_SESSION['products_success'] = 'Product deleted.';
        header('Location: products.php');
        exit;
    } catch (PDOException $e) {
        // Foreign key violation — product is referenced by past sale_items.
        if ($e->getCode() === '23000') {
            $_SESSION['products_error'] = 'Cannot delete this product — it appears in past sales records.';
        } else {
            error_log('Product deletion failed: ' . $e->getMessage());
            $_SESSION['products_error'] = 'Unable to delete this product right now. Please try again.';
        }
        header('Location: products.php');
        exit;
    }
}

function handleRestock(PDO $pdo) {
    $productId = (int) ($_POST['product_id'] ?? 0);
    $qty       = (int) ($_POST['restock_qty'] ?? 0);

    if ($productId <= 0 || $qty <= 0) {
        $_SESSION['products_error'] = 'Enter a restock quantity greater than zero.';
        header('Location: products.php');
        exit;
    }

    try {
        $stmt = $pdo->prepare('UPDATE products SET stock_qty = stock_qty + :qty WHERE id = :id');
        $stmt->execute(['qty' => $qty, 'id' => $productId]);

        if ($stmt->rowCount() === 0) {
            $_SESSION['products_error'] = 'That product could not be found.';
        } else {
            $_SESSION['products_success'] = "Added $qty units to stock.";
        }
        header('Location: products.php');
        exit;

    } catch (PDOException $e) {
        error_log('Product restock failed: ' . $e->getMessage());
        $_SESSION['products_error'] = 'Unable to update stock right now. Please try again.';
        header('Location: products.php');
        exit;
    }
}