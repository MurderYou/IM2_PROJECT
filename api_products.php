<?php
/**
 * api_products.php
 * AJAX-friendly endpoint for product operations in SEMS.
 *
 * Unlike product_process.php (which redirects on success/failure),
 * this endpoint returns JSON so the calling page (e.g. sales.php)
 * can handle the result inline without a full page reload.
 *
 * Currently supports:
 *   POST action=create   — add a new product, return the new row
 *   GET                   — list all products as JSON
 */
session_start();
require_once __DIR__ . '/db.php';

// --- Auth guard ----------------------------------------------------
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not logged in.']);
    exit;
}

// --- Helpers -------------------------------------------------------
function jsonFail(string $msg): void {
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $msg]);
    exit;
}

function jsonSuccess(array $payload): void {
    header('Content-Type: application/json');
    echo json_encode(array_merge(['success' => true], $payload));
    exit;
}

// --- GET: return product list --------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $stmt = $pdo->query('SELECT id, name, price, stock_qty FROM products ORDER BY name ASC');
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        jsonSuccess(['products' => $products]);
    } catch (PDOException $e) {
        error_log('api_products GET failed: ' . $e->getMessage());
        jsonFail('Unable to load product list.');
    }
}

// --- POST: handle product operations -------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonFail('Method not allowed.');
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'create':
        $name  = trim($_POST['name'] ?? '');
        $price = $_POST['price'] ?? '';
        $stock = $_POST['stock_qty'] ?? '0';

        // --- Validate ---
        $errors = [];
        if ($name === '') {
            $errors[] = 'a product name';
        }
        if ($price === '' || !is_numeric($price) || (float) $price <= 0) {
            $errors[] = 'a valid price greater than zero';
        }
        if ($stock === '' || !is_numeric($stock) || (int) $stock < 0) {
            $errors[] = 'a valid stock quantity (zero or more)';
        }

        if (!empty($errors)) {
            jsonFail('Please provide ' . implode(', ', $errors) . '.');
        }

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO products (name, price, stock_qty) VALUES (:name, :price, :stock_qty)'
            );
            $stmt->execute([
                'name'      => $name,
                'price'     => (float) $price,
                'stock_qty' => (int) $stock,
            ]);

            jsonSuccess([
                'message'  => 'Product added.',
                'product'  => [
                    'id'        => (int) $pdo->lastInsertId(),
                    'name'      => $name,
                    'price'     => (float) $price,
                    'stock_qty' => (int) $stock,
                ],
            ]);
        } catch (PDOException $e) {
            error_log('api_products create failed: ' . $e->getMessage());
            jsonFail('Unable to add the product right now. Please try again.');
        }

        // Unreachable, but satisfies the switch for static analysis tools.
        jsonFail('Unexpected error.');

    default:
        jsonFail('Unknown action.');
}
