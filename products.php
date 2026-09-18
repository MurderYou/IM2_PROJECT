<?php
/**
 * products.php
 * Sales and Expense Monitoring System (SEMS)
 *
 * Manages the product catalog: create/edit/delete products, and a
 * dedicated "restock" action to add to stock_qty. This is deliberately
 * separate from expenses.php — logging that money was spent on
 * inventory (an expense) and actually increasing what's on the shelf
 * (a stock movement) are two different facts about the business, even
 * though they're often related in practice.
 */

session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$activePage = 'products';
$fullName   = $_SESSION['full_name'] ?? 'User';
$role       = $_SESSION['role'] ?? 'staff';

$error   = $_SESSION['products_error'] ?? null;
$success = $_SESSION['products_success'] ?? null;
unset($_SESSION['products_error'], $_SESSION['products_success']);

$products = [];
$editingProduct = null;
$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$dataUnavailable = false;

try {
    if ($editId > 0) {
        $stmt = $pdo->prepare('SELECT id, name, price, stock_qty FROM products WHERE id = :id');
        $stmt->execute(['id' => $editId]);
        $editingProduct = $stmt->fetch();
    }

    $products = $pdo->query('SELECT id, name, price, stock_qty FROM products ORDER BY name ASC')->fetchAll();

} catch (PDOException $e) {
    error_log('Products page query failed: ' . $e->getMessage());
    $dataUnavailable = true;
}

function pesos($amount) {
    return '₱' . number_format((float) $amount, 2);
}

$LOW_STOCK_THRESHOLD = 10;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Products — SEMS</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600&family=IBM+Plex+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="style.css">

<style>
  .split-panels { display: grid; grid-template-columns: 1fr 1.6fr; gap: 1.25rem; margin-bottom: 1.5rem; }
  @media (max-width: 992px) { .split-panels { grid-template-columns: 1fr; } }

  .edit-banner {
    background: rgba(184, 134, 11, 0.1); border-left: 3px solid var(--gold);
    color: #6b5209; font-size: 0.875rem; padding: 0.6rem 1rem; margin-bottom: 1.25rem;
    display: flex; justify-content: space-between; align-items: center;
  }
  .edit-banner a { color: var(--gold-hover); font-weight: 500; text-decoration: none; }

  .stock-pill { font-size: 0.75rem; padding: 0.1rem 0.5rem; border-radius: 10px; font-weight: 500; }
  .stock-ok { background: rgba(43,86,70,0.1); color: var(--ledger-green); }
  .stock-low { background: rgba(179,38,30,0.1); color: var(--error-red); }

  .restock-form { display: flex; gap: 0.4rem; align-items: center; }
  .restock-form input {
    width: 70px; border: 1px solid rgba(22,33,28,0.2); padding: 0.35rem 0.5rem;
    border-radius: 4px; font-size: 0.85rem;
  }
</style>
</head>
<body>

<div class="layout">

  <?php include __DIR__ . '/includes/sidebar.php'; ?>

  <main class="main">
    <div class="topbar">
      <h1>Products</h1>
      <div class="welcome">
        <?php echo htmlspecialchars($fullName); ?>
        <span class="role-badge"><?php echo htmlspecialchars($role); ?></span>
      </div>
    </div>

    <?php if ($dataUnavailable): ?>
      <div class="notice-banner">Unable to load product data right now. Please try again shortly.</div>
    <?php endif; ?>

    <?php if ($success): ?><div class="success-banner"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="error-banner"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <div class="split-panels">
      <div class="panel">
        <h2><?php echo $editingProduct ? 'Edit product' : 'Add product'; ?></h2>

        <?php if ($editingProduct): ?>
          <div class="edit-banner">
            Editing "<?php echo htmlspecialchars($editingProduct['name']); ?>"
            <a href="products.php">Cancel edit</a>
          </div>
        <?php endif; ?>

        <form action="products_process.php" method="POST">
          <input type="hidden" name="action" value="<?php echo $editingProduct ? 'update' : 'create'; ?>">
          <?php if ($editingProduct): ?>
            <input type="hidden" name="product_id" value="<?php echo (int) $editingProduct['id']; ?>">
          <?php endif; ?>

          <div class="field">
            <label for="name">Product name</label>
            <input type="text" id="name" name="name" required maxlength="100"
                   value="<?php echo htmlspecialchars($editingProduct['name'] ?? ''); ?>">
          </div>

          <div class="field">
            <label for="price">Price (₱)</label>
            <input type="number" id="price" name="price" step="0.01" min="0.01" required
                   value="<?php echo htmlspecialchars($editingProduct['price'] ?? ''); ?>">
          </div>

          <div class="field">
            <label for="stock_qty"><?php echo $editingProduct ? 'Current stock' : 'Starting stock'; ?></label>
            <input type="number" id="stock_qty" name="stock_qty" min="0" required
                   value="<?php echo htmlspecialchars($editingProduct['stock_qty'] ?? '0'); ?>">
            <?php if ($editingProduct): ?>
              <div class="hint" style="font-size:0.75rem; color:rgba(22,33,28,0.5); margin-top:0.35rem;">
                To add newly delivered stock without retyping the total, use "Restock" in the table instead.
              </div>
            <?php endif; ?>
          </div>

          <button type="submit" class="btn btn-primary"><?php echo $editingProduct ? 'Update product' : 'Add product'; ?></button>
        </form>
      </div>

      <div class="panel">
        <h2>Product catalog</h2>
        <?php if (empty($products)): ?>
          <p class="empty-state">No products yet — add one to get started.</p>
        <?php else: ?>
          <table class="data-table">
            <thead><tr><th>Product</th><th>Price</th><th>Stock</th><th>Restock</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($products as $p): ?>
                <tr>
                  <td><?php echo htmlspecialchars($p['name']); ?></td>
                  <td><?php echo pesos($p['price']); ?></td>
                  <td>
                    <span class="stock-pill <?php echo $p['stock_qty'] <= $LOW_STOCK_THRESHOLD ? 'stock-low' : 'stock-ok'; ?>">
                      <?php echo (int) $p['stock_qty']; ?> <?php echo $p['stock_qty'] <= $LOW_STOCK_THRESHOLD ? '(low)' : ''; ?>
                    </span>
                  </td>
                  <td>
                    <form action="products_process.php" method="POST" class="restock-form">
                      <input type="hidden" name="action" value="restock">
                      <input type="hidden" name="product_id" value="<?php echo (int) $p['id']; ?>">
                      <input type="number" name="restock_qty" min="1" placeholder="qty" required>
                      <button type="submit" class="btn btn-secondary btn-sm">+ Add</button>
                    </form>
                  </td>
                  <td>
                    <a href="products.php?edit=<?php echo (int) $p['id']; ?>" class="btn btn-secondary btn-sm">Edit</a>
                    <form action="products_process.php" method="POST" style="display:inline" onsubmit="return confirm('Delete this product? This cannot be undone.');">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="product_id" value="<?php echo (int) $p['id']; ?>">
                      <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </div>
  </main>

</div>

</body>
</html>