<?php
/**
 * sale_edit.php
 * Edit an existing sale.
 *
 * Reuses the same dynamic line-item table as the "record a new sale"
 * form on sales.php, but pre-filled with the sale's current items and
 * posted to sales_process.php with action=edit instead of action=create.
 * All the stock/price re-validation happens server-side in handleEdit().
 */

session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$activePage = 'sales';
$fullName   = $_SESSION['full_name'] ?? 'User';
$role       = $_SESSION['role'] ?? 'staff';

$error = $_SESSION['sales_error'] ?? null;
unset($_SESSION['sales_error']);

$saleId = (int) ($_GET['id'] ?? 0);
if ($saleId <= 0) {
    header('Location: sales.php');
    exit;
}

$products     = [];
$existingItems = [];
$saleFound    = false;
$dataUnavailable = false;

try {
    $stmt = $pdo->prepare('SELECT id FROM sales WHERE id = :id');
    $stmt->execute(['id' => $saleId]);
    $saleFound = (bool) $stmt->fetch();

    if ($saleFound) {
        $stmt = $pdo->query("SELECT id, name, price, stock_qty FROM products ORDER BY name ASC");
        $products = $stmt->fetchAll();

        $stmt = $pdo->prepare('SELECT product_id, quantity, unit_price FROM sale_items WHERE sale_id = :id');
        $stmt->execute(['id' => $saleId]);
        $existingItems = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    error_log('Sale edit page query failed: ' . $e->getMessage());
    $dataUnavailable = true;
}

if (!$dataUnavailable && !$saleFound) {
    $_SESSION['sales_error'] = 'That sale record could not be found.';
    header('Location: sales.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit sale — SEMS</title>

<?php include __DIR__ . '/includes/head.php'; ?>

</head>
<body>

<div class="layout">

  <?php include __DIR__ . '/includes/sidebar.php'; ?>

  <main class="main">
    <div class="topbar">
      <h1>Edit sale #<?php echo (int) $saleId; ?></h1>
      <div class="welcome">
        <?php echo htmlspecialchars($fullName); ?>
        <span class="role-badge"><?php echo htmlspecialchars($role); ?></span>
      </div>
    </div>

    <?php if ($dataUnavailable): ?>
      <div class="notice-banner">Unable to load this sale right now. Please try again shortly.</div>
    <?php endif; ?>

        <?php include __DIR__ . '/includes/flash.php'; ?>

    <?php if (!$dataUnavailable): ?>
    <div class="panel">
      <h2>Update line items</h2>
      <p class="stock-hint">Stock shown below already accounts for what this sale currently holds, so it's safe to raise or lower quantities.</p>

      <?php if (empty($products)): ?>
        <p class="empty-state">No products found. Add products to the database before editing a sale.</p>
      <?php else: ?>
        <form action="sales_process.php" method="POST" id="saleForm">
          <input type="hidden" name="action" value="edit">
          <input type="hidden" name="sale_id" value="<?php echo (int) $saleId; ?>">

          <table class="line-items-table" id="itemsTable">
            <thead>
              <tr>
                <th style="width: 34%">Product</th>
                <th style="width: 16%">Quantity</th>
                <th style="width: 20%">Unit price</th>
                <th style="width: 20%">Subtotal</th>
                <th style="width: 10%"></th>
              </tr>
            </thead>
            <tbody id="itemsBody">
              <!-- rows added by JS, pre-filled from existingItems -->
            </tbody>
          </table>

          <button type="button" class="btn btn-secondary btn-sm" id="addRowBtn">+ Add product</button>

          <div class="form-actions-row">
            <div class="grand-total">Total: <span id="grandTotal">₱0.00</span></div>
            <div style="display:flex; gap:0.75rem;">
              <a href="sales.php" class="btn btn-secondary">Cancel</a>
              <button type="submit" class="btn btn-primary">Save changes</button>
            </div>
          </div>
        </form>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </main>

</div>

<script>
  const products = <?php echo json_encode(array_map(fn($p) => [
      'id' => (int) $p['id'],
      'name' => $p['name'],
      'price' => (float) $p['price'],
      'stock' => (int) $p['stock_qty'],
  ], $products)); ?>;

  // The sale's current line items, so the form opens exactly as the
  // sale looks today. Quantities here are effectively "returned" to
  // stock server-side before the new totals are re-checked, which is
  // why the stock counts above already include them.
  const existingItems = <?php echo json_encode(array_map(fn($i) => [
      'product_id' => (int) $i['product_id'],
      'quantity'   => (int) $i['quantity'],
  ], $existingItems)); ?>;

  const itemsBody = document.getElementById('itemsBody');

  function productOptions(selectedId) {
    return products.map(p =>
      `<option value="${p.id}" data-price="${p.price}" data-stock="${p.stock}" ${p.id === selectedId ? 'selected' : ''}>
        ${p.name} (${p.stock} in stock)
      </option>`
    ).join('');
  }

  function addRow(prefillProductId = null, prefillQty = 1) {
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>
        <select name="product_id[]" class="product-select" required>
          <option value="">Select product</option>
          ${productOptions(prefillProductId)}
        </select>
      </td>
      <td><input type="number" name="quantity[]" class="qty-input" min="1" value="${prefillQty}" required></td>
      <td><input type="number" name="unit_price[]" class="price-input" min="0" step="0.01" required></td>
      <td><input type="text" class="subtotal-display" readonly value="₱0.00"></td>
      <td><button type="button" class="remove-row-btn" title="Remove">&times;</button></td>
    `;
    itemsBody.appendChild(tr);
    bindRow(tr);

    // Prime the unit price / subtotal from the selected product.
    const select = tr.querySelector('.product-select');
    const opt = select.options[select.selectedIndex];
    if (opt && opt.dataset.price !== undefined) {
      tr.querySelector('.price-input').value = parseFloat(opt.dataset.price).toFixed(2);
    }
    recalcRowFor(tr);
  }

  function bindRow(tr) {
    const select = tr.querySelector('.product-select');
    const qty = tr.querySelector('.qty-input');
    const price = tr.querySelector('.price-input');
    const removeBtn = tr.querySelector('.remove-row-btn');

    select.addEventListener('change', () => {
      const opt = select.options[select.selectedIndex];
      const p = opt.dataset.price;
      if (p !== undefined) {
        price.value = parseFloat(p).toFixed(2);
      }
      recalcRowFor(tr);
    });

    [qty, price].forEach(el => el.addEventListener('input', () => recalcRowFor(tr)));

    removeBtn.addEventListener('click', () => {
      if (itemsBody.children.length > 1) {
        tr.remove();
        recalcGrandTotal();
      }
    });
  }

  function recalcRowFor(tr) {
    const q = parseFloat(tr.querySelector('.qty-input').value) || 0;
    const p = parseFloat(tr.querySelector('.price-input').value) || 0;
    tr.querySelector('.subtotal-display').value = '₱' + (q * p).toFixed(2);
    recalcGrandTotal();
  }

  function recalcGrandTotal() {
    let total = 0;
    itemsBody.querySelectorAll('tr').forEach(tr => {
      const q = parseFloat(tr.querySelector('.qty-input').value) || 0;
      const p = parseFloat(tr.querySelector('.price-input').value) || 0;
      total += q * p;
    });
    document.getElementById('grandTotal').textContent = '₱' + total.toFixed(2);
  }

  document.getElementById('addRowBtn')?.addEventListener('click', () => addRow());

  // Pre-fill one row per existing line item; fall back to a single
  // blank row if the sale somehow has none.
  if (existingItems.length) {
    existingItems.forEach(item => addRow(item.product_id, item.quantity));
  } else if (products.length) {
    addRow();
    }
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

</body>
</html>