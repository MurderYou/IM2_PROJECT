<?php
/**
 * dashboard.php
 * Sales and Expense Monitoring System (SEMS)
 *
 * Requires an active session (set by auth.php on successful login).
 * Pulls summary figures and chart data from the database. If the
 * sales/expenses tables don't have data yet (or don't exist yet),
 * this page degrades gracefully instead of crashing — see the
 * try/catch around the data section below.
 */

session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$activePage = 'dashboard';
$fullName   = $_SESSION['full_name'] ?? 'User';
$role       = $_SESSION['role'] ?? 'staff';

// --- Defaults, used if data isn't available yet ---
$todaySales      = 0;
$todayExpenses   = 0;
$monthSales      = 0;
$monthExpenses   = 0;
$trendLabels     = [];
$trendSales      = [];
$trendExpenses   = [];
$categoryLabels  = [];
$categoryAmounts = [];
$recentActivity  = [];
$dataUnavailable = false;

try {
    $stmt = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) AS total FROM sales WHERE DATE(sale_date) = CURDATE()");
    $todaySales = (float) $stmt->fetch()['total'];

    $stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) AS total FROM expenses WHERE DATE(expense_date) = CURDATE()");
    $todayExpenses = (float) $stmt->fetch()['total'];

    $stmt = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) AS total FROM sales WHERE YEAR(sale_date) = YEAR(CURDATE()) AND MONTH(sale_date) = MONTH(CURDATE())");
    $monthSales = (float) $stmt->fetch()['total'];

    $stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) AS total FROM expenses WHERE YEAR(expense_date) = YEAR(CURDATE()) AND MONTH(expense_date) = MONTH(CURDATE())");
    $monthExpenses = (float) $stmt->fetch()['total'];

    $days = [];
    for ($i = 13; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i days"));
        $days[$d] = ['sales' => 0.0, 'expenses' => 0.0];
    }

    $stmt = $pdo->query("SELECT DATE(sale_date) AS d, SUM(total_amount) AS total
                          FROM sales
                          WHERE sale_date >= CURDATE() - INTERVAL 13 DAY
                          GROUP BY DATE(sale_date)");
    foreach ($stmt->fetchAll() as $row) {
        if (isset($days[$row['d']])) $days[$row['d']]['sales'] = (float) $row['total'];
    }

    $stmt = $pdo->query("SELECT DATE(expense_date) AS d, SUM(amount) AS total
                          FROM expenses
                          WHERE expense_date >= CURDATE() - INTERVAL 13 DAY
                          GROUP BY DATE(expense_date)");
    foreach ($stmt->fetchAll() as $row) {
        if (isset($days[$row['d']])) $days[$row['d']]['expenses'] = (float) $row['total'];
    }

    foreach ($days as $date => $vals) {
        $trendLabels[]   = date('M j', strtotime($date));
        $trendSales[]    = $vals['sales'];
        $trendExpenses[] = $vals['expenses'];
    }

    $stmt = $pdo->query("SELECT ec.name AS category, SUM(e.amount) AS total
                          FROM expenses e
                          JOIN expense_categories ec ON ec.id = e.category_id
                          WHERE YEAR(e.expense_date) = YEAR(CURDATE()) AND MONTH(e.expense_date) = MONTH(CURDATE())
                          GROUP BY ec.name
                          ORDER BY total DESC");
    foreach ($stmt->fetchAll() as $row) {
        $categoryLabels[]  = $row['category'];
        $categoryAmounts[] = (float) $row['total'];
    }

    $stmt = $pdo->query("
        (SELECT 'Sale' AS type, sale_date AS activity_date, total_amount AS amount, CONCAT('Sale #', id) AS label FROM sales)
        UNION ALL
        (SELECT 'Expense' AS type, expense_date AS activity_date, amount, description AS label FROM expenses)
        ORDER BY activity_date DESC
        LIMIT 6
    ");
    $recentActivity = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log('Dashboard data query failed: ' . $e->getMessage());
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
<title>Dashboard — SEMS</title>

<?php include __DIR__ . '/includes/head.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
</head>
<body>

<div class="layout">

  <?php include __DIR__ . '/includes/sidebar.php'; ?>

  <main class="main">
    <div class="topbar">
      <h1>Dashboard</h1>
      <div class="welcome">
        Welcome back, <?php echo htmlspecialchars($fullName); ?>
        <span class="role-badge"><?php echo htmlspecialchars($role); ?></span>
      </div>
    </div>

    <?php if (isset($_GET['denied'])): ?>
      <div class="error-banner">You don't have permission to access that page.</div>
    <?php endif; ?>

    <?php if ($dataUnavailable): ?>
      <div class="notice-banner">
        Sales and expense figures will appear here once transactions are recorded and the database is connected.
      </div>
    <?php endif; ?>

    <?php include __DIR__ . '/includes/flash.php'; ?>

    <div class="card-row">
      <div class="summary-card">
        <div class="card-icon"><i class="bi bi-sun"></i></div>
        <div class="label">Today's sales</div>
        <div class="value"><?php echo pesos($todaySales); ?></div>
      </div>
      <div class="summary-card expense-accent">
        <div class="card-icon"><i class="bi bi-basket"></i></div>
        <div class="label">Today's expenses</div>
        <div class="value"><?php echo pesos($todayExpenses); ?></div>
      </div>
      <div class="summary-card">
        <div class="card-icon"><i class="bi bi-tree"></i></div>
        <div class="label">This month's sales</div>
        <div class="value"><?php echo pesos($monthSales); ?></div>
      </div>
      <div class="summary-card expense-accent">
        <div class="card-icon"><i class="bi bi-wallet2"></i></div>
        <div class="label">This month's expenses</div>
        <div class="value"><?php echo pesos($monthExpenses); ?></div>
      </div>
    </div>

    <div class="chart-row">
      <div class="panel">
        <h2>Sales &amp; expenses, last 14 days</h2>
        <div class="chart-wrap">
          <canvas id="trendChart"></canvas>
        </div>
      </div>
      <div class="panel">
        <h2>Expenses by category</h2>
        <div class="chart-wrap">
          <?php if (empty($categoryLabels)): ?>
            <p class="empty-state">No expenses recorded this month yet.</p>
          <?php else: ?>
            <canvas id="categoryChart"></canvas>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="panel">
      <h2>Recent activity</h2>
      <?php if (empty($recentActivity)): ?>
        <p class="empty-state">No sales or expenses recorded yet.</p>
      <?php else: ?>
        <table class="data-table">
          <thead>
            <tr><th>Type</th><th>Description</th><th>Date</th><th>Amount</th></tr>
          </thead>
          <tbody>
            <?php foreach ($recentActivity as $row): ?>
              <tr>
                <td><span class="type-pill <?php echo $row['type'] === 'Sale' ? 'sale' : 'expense'; ?>"><?php echo htmlspecialchars($row['type']); ?></span></td>
                <td><?php echo htmlspecialchars($row['label']); ?></td>
                <td><?php echo date('M j, Y', strtotime($row['activity_date'])); ?></td>
                <td><?php echo pesos($row['amount']); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </main>

</div>

<script>
  const trendLabels   = <?php echo json_encode($trendLabels); ?>;
  const trendSales    = <?php echo json_encode($trendSales); ?>;
  const trendExpenses = <?php echo json_encode($trendExpenses); ?>;
  const categoryLabels  = <?php echo json_encode($categoryLabels); ?>;
  const categoryAmounts = <?php echo json_encode($categoryAmounts); ?>;

  // Charts read colours from the CSS tokens in style.css so they follow
  // the organic palette and re-theme when dark mode is toggled.
  // Built on DOMContentLoaded so the footer has applied the saved theme first.
  var charts = [];

  function cssVar(name) {
    return getComputedStyle(document.body).getPropertyValue(name).trim();
  }

  function withAlpha(hex, alpha) {
    var n = parseInt(hex.replace('#', ''), 16);
    return 'rgba(' + (n >> 16 & 255) + ',' + (n >> 8 & 255) + ',' + (n & 255) + ',' + alpha + ')';
  }

  function verticalFill(color) {
    return function (context) {
      var area = context.chart.chartArea;
      if (!area) return withAlpha(color, 0.15);
      var g = context.chart.ctx.createLinearGradient(0, area.top, 0, area.bottom);
      g.addColorStop(0, withAlpha(color, 0.3));
      g.addColorStop(1, withAlpha(color, 0));
      return g;
    };
  }

  function buildCharts() {
    charts.forEach(function (c) { c.destroy(); });
    charts = [];

    var ink      = cssVar('--ink');
    var line     = cssVar('--line');
    var ochre    = cssVar('--ochre');
    var clay     = cssVar('--clay');
    var linen    = cssVar('--linen');
    var font     = { family: 'Nunito', weight: '600' };

    Chart.defaults.font.family = 'Nunito';
    Chart.defaults.color = ink;

    var tooltip = {
      backgroundColor: cssVar('--forest'),
      titleColor: '#F3EEE2',
      bodyColor: '#F3EEE2',
      padding: 12,
      cornerRadius: 14,
      boxPadding: 4,
      usePointStyle: true
    };

    if (trendLabels.length) {
      charts.push(new Chart(document.getElementById('trendChart'), {
        type: 'line',
        data: {
          labels: trendLabels,
          datasets: [
            { label: 'Sales', data: trendSales, borderColor: ochre, backgroundColor: verticalFill(ochre),
              borderWidth: 2.5, cubicInterpolationMode: 'monotone', fill: true, pointRadius: 0, pointHoverRadius: 5,
              pointBackgroundColor: ochre, pointBorderColor: linen, pointBorderWidth: 2 },
            { label: 'Expenses', data: trendExpenses, borderColor: clay, backgroundColor: verticalFill(clay),
              borderWidth: 2.5, cubicInterpolationMode: 'monotone', fill: true, pointRadius: 0, pointHoverRadius: 5,
              pointBackgroundColor: clay, pointBorderColor: linen, pointBorderWidth: 2 }
          ]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          interaction: { mode: 'index', intersect: false },
          plugins: {
            legend: { position: 'bottom', labels: { usePointStyle: true, pointStyle: 'circle', boxWidth: 8, padding: 18, font: font, color: ink } },
            tooltip: tooltip
          },
          scales: {
            y: { beginAtZero: true, border: { display: false }, ticks: { font: font, color: ink, padding: 8 }, grid: { color: line } },
            x: { border: { display: false }, ticks: { font: font, color: ink, maxRotation: 0, autoSkipPadding: 12 }, grid: { display: false } }
          }
        }
      }));
    }

    if (categoryLabels.length) {
      var palette = [cssVar('--moss'), ochre, clay, cssVar('--sage'), cssVar('--bark'), cssVar('--forest-2')];
      charts.push(new Chart(document.getElementById('categoryChart'), {
        type: 'doughnut',
        data: {
          labels: categoryLabels,
          datasets: [{ data: categoryAmounts, backgroundColor: palette, borderColor: linen, borderWidth: 4, borderRadius: 10, spacing: 2, hoverOffset: 6 }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          cutout: '64%',
          plugins: {
            legend: { position: 'bottom', labels: { usePointStyle: true, pointStyle: 'circle', boxWidth: 8, padding: 14, font: { family: 'Nunito', size: 11, weight: '600' }, color: ink } },
            tooltip: tooltip
          }
        }
      }));
    }
  }

  document.addEventListener('DOMContentLoaded', buildCharts);
  document.addEventListener('sems:themechange', function () {
    if (document.readyState !== 'loading') buildCharts();
  });
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

</body>
</html>