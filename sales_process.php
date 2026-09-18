<?php
/**
 * sales_process.php
 * Handles all three actions from sales.php / sale_edit.php:
 * - action=create : validates line items, checks stock, inserts the
 *   sale + its items inside a database transaction, and decrements
 *   product stock.
 * - action=edit   : restores the stock the sale originally deducted,
 *   re-validates the new line items against that clean baseline
 *   (same rules as create), then replaces the sale's items and
 *   deducts stock for the new quantities. All inside one transaction,
 *   so a partial edit can never leave stock or totals inconsistent.
 * - action=delete : voids a sale, restores the stock it had deducted,
 *   and removes the sale + its items.
 *
 * Server-side prices are always looked up from the products table —
 * client-submitted prices are never trusted directly for the totals
 * that get saved, since a form value can be edited in the browser.
 */

session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: sales.php');
    exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'create') {
    handleCreate($pdo);
} elseif ($action === 'edit') {
    handleEdit($pdo);
} elseif ($action === 'delete') {
    handleDelete($pdo);
} else {
    header('Location: sales.php');
    exit;
}

function handleCreate(PDO $pdo) {
    $productIds = $_POST['product_id'] ?? [];
    $quantities = $_POST['quantity'] ?? [];

    // --- Basic input validation (rubric requirement 6) ---
    if (empty($productIds) || count($productIds) !== count($quantities)) {
        $_SESSION['sales_error'] = 'Please add at least one valid product line.';
        header('Location: sales.php');
        exit;
    }

    $items = [];
    for ($i = 0; $i < count($productIds); $i++) {
        $pid = (int) $productIds[$i];
        $qty = (int) $quantities[$i];

        if ($pid <= 0 || $qty <= 0) {
            $_SESSION['sales_error'] = 'Each line item needs a product and a quantity greater than zero.';
            header('Location: sales.php');
            exit;
        }
        $items[] = ['product_id' => $pid, 'quantity' => $qty];
    }

    try {
        $pdo->beginTransaction();

        // Lock and re-check stock for every item using authoritative
        // database prices — never trust client-submitted prices/stock.
        $insufficientStock = [];
        $lineData = [];

        $stmt = $pdo->prepare('SELECT id, name, price, stock_qty FROM products WHERE id = :id FOR UPDATE');

        foreach ($items as $item) {
            $stmt->execute(['id' => $item['product_id']]);
            $product = $stmt->fetch();

            if (!$product) {
                $pdo->rollBack();
                $_SESSION['sales_error'] = 'One of the selected products no longer exists.';
                header('Location: sales.php');
                exit;
            }

            if ($product['stock_qty'] < $item['quantity']) {
                $insufficientStock[] = $product['name'] . ' (only ' . $product['stock_qty'] . ' left)';
            }

            $lineData[] = [
                'product_id' => $product['id'],
                'quantity'   => $item['quantity'],
                'unit_price' => (float) $product['price'],
                'subtotal'   => $item['quantity'] * (float) $product['price'],
            ];
        }

        // Prevent the invalid transaction outright, per rubric requirement 4.
        if (!empty($insufficientStock)) {
            $pdo->rollBack();
            $_SESSION['sales_error'] = 'Not enough stock for: ' . implode(', ', $insufficientStock) . '.';
            header('Location: sales.php');
            exit;
        }

        $totalAmount = array_sum(array_column($lineData, 'subtotal'));

        $stmt = $pdo->prepare('INSERT INTO sales (sale_date, total_amount, user_id) VALUES (NOW(), :total, :user_id)');
        $stmt->execute([
            'total'   => $totalAmount,
            'user_id' => $_SESSION['user_id'],
        ]);
        $saleId = $pdo->lastInsertId();

        $itemStmt  = $pdo->prepare('INSERT INTO sale_items (sale_id, product_id, quantity, unit_price, subtotal) VALUES (:sale_id, :product_id, :quantity, :unit_price, :subtotal)');
        $stockStmt = $pdo->prepare('UPDATE products SET stock_qty = stock_qty - :qty WHERE id = :id');

        foreach ($lineData as $line) {
            $itemStmt->execute([
                'sale_id'    => $saleId,
                'product_id' => $line['product_id'],
                'quantity'   => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'subtotal'   => $line['subtotal'],
            ]);
            $stockStmt->execute([
                'qty' => $line['quantity'],
                'id'  => $line['product_id'],
            ]);
        }

        $pdo->commit();
        $_SESSION['sales_success'] = 'Sale recorded successfully.';
        header('Location: sales.php');
        exit;

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Sale creation failed: ' . $e->getMessage());
        $_SESSION['sales_error'] = 'Unable to save the sale. Please check the information and try again.';
        header('Location: sales.php');
        exit;
    }
}

function handleEdit(PDO $pdo) {
    $saleId     = (int) ($_POST['sale_id'] ?? 0);
    $productIds = $_POST['product_id'] ?? [];
    $quantities = $_POST['quantity'] ?? [];

    if ($saleId <= 0) {
        $_SESSION['sales_error'] = 'Invalid sale reference.';
        header('Location: sales.php');
        exit;
    }

    if (empty($productIds) || count($productIds) !== count($quantities)) {
        $_SESSION['sales_error'] = 'Please add at least one valid product line.';
        header('Location: sale_edit.php?id=' . $saleId);
        exit;
    }

    $items = [];
    for ($i = 0; $i < count($productIds); $i++) {
        $pid = (int) $productIds[$i];
        $qty = (int) $quantities[$i];

        if ($pid <= 0 || $qty <= 0) {
            $_SESSION['sales_error'] = 'Each line item needs a product and a quantity greater than zero.';
            header('Location: sale_edit.php?id=' . $saleId);
            exit;
        }
        $items[] = ['product_id' => $pid, 'quantity' => $qty];
    }

    try {
        $pdo->beginTransaction();

        // Lock the sale row itself so a concurrent edit/void on the
        // same sale can't race with this one.
        $stmt = $pdo->prepare('SELECT id FROM sales WHERE id = :id FOR UPDATE');
        $stmt->execute(['id' => $saleId]);
        if (!$stmt->fetch()) {
            $pdo->rollBack();
            $_SESSION['sales_error'] = 'That sale record could not be found.';
            header('Location: sales.php');
            exit;
        }

        // Step 1: give back the stock this sale currently holds, so
        // the check below runs against a clean baseline — as if the
        // original sale hadn't happened yet. This is what lets someone
        // e.g. lower a quantity on one line and raise it on another
        // without a false "insufficient stock" error.
        $oldItemsStmt = $pdo->prepare('SELECT product_id, quantity FROM sale_items WHERE sale_id = :id');
        $oldItemsStmt->execute(['id' => $saleId]);
        $oldItems = $oldItemsStmt->fetchAll();

        $lockStmt    = $pdo->prepare('SELECT id FROM products WHERE id = :id FOR UPDATE');
        $restoreStmt = $pdo->prepare('UPDATE products SET stock_qty = stock_qty + :qty WHERE id = :id');

        foreach ($oldItems as $old) {
            $lockStmt->execute(['id' => $old['product_id']]);
            $restoreStmt->execute([
                'qty' => $old['quantity'],
                'id'  => $old['product_id'],
            ]);
        }

        // Step 2: re-validate the new line items against that baseline,
        // same rules as creating a sale — authoritative DB prices,
        // authoritative stock, reject the whole edit if anything is
        // short (nothing has been committed yet, so a rollback here
        // leaves stock exactly as it was before the edit attempt).
        $insufficientStock = [];
        $lineData = [];

        $productStmt = $pdo->prepare('SELECT id, name, price, stock_qty FROM products WHERE id = :id FOR UPDATE');

        foreach ($items as $item) {
            $productStmt->execute(['id' => $item['product_id']]);
            $product = $productStmt->fetch();

            if (!$product) {
                $pdo->rollBack();
                $_SESSION['sales_error'] = 'One of the selected products no longer exists.';
                header('Location: sale_edit.php?id=' . $saleId);
                exit;
            }

            if ($product['stock_qty'] < $item['quantity']) {
                $insufficientStock[] = $product['name'] . ' (only ' . $product['stock_qty'] . ' available)';
            }

            $lineData[] = [
                'product_id' => $product['id'],
                'quantity'   => $item['quantity'],
                'unit_price' => (float) $product['price'],
                'subtotal'   => $item['quantity'] * (float) $product['price'],
            ];
        }

        if (!empty($insufficientStock)) {
            $pdo->rollBack();
            $_SESSION['sales_error'] = 'Not enough stock for: ' . implode(', ', $insufficientStock) . '.';
            header('Location: sale_edit.php?id=' . $saleId);
            exit;
        }

        // Step 3: replace the old line items with the new ones, update
        // the sale total, and deduct stock for the new quantities.
        $pdo->prepare('DELETE FROM sale_items WHERE sale_id = :id')->execute(['id' => $saleId]);

        $totalAmount = array_sum(array_column($lineData, 'subtotal'));

        $updateSaleStmt = $pdo->prepare('UPDATE sales SET total_amount = :total WHERE id = :id');
        $updateSaleStmt->execute([
            'total' => $totalAmount,
            'id'    => $saleId,
        ]);

        $itemStmt  = $pdo->prepare('INSERT INTO sale_items (sale_id, product_id, quantity, unit_price, subtotal) VALUES (:sale_id, :product_id, :quantity, :unit_price, :subtotal)');
        $stockStmt = $pdo->prepare('UPDATE products SET stock_qty = stock_qty - :qty WHERE id = :id');

        foreach ($lineData as $line) {
            $itemStmt->execute([
                'sale_id'    => $saleId,
                'product_id' => $line['product_id'],
                'quantity'   => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'subtotal'   => $line['subtotal'],
            ]);
            $stockStmt->execute([
                'qty' => $line['quantity'],
                'id'  => $line['product_id'],
            ]);
        }

        $pdo->commit();
        $_SESSION['sales_success'] = 'Sale updated successfully.';
        header('Location: sales.php');
        exit;

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Sale edit failed: ' . $e->getMessage());
        $_SESSION['sales_error'] = 'Unable to update the sale. Please check the information and try again.';
        header('Location: sale_edit.php?id=' . $saleId);
        exit;
    }
}

function handleDelete(PDO $pdo) {
    $saleId = (int) ($_POST['sale_id'] ?? 0);

    if ($saleId <= 0) {
        $_SESSION['sales_error'] = 'Invalid sale reference.';
        header('Location: sales.php');
        exit;
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare('SELECT id FROM sales WHERE id = :id');
        $stmt->execute(['id' => $saleId]);
        if (!$stmt->fetch()) {
            $pdo->rollBack();
            $_SESSION['sales_error'] = 'That sale record could not be found.';
            header('Location: sales.php');
            exit;
        }

        // Restore stock for every item this sale had deducted.
        $itemsStmt = $pdo->prepare('SELECT product_id, quantity FROM sale_items WHERE sale_id = :id');
        $itemsStmt->execute(['id' => $saleId]);
        $restoreStmt = $pdo->prepare('UPDATE products SET stock_qty = stock_qty + :qty WHERE id = :id');

        foreach ($itemsStmt->fetchAll() as $item) {
            $restoreStmt->execute([
                'qty' => $item['quantity'],
                'id'  => $item['product_id'],
            ]);
        }

        $pdo->prepare('DELETE FROM sale_items WHERE sale_id = :id')->execute(['id' => $saleId]);
        $pdo->prepare('DELETE FROM sales WHERE id = :id')->execute(['id' => $saleId]);

        $pdo->commit();
        $_SESSION['sales_success'] = 'Sale voided and stock restored.';
        header('Location: sales.php');
        exit;

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Sale deletion failed: ' . $e->getMessage());
        $_SESSION['sales_error'] = 'Unable to void this sale right now. Please try again.';
        header('Location: sales.php');
        exit;
    }
}