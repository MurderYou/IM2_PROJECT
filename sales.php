<?php
/**
 * sales.php
 * Sales and Expense Monitoring System (SEMS)
 *
 * Two things on one page: a form to record a new sale (dynamic
 * multi-line-item, per the project brief) and a history table of past
 * sales with edit/delete (void) options. Creation logic lives in
 * sales_process.php, editing lives in sale_edit.php + sales_process.php,
 * both of which use a database transaction so a sale and its line
 * items are never left half-saved.
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

$error   = $_SESSION['sales_error'] ?? null;
$success = $_SESSION['sales_success'] ?? null;
unset($_SESSION['sales_error'], $_SESSION['sales_success']);

$products     = [];
$salesHistory = [];
$dataUnavailable = false;

try {
    $stmt = $pdo->query("SELECT id, name, price, stock_qty FROM products ORDER BY name ASC");
    $products = $stmt->fetchAll();

    $stmt = $pdo->query("
        SELECT s.id, s.sale_date, s.total_amount, u.full_name,
               (SELECT COUNT(*) FROM sale_items si WHERE si.sale_id = s.id) AS item_count
        FROM sales s
        LEFT JOIN users u ON u.id = s.user_id
        ORDER BY s.sale_date DESC
        LIMIT 30
    ");
    $salesHistory = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Sales page query failed: ' . $e->getMessage());
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
<title>Sales — SEMS</title>

<?php include __DIR__ . '/includes/head.php'; ?>

<style>
  .line-items-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 1rem;
  }
  .line-items-table th {
    text-align: left;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    color: rgba(22, 33, 28, 0.5);
    font-weight: 500;
    padding: 0 0.5rem 0.5rem;
  }
  .line-items-table td {
    padding: 0.4rem 0.5rem;
    vertical-align: middle;
  }
  .line-items-table select,
  .line-items-table input {
    width: 100%;
    border: 1px solid rgba(22, 33, 28, 0.2);
    padding: 0.45rem 0.6rem;
    border-radius: 4px;
    font-family: 'IBM Plex Sans', sans-serif;
    font-size: 0.9rem;
  }
  .line-items-table input[readonly] {
    background: rgba(22, 33, 28, 0.04);
  }
  .remove-row-btn {
    background: none;
    border: none;
    color: var(--error-red);
    cursor: pointer;
    font-size: 1.1rem;
    padding: 0.2rem 0.5rem;
    line-height: 1;
  }
  .remove-row-btn:hover { opacity: 0.7; }
  .form-actions-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 1rem;
    flex-wrap: wrap;
    gap: 1rem;
  }
  .grand-total {
    font-family: 'Fraunces', serif;
    font-weight: 500;
    font-size: 1.25rem;
  }
  .stock-hint {
    font-size: 0.75rem;
    color: rgba(22, 33, 28, 0.45);
  }
  .row-actions {
    display: flex;
    gap: 0.5rem;
  }
</style>
</head>
<body>

<div class="layout">

  <?php include __DIR__ . '/includes/sidebar.php'; ?>

  <main class="main">
    <div class="topbar">
      <h1>Sales</h1>
      <div class="welcome">
        <?php echo htmlspecialchars($fullName); ?>
        <span class="role-badge"><?php echo htmlspecialchars($role); ?></span>
      </div>
    </div>

    <?php if ($dataUnavailable): ?>
      <div class="notice-banner">Unable to load product or sales data right now. Please try again shortly.</div>
    <?php endif; ?>

    <?php include __DIR__ . '/includes/flash.php'; ?>

    <div class="panel">
      <h2>Record a new sale</h2>
      <?php if (empty($products)): ?>
        <p class="empty-state">No products found. Add products to the database before recording a sale.</p>
      <?php else: ?>
        <form action="sales_process.php" method="POST" id="saleForm">
          <input type="hidden" name="action" value="create">

          <div class="table-responsive">
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
                <!-- rows added by JS -->
              </tbody>
            </table>
          </div>

          <button type="button" class="btn btn-secondary btn-sm no-print" id="addRowBtn">+ Add product</button>

          <div class="form-actions-row">
            <div class="grand-total">Total: <span id="grandTotal">₱0.00</span></div>
            <button type="submit" class="btn btn-primary">Save sale</button>
          </div>
        </form>
      <?php endif; ?>
    </div>

    <div class="panel">
      <h2>Sales history</h2>
      <?php if (empty($salesHistory)): ?>
        <p class="empty-state">No sales recorded yet.</p>
      <?php else: ?>
                <div class="table-responsive">
        <table class="data-table" id="salesHistoryTable">
          <thead>
            <tr>
              <th class="sortable" data-sort-key="date">Date</th>
              <th class="sortable">Recorded by</th>
              <th class="sortable">Items</th>
              <th class="sortable">Total</th>
              <th><span class="visually-hidden">Actions</span></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($salesHistory as $sale): ?>
              <tr>
                <td><?php echo date('M j, Y g:i A', strtotime($sale['sale_date'])); ?></td>
                <td><?php echo htmlspecialchars($sale['full_name'] ?? '—'); ?></td>
                <td><?php echo (int) $sale['item_count']; ?></td>
                <td><?php echo pesos($sale['total_amount']); ?></td>
                <td>
                  <div class="row-actions">
                    <a href="sale_edit.php?id=<?php echo (int) $sale['id']; ?>" class="btn btn-secondary btn-sm">Edit</a>
                    <form action="sales_process.php" method="POST" onsubmit="return confirm('Void this sale? Stock quantities will be restored.');">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="sale_id" value="<?php echo (int) $sale['id']; ?>">
                      <button type="submit" class="btn btn-danger btn-sm">Void</button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        </div>
      <?php endif; ?>
    </div>
  </main>

</div>

<script>
  const products = <?php echo json_encode(array_map(fn($p) => [
      'id' => (int) $p['id'],
      'name' => $p['name'],
      'price' => (float) $p['price'],
      'stock' => (int) $p['stock_qty'],
  ], $products)); ?>;

  const itemsBody = document.getElementById('itemsBody');
  let rowCount = 0;

  function productOptions(selectedId) {
    return products.map(p =>
      `<option value="${p.id}" data-price="${p.price}" data-stock="${p.stock}" ${p.id === selectedId ? 'selected' : ''}>
        ${p.name} (${p.stock} in stock)
      </option>`
    ).join('');
  }

  function addRow() {
    rowCount++;
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>
        <select name="product_id[]" class="product-select" required>
          <option value="">Select product</option>
          ${productOptions(null)}
        </select>
      </td>
      <td><input type="number" name="quantity[]" class="qty-input" min="1" value="1" required></td>
      <td><input type="number" name="unit_price[]" class="price-input" min="0" step="0.01" required></td>
      <td><input type="text" class="subtotal-display" readonly value="₱0.00"></td>
      <td><button type="button" class="remove-row-btn" title="Remove">&times;</button></td>
    `;
    itemsBody.appendChild(tr);
    bindRow(tr);
  }

  function bindRow(tr) {
    const select = tr.querySelector('.product-select');
    const qty = tr.querySelector('.qty-input');
    const price = tr.querySelector('.price-input');
    const subtotalDisplay = tr.querySelector('.subtotal-display');
    const removeBtn = tr.querySelector('.remove-row-btn');

    select.addEventListener('change', () => {
      const opt = select.options[select.selectedIndex];
      const p = opt.dataset.price;
      if (p !== undefined) {
        price.value = parseFloat(p).toFixed(2);
      }
      recalcRow();
    });

    [qty, price].forEach(el => el.addEventListener('input', recalcRow));

    function recalcRow() {
      const q = parseFloat(qty.value) || 0;
      const p = parseFloat(price.value) || 0;
      subtotalDisplay.value = '₱' + (q * p).toFixed(2);
      recalcGrandTotal();
    }

    removeBtn.addEventListener('click', () => {
      if (itemsBody.children.length > 1) {
        tr.remove();
        recalcGrandTotal();
      }
    });
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

  document.getElementById('addRowBtn')?.addEventListener('click', addRow);

  // Start with one row.
    if (products.length) addRow();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

</body>
</html>