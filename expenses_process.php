<?php
/**
 * expenses_process.php
 * Handles all write actions from expenses.php:
 * - create          : record a new expense
 * - update          : edit an existing expense
 * - delete          : remove an expense
 * - add_category    : create a new expense category
 * - delete_category : remove a category (blocked if expenses use it)
 */

session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: expenses.php');
    exit;
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'create':
        handleSave($pdo, false);
        break;
    case 'update':
        handleSave($pdo, true);
        break;
    case 'delete':
        handleDelete($pdo);
        break;
    case 'add_category':
        handleAddCategory($pdo);
        break;
    case 'delete_category':
        handleDeleteCategory($pdo);
        break;
    default:
        header('Location: expenses.php');
        exit;
}

function validateExpenseInput(): array {
    $categoryId  = (int) ($_POST['category_id'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $amount      = $_POST['amount'] ?? '';
    $expenseDate = $_POST['expense_date'] ?? '';

    $errors = [];

    if ($categoryId <= 0) $errors[] = 'a category';
    if ($description === '') $errors[] = 'a description';
    if ($amount === '' || !is_numeric($amount) || (float) $amount <= 0) $errors[] = 'a valid amount greater than zero';

    $parsedDate = strtotime($expenseDate);
    if ($expenseDate === '' || $parsedDate === false) {
        $errors[] = 'a valid date';
    }

    if (!empty($errors)) {
        $_SESSION['expenses_error'] = 'Please provide ' . implode(', ', $errors) . '.';
        header('Location: expenses.php');
        exit;
    }

    return [
        'category_id'  => $categoryId,
        'description'  => $description,
        'amount'       => (float) $amount,
        'expense_date' => date('Y-m-d H:i:s', $parsedDate),
    ];
}

function handleSave(PDO $pdo, bool $isUpdate) {
    $data = validateExpenseInput();

    try {
        // Confirm the category actually exists (defends against a
        // tampered category_id value in the submitted form).
        $check = $pdo->prepare('SELECT id FROM expense_categories WHERE id = :id');
        $check->execute(['id' => $data['category_id']]);
        if (!$check->fetch()) {
            $_SESSION['expenses_error'] = 'Selected category no longer exists.';
            header('Location: expenses.php');
            exit;
        }

        if ($isUpdate) {
            $expenseId = (int) ($_POST['expense_id'] ?? 0);
            if ($expenseId <= 0) {
                $_SESSION['expenses_error'] = 'Invalid expense reference.';
                header('Location: expenses.php');
                exit;
            }
            $stmt = $pdo->prepare(
                'UPDATE expenses SET category_id = :category_id, description = :description,
                 amount = :amount, expense_date = :expense_date WHERE id = :id'
            );
            $stmt->execute([
                'category_id'  => $data['category_id'],
                'description'  => $data['description'],
                'amount'       => $data['amount'],
                'expense_date' => $data['expense_date'],
                'id'           => $expenseId,
            ]);
            $_SESSION['expenses_success'] = 'Expense updated.';
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO expenses (category_id, description, amount, expense_date, user_id)
                 VALUES (:category_id, :description, :amount, :expense_date, :user_id)'
            );
            $stmt->execute([
                'category_id'  => $data['category_id'],
                'description'  => $data['description'],
                'amount'       => $data['amount'],
                'expense_date' => $data['expense_date'],
                'user_id'      => $_SESSION['user_id'],
            ]);
            $_SESSION['expenses_success'] = 'Expense recorded.';
        }

        header('Location: expenses.php');
        exit;

    } catch (PDOException $e) {
        error_log('Expense save failed: ' . $e->getMessage());
        $_SESSION['expenses_error'] = 'Unable to save the expense. Please check the information and try again.';
        header('Location: expenses.php');
        exit;
    }
}

function handleDelete(PDO $pdo) {
    $expenseId = (int) ($_POST['expense_id'] ?? 0);

    if ($expenseId <= 0) {
        $_SESSION['expenses_error'] = 'Invalid expense reference.';
        header('Location: expenses.php');
        exit;
    }

    try {
        $stmt = $pdo->prepare('DELETE FROM expenses WHERE id = :id');
        $stmt->execute(['id' => $expenseId]);

        if ($stmt->rowCount() === 0) {
            $_SESSION['expenses_error'] = 'That expense record could not be found.';
        } else {
            $_SESSION['expenses_success'] = 'Expense deleted.';
        }

        header('Location: expenses.php');
        exit;

    } catch (PDOException $e) {
        error_log('Expense deletion failed: ' . $e->getMessage());
        $_SESSION['expenses_error'] = 'Unable to delete this expense right now. Please try again.';
        header('Location: expenses.php');
        exit;
    }
}

function handleAddCategory(PDO $pdo) {
    $name = trim($_POST['category_name'] ?? '');

    if ($name === '') {
        $_SESSION['expenses_error'] = 'Category name cannot be empty.';
        header('Location: expenses.php');
        exit;
    }

    try {
        $stmt = $pdo->prepare('SELECT id FROM expense_categories WHERE name = :name');
        $stmt->execute(['name' => $name]);
        if ($stmt->fetch()) {
            $_SESSION['expenses_error'] = 'That category already exists.';
            header('Location: expenses.php');
            exit;
        }

        $stmt = $pdo->prepare('INSERT INTO expense_categories (name) VALUES (:name)');
        $stmt->execute(['name' => $name]);

        $_SESSION['expenses_success'] = 'Category added.';
        header('Location: expenses.php');
        exit;

    } catch (PDOException $e) {
        error_log('Category creation failed: ' . $e->getMessage());
        $_SESSION['expenses_error'] = 'Unable to add the category right now. Please try again.';
        header('Location: expenses.php');
        exit;
    }
}

function handleDeleteCategory(PDO $pdo) {
    $categoryId = (int) ($_POST['category_id'] ?? 0);

    if ($categoryId <= 0) {
        $_SESSION['expenses_error'] = 'Invalid category reference.';
        header('Location: expenses.php');
        exit;
    }

    try {
        $stmt = $pdo->prepare('DELETE FROM expense_categories WHERE id = :id');
        $stmt->execute(['id' => $categoryId]);
        $_SESSION['expenses_success'] = 'Category removed.';
        header('Location: expenses.php');
        exit;

    } catch (PDOException $e) {
        // Foreign key violation — category is still used by existing expenses.
        if ($e->getCode() === '23000') {
            $_SESSION['expenses_error'] = 'Cannot remove this category — it still has expenses recorded under it.';
        } else {
            error_log('Category deletion failed: ' . $e->getMessage());
            $_SESSION['expenses_error'] = 'Unable to remove this category right now. Please try again.';
        }
        header('Location: expenses.php');
        exit;
    }
}