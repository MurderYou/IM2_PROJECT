<?php
/**
 * reports.php
 * Sales and Expense Monitoring System (SEMS)
 *
 * Read-only reporting page — no separate _process.php needed since
 * nothing here writes to the database. Supports a custom date range
 * plus quick presets (Today, This Week, This Month), and is printable
 * via the browser (no PDF library, per the project's tech decisions).
 */

session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$activePage = 'reports';
$fullName   = $_SESSION['full_name'] ?? 'User';
$role       = $_SESSION['role'] ?? 'staff';

// --- Date range resolution ---
$preset = $_GET['preset'] ?? 'this_month';
$today  = date('Y-m-d');

switch ($preset) {
    case 'today':
        $from = $today;
        $to   = $today;
        break;
    case 'this_week':
        $from = date('Y-m-d', strtotime('monday this week'));
        $to   = $today;
        break;
    case 'this_month':
        $from = date('Y-m-01');
        $to   = $today;
        break;
    case 'custom':
        $from = $_GET['from'] ?? date('Y-m-01');
        $to   = $_GET['to'] ?? $today;
        break;
    default:
        $preset = 'this_month';
        $from = date('Y-m-01');
        $to   = $today;
}

// Guard against an invalid or reversed range.
if (strtotime($from) === false) $from = date('Y-m-01');
if (strtotime($to) === false)   $to   = $today;
if ($from > $to) [$from, $to] = [$to, $from];

$fromDateTime = $from . ' 00:00:00';
$toDateTime   = $to . ' 23:59:59';

$totalSales     = 0;
$totalExpenses  = 0;
$salesList      = [];
$expensesList   = [];
$categoryBreakdown = [];
$dataUnavailable = false;

try {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) AS total FROM sales WHERE sale_date BETWEEN :from AND :to");
    $stmt->execute(['from' => $fromDateTime, 'to' => $toDateTime]);
    $totalSales = (float) $stmt->fetch()['total'];

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) AS total FROM expenses WHERE expense_date BETWEEN :from AND :to");
    $stmt->execute(['from' => $fromDateTime, 'to' => $toDateTime]);
    $totalExpenses = (float) $stmt->fetch()['total'];

    $stmt = $pdo->prepare("
        SELECT s.id, s.sale_date, s.total_amount, u.full_name,
               (SELECT COUNT(*) FROM sale_items si WHERE si.sale_id = s.id) AS item_count
        FROM sales s
        LEFT JOIN users u ON u.id = s.user_id
        WHERE s.sale_date BETWEEN :from AND :to
        ORDER BY s.sale_date ASC
    ");
    $stmt->execute(['from' => $fromDateTime, 'to' => $toDateTime]);
    $salesList = $stmt->fetchAll();

    $stmt = $pdo->prepare("
        SELECT e.expense_date, e.description, e.amount, ec.name AS category_name
        FROM expenses e
        JOIN expense_categories ec ON ec.id = e.category_id
        WHERE e.expense_date BETWEEN :from AND :to
        ORDER BY e.expense_date ASC
    ");
    $stmt->execute(['from' => $fromDateTime, 'to' => $toDateTime]);
    $expensesList = $stmt->fetchAll();

    $stmt = $pdo->prepare("
        SELECT ec.name AS category, SUM(e.amount) AS total
        FROM expenses e
        JOIN expense_categories ec ON ec.id = e.category_id
        WHERE e.expense_date BETWEEN :from AND :to
        GROUP BY ec.name
        ORDER BY total DESC
    ");
    $stmt->execute(['from' => $fromDateTime, 'to' => $toDateTime]);
    $categoryBreakdown = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log('Reports query failed: ' . $e->getMessage());
    $dataUnavailable = true;
}

$netProfit = $totalSales - $totalExpenses;

function pesos($amount) {
    return '₱' . number_format((float) $amount, 2);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reports — SEMS</title>

<?php include __DIR__ . '/includes/head.php'; ?>

<style>
  .filter-row { display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: end; margin-bottom: 1.5rem; }
  .filter-row .field { margin-bottom: 0; min-width: 150px; }
  .filter-row .field label { font-size: 0.75rem; }

  .preset-tabs { display: flex; gap: 0.5rem; margin-bottom: 1.25rem; flex-wrap: wrap; }
  .preset-tabs a {
    padding: 0.4rem 0.9rem; border-radius: 4px; font-size: 0.875rem; text-decoration: none;
    color: var(--ink); background: rgba(22,33,28,0.06);
  }
  .preset-tabs a.active { background: var(--gold); color: var(--paper); font-weight: 500; }
  .preset-tabs a:hover:not(.active) { background: rgba(22,33,28,0.1); }

  .report-header { display: none; }

  .net-positive { color: var(--ledger-green); }
  .net-negative { color: var(--error-red); }

  .report-meta { font-size: 0.875rem; color: rgba(22,33,28,0.6); margin-bottom: 1.5rem; }

  @media print {
    .sidebar, .topbar .welcome, .filter-row, .preset-tabs, .no-print { display: none !important; }
    .layout { display: block; }
    .main { padding: 0; }
    body { background: #fff; }
    .panel { box-shadow: none; border: none; padding: 0.5rem 0; margin-bottom: 1rem; }
    .report-header { display: block; margin-bottom: 1.5rem; border-bottom: 2px solid #16211C; padding-bottom: 1rem; }
    .report-header h1 { font-family: 'Fraunces', serif; margin: 0 0 0.25rem; }
    .card-row { grid-template-columns: repeat(3, 1fr); }
  }
</style>
</head>
<body>

<div class="layout">

  <?php include __DIR__ . '/includes/sidebar.php'; ?>

  <main class="main">
    <div class="topbar">
      <h1>Reports</h1>
      <div class="welcome">
        <?php echo htmlspecialchars($fullName); ?>
        <span class="role-badge"><?php echo htmlspecialchars($role); ?></span>
      </div>
    </div>

    <div class="report-header">
      <h1>SEMS — Financial Report</h1>
      <div>Period: <?php echo date('M j, Y', strtotime($from)); ?> — <?php echo date('M j, Y', strtotime($to)); ?></div>
      <div>Generated: <?php echo date('M j, Y g:i A'); ?> by <?php echo htmlspecialchars($fullName); ?></div>
    </div>

    <?php if ($dataUnavailable): ?>
      <div class="notice-banner">Unable to load report data right now. Please try again shortly.</div>
    <?php endif; ?>

    <div class="preset-tabs no-print">
      <a href="reports.php?preset=today" class="<?php echo $preset === 'today' ? 'active' : ''; ?>">Today</a>
      <a href="reports.php?preset=this_week" class="<?php echo $preset === 'this_week' ? 'active' : ''; ?>">This week</a>
      <a href="reports.php?preset=this_month" class="<?php echo $preset === 'this_month' ? 'active' : ''; ?>">This month</a>
      <a href="reports.php?preset=custom&from=<?php echo $from; ?>&to=<?php echo $to; ?>" class="<?php echo $preset === 'custom' ? 'active' : ''; ?>">Custom range</a>
    </div>

        <form method="GET" class="filter-row no-print">
      <input type="hidden" name="preset" value="custom">
      <div class="field">
        <label for="from">From</label>
        <input type="date" id="from" name="from" value="<?php echo htmlspecialchars($from); ?>">
      </div>
      <div class="field">
        <label for="to">To</label>
        <input type="date" id="to" name="to" value="<?php echo htmlspecialchars($to); ?>">
      </div>
      <button type="submit" class="btn btn-secondary">Apply range</button>
      <button type="button" class="btn btn-secondary" onclick="exportTableToCSV('salesTable', 'sems-sales-export')"><i class="bi bi-download"></i> Export sales CSV</button>
      <button type="button" class="btn btn-secondary" onclick="exportTableToCSV('expensesTable', 'sems-expenses-export')"><i class="bi bi-download"></i> Export expenses CSV</button>
      <button type="button" class="btn btn-primary" onclick="window.print()">Print report</button>
    </form>

    <p class="report-meta no-print">
      Showing <?php echo date('M j, Y', strtotime($from)); ?> to <?php echo date('M j, Y', strtotime($to)); ?>
    </p>

    <div class="card-row">
      <div class="summary-card">
        <div class="label">Total sales</div>
        <div class="value"><?php echo pesos($totalSales); ?></div>
      </div>
      <div class="summary-card expense-accent">
        <div class="label">Total expenses</div>
        <div class="value"><?php echo pesos($totalExpenses); ?></div>
      </div>
      <div class="summary-card" style="border-top-color: <?php echo $netProfit >= 0 ? 'var(--ledger-green)' : 'var(--error-red)'; ?>;">
        <div class="label">Estimated net profit</div>
        <div class="value <?php echo $netProfit >= 0 ? 'net-positive' : 'net-negative'; ?>"><?php echo pesos($netProfit); ?></div>
      </div>
    </div>

    <div class="panel">
      <h2>Sales in this period</h2>
      <?php if (empty($salesList)): ?>
        <p class="empty-state">No sales recorded in this period.</p>
      <?php else: ?>
                <table class="data-table" id="salesTable">
          <thead><tr><th class="sortable">Date</th><th class="sortable">Recorded by</th><th class="sortable">Items</th><th class="sortable">Total</th></tr></thead>
          <tbody>
            <?php foreach ($salesList as $sale): ?>
              <tr>
                <td><?php echo date('M j, Y g:i A', strtotime($sale['sale_date'])); ?></td>
                <td><?php echo htmlspecialchars($sale['full_name'] ?? '—'); ?></td>
                <td><?php echo (int) $sale['item_count']; ?></td>
                <td><?php echo pesos($sale['total_amount']); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr><td colspan="3" style="text-align:right; font-weight:500;">Total</td><td style="font-weight:500;"><?php echo pesos($totalSales); ?></td></tr>
          </tfoot>
        </table>
      <?php endif; ?>
    </div>

    <div class="panel">
      <h2>Expenses in this period</h2>
      <?php if (empty($expensesList)): ?>
        <p class="empty-state">No expenses recorded in this period.</p>
      <?php else: ?>
                <table class="data-table" id="expensesTable">
          <thead><tr><th class="sortable">Date</th><th class="sortable">Category</th><th class="sortable">Description</th><th class="sortable">Amount</th></tr></thead>
          <tbody>
            <?php foreach ($expensesList as $exp): ?>
              <tr>
                <td><?php echo date('M j, Y', strtotime($exp['expense_date'])); ?></td>
                <td><?php echo htmlspecialchars($exp['category_name']); ?></td>
                <td><?php echo htmlspecialchars($exp['description']); ?></td>
                <td><?php echo pesos($exp['amount']); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr><td colspan="3" style="text-align:right; font-weight:500;">Total</td><td style="font-weight:500;"><?php echo pesos($totalExpenses); ?></td></tr>
          </tfoot>
        </table>
      <?php endif; ?>
    </div>

    <div class="panel">
            <h2>Expenses by category</h2>
      <?php if (empty($categoryBreakdown)): ?>
        <p class="empty-state">No expenses to break down for this period.</p>
      <?php else: ?>
        <button type="button" class="btn btn-secondary no-print" onclick="exportTableToCSV('categoryTable', 'sems-category-export')"><i class="bi bi-download"></i> Export category CSV</button>
        <table class="data-table" id="categoryTable">
          <thead><tr><th class="sortable">Category</th><th class="sortable">Total</th><th>% of expenses</th></tr></thead>
          <tbody>
            <?php foreach ($categoryBreakdown as $row): ?>
              <tr>
                <td><?php echo htmlspecialchars($row['category']); ?></td>
                <td><?php echo pesos($row['total']); ?></td>
                <td><?php echo $totalExpenses > 0 ? number_format(($row['total'] / $totalExpenses) * 100, 1) . '%' : '—'; ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
    </main>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

</body>
</html>