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

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600&family=IBM+Plex+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="style.css">
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

    <div class="card-row">
      <div class="summary-card">
        <div class="label">Today's sales</div>
        <div class="value"><?php echo pesos($todaySales); ?></div>
      </div>
      <div class="summary-card expense-accent">
        <div class="label">Today's expenses</div>
        <div class="value"><?php echo pesos($todayExpenses); ?></div>
      </div>
      <div class="summary-card">
        <div class="label">This month's sales</div>
        <div class="value"><?php echo pesos($monthSales); ?></div>
      </div>
      <div class="summary-card expense-accent">
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

  if (trendLabels.length) {
    new Chart(document.getElementById('trendChart'), {
      type: 'line',
      data: {
        labels: trendLabels,
        datasets: [
          { label: 'Sales', data: trendSales, borderColor: '#B8860B', backgroundColor: 'rgba(184, 134, 11, 0.1)', tension: 0.3, fill: true },
          { label: 'Expenses', data: trendExpenses, borderColor: '#B3261E', backgroundColor: 'rgba(179, 38, 30, 0.08)', tension: 0.3, fill: true }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { family: 'IBM Plex Sans' } } } },
        scales: {
          y: { beginAtZero: true, ticks: { font: { family: 'IBM Plex Sans' } } },
          x: { ticks: { font: { family: 'IBM Plex Sans' } } }
        }
      }
    });
  }

  if (categoryLabels.length) {
    new Chart(document.getElementById('categoryChart'), {
      type: 'doughnut',
      data: {
        labels: categoryLabels,
        datasets: [{ data: categoryAmounts, backgroundColor: ['#1E3D32', '#B8860B', '#B3261E', '#2B5646', '#6b5209', '#8a3a34'], borderWidth: 0 }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { family: 'IBM Plex Sans', size: 11 } } } }
      }
    });
  }
</script>

</body>
</html>