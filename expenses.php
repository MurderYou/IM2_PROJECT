<?php
/**
 * expenses.php
 * Sales and Expense Monitoring System (SEMS)
 *
 * Covers: expense recording (create/edit/delete), category management,
 * and search/filtering of expense history. Mirrors the same pattern as
 * sales.php — this page renders and reads, expenses_process.php does
 * all the writing.
 */

session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$activePage = 'expenses';
$fullName   = $_SESSION['full_name'] ?? 'User';
$role       = $_SESSION['role'] ?? 'staff';

$error   = $_SESSION['expenses_error'] ?? null;
$success = $_SESSION['expenses_success'] ?? null;
unset($_SESSION['expenses_error'], $_SESSION['expenses_success']);

$categories = [];
$expenses   = [];
$dataUnavailable = false;

// If ?edit=ID is present, load that expense so the form can be prefilled.
$editingExpense = null;
$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;

// Filters
$filterCategory = isset($_GET['category']) ? (int) $_GET['category'] : 0;
$filterFrom     = $_GET['from'] ?? '';
$filterTo       = $_GET['to'] ?? '';

try {
    $categories = $pdo->query("SELECT id, name FROM expense_categories ORDER BY name ASC")->fetchAll();

    if ($editId > 0) {
        $stmt = $pdo->prepare("SELECT * FROM expenses WHERE id = :id");
        $stmt->execute(['id' => $editId]);
        $editingExpense = $stmt->fetch();
    }

    $sql = "SELECT e.id, e.description, e.amount, e.expense_date, ec.name AS category_name, e.category_id
            FROM expenses e
            JOIN expense_categories ec ON ec.id = e.category_id
            WHERE 1=1";
    $params = [];

    if ($filterCategory > 0) {
        $sql .= " AND e.category_id = :category_id";
        $params['category_id'] = $filterCategory;
    }
    if ($filterFrom !== '') {
        $sql .= " AND e.expense_date >= :from_date";
        $params['from_date'] = $filterFrom . ' 00:00:00';
    }
    if ($filterTo !== '') {
        $sql .= " AND e.expense_date <= :to_date";
        $params['to_date'] = $filterTo . ' 23:59:59';
    }

    $sql .= " ORDER BY e.expense_date DESC LIMIT 100";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $expenses = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log('Expenses page query failed: ' . $e->getMessage());
    $dataUnavailable = true;
}

function pesos($amount) {
    return '₱' . number_format((float) $amount, 2);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Expenses — SEMS</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600&family=IBM+Plex+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="style.css">

<style>
  .split-panels { display: grid; grid-template-columns: 1.4fr 1fr; gap: 1.25rem; margin-bottom: 1.5rem; }
  @media (max-width: 992px) { .split-panels { grid-template-columns: 1fr; } }

  .category-list { list-style: none; padding: 0; margin: 1rem 0 0; }
  .category-list li {
    display: flex; justify-content: space-between; align-items: center;
    padding: 0.5rem 0; border-bottom: 1px solid rgba(22,33,28,0.06); font-size: 0.9rem;
  }
  .category-list li:last-child { border-bottom: none; }

  .inline-form { display: flex; gap: 0.5rem; margin-top: 0.5rem; }
  .inline-form input { flex: 1; }

  .filter-row { display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: end; margin-bottom: 1.25rem; }
  .filter-row .field { margin-bottom: 0; min-width: 160px; }
  .filter-row .field label { font-size: 0.75rem; }

  .edit-banner {
    background: rgba(184, 134, 11, 0.1); border-left: 3px solid var(--gold);
    color: #6b5209; font-size: 0.875rem; padding: 0.6rem 1rem; margin-bottom: 1.25rem;
    display: flex; justify-content: space-between; align-items: center;
  }
  .edit-banner a { color: var(--gold-hover); font-weight: 500; text-decoration: none; }
</style>
</head>
<body>

<div class="layout">

  <?php include __DIR__ . '/includes/sidebar.php'; ?>

  <main class="main">
    <div class="topbar">
      <h1>Expenses</h1>
      <div class="welcome">
        <?php echo htmlspecialchars($fullName); ?>
        <span class="role-badge"><?php echo htmlspecialchars($role); ?></span>
      </div>
    </div>

    <?php if ($dataUnavailable): ?>
      <div class="notice-banner">Unable to load expense data right now. Please try again shortly.</div>
    <?php endif; ?>

    <?php if ($success): ?><div class="success-banner"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="error-banner"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <div class="split-panels">
      <div class="panel">
        <h2><?php echo $editingExpense ? 'Edit expense' : 'Record an expense'; ?></h2>

        <?php if ($editingExpense): ?>
          <div class="edit-banner">
            Editing expense #<?php echo (int) $editingExpense['id']; ?>
            <a href="expenses.php">Cancel edit</a>
          </div>
        <?php endif; ?>

        <?php if (empty($categories)): ?>
          <p class="empty-state">Add a category first before recording an expense.</p>
        <?php else: ?>
          <form action="expenses_process.php" method="POST">
            <input type="hidden" name="action" value="<?php echo $editingExpense ? 'update' : 'create'; ?>">
            <?php if ($editingExpense): ?>
              <input type="hidden" name="expense_id" value="<?php echo (int) $editingExpense['id']; ?>">
            <?php endif; ?>

            <div class="field">
              <label for="category_id">Category</label>
              <select id="category_id" name="category_id" required>
                <option value="">Select category</option>
                <?php foreach ($categories as $cat): ?>
                  <option value="<?php echo (int) $cat['id']; ?>"
                    <?php echo ($editingExpense && (int) $editingExpense['category_id'] === (int) $cat['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($cat['name']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="field">
              <label for="description">Description</label>
              <input type="text" id="description" name="description" required maxlength="255"
                     value="<?php echo htmlspecialchars($editingExpense['description'] ?? ''); ?>">
            </div>

            <div class="field">
              <label for="amount">Amount (₱)</label>
              <input type="number" id="amount" name="amount" step="0.01" min="0.01" required
                     value="<?php echo htmlspecialchars($editingExpense['amount'] ?? ''); ?>">
            </div>

            <div class="field">
              <label for="expense_date">Date</label>
              <input type="date" id="expense_date" name="expense_date" required
                     value="<?php echo htmlspecialchars($editingExpense ? date('Y-m-d', strtotime($editingExpense['expense_date'])) : date('Y-m-d')); ?>">
            </div>

            <button type="submit" class="btn btn-primary"><?php echo $editingExpense ? 'Update expense' : 'Save expense'; ?></button>
          </form>
        <?php endif; ?>
      </div>

      <div class="panel">
        <h2>Expense categories</h2>
        <?php if (empty($categories)): ?>
          <p class="empty-state">No categories yet — add one below.</p>
        <?php else: ?>
          <ul class="category-list">
            <?php foreach ($categories as $cat): ?>
              <li>
                <span><?php echo htmlspecialchars($cat['name']); ?></span>
                <form action="expenses_process.php" method="POST" onsubmit="return confirm('Delete this category? Only possible if no expenses use it.');">
                  <input type="hidden" name="action" value="delete_category">
                  <input type="hidden" name="category_id" value="<?php echo (int) $cat['id']; ?>">
                  <button type="submit" class="btn btn-danger btn-sm">Remove</button>
                </form>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>

        <form action="expenses_process.php" method="POST" class="inline-form">
          <input type="hidden" name="action" value="add_category">
          <input type="text" name="category_name" placeholder="New category name" required maxlength="100">
          <button type="submit" class="btn btn-secondary btn-sm">Add</button>
        </form>
      </div>
    </div>

    <div class="panel">
      <h2>Expense history</h2>

      <form method="GET" class="filter-row">
        <div class="field">
          <label for="category">Category</label>
          <select id="category" name="category">
            <option value="0">All categories</option>
            <?php foreach ($categories as $cat): ?>
              <option value="<?php echo (int) $cat['id']; ?>" <?php echo $filterCategory === (int) $cat['id'] ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($cat['name']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="from">From</label>
          <input type="date" id="from" name="from" value="<?php echo htmlspecialchars($filterFrom); ?>">
        </div>
        <div class="field">
          <label for="to">To</label>
          <input type="date" id="to" name="to" value="<?php echo htmlspecialchars($filterTo); ?>">
        </div>
        <button type="submit" class="btn btn-secondary">Filter</button>
        <?php if ($filterCategory || $filterFrom || $filterTo): ?>
          <a href="expenses.php" class="btn btn-secondary">Clear</a>
        <?php endif; ?>
      </form>

      <?php if (empty($expenses)): ?>
        <p class="empty-state">No expenses match these filters.</p>
      <?php else: ?>
        <table class="data-table">
          <thead>
            <tr><th>Date</th><th>Category</th><th>Description</th><th>Amount</th><th></th></tr>
          </thead>
          <tbody>
            <?php foreach ($expenses as $exp): ?>
              <tr>
                <td><?php echo date('M j, Y', strtotime($exp['expense_date'])); ?></td>
                <td><?php echo htmlspecialchars($exp['category_name']); ?></td>
                <td><?php echo htmlspecialchars($exp['description']); ?></td>
                <td><?php echo pesos($exp['amount']); ?></td>
                <td>
                  <a href="expenses.php?edit=<?php echo (int) $exp['id']; ?>" class="btn btn-secondary btn-sm">Edit</a>
                  <form action="expenses_process.php" method="POST" style="display:inline" onsubmit="return confirm('Delete this expense?');">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="expense_id" value="<?php echo (int) $exp['id']; ?>">
                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </main>

</div>

</body>
</html>