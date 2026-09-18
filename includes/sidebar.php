<?php
/**
 * includes/sidebar.php
 * Shared sidebar navigation for all authenticated pages.
 *
 * Expects these variables to already be set by the including page:
 * - $activePage : string, one of 'dashboard'|'sales'|'expenses'|'reports'|'users'
 * - $_SESSION['full_name'], $_SESSION['role'] : set at login
 */
$navFullName = $_SESSION['full_name'] ?? 'User';
$navRole     = $_SESSION['role'] ?? 'staff';

function navClass($page, $activePage) {
    return $page === $activePage ? 'active' : '';
}
?>
<aside class="sidebar">
  <div class="sidebar-brand">SEMS</div>
  <ul class="sidebar-nav">
    <li><a href="dashboard.php" class="<?php echo navClass('dashboard', $activePage); ?>">Dashboard</a></li>
    <li><a href="products.php" class="<?php echo navClass('products', $activePage); ?>">Products</a></li>
    <li><a href="sales.php" class="<?php echo navClass('sales', $activePage); ?>">Sales</a></li>
    <li><a href="expenses.php" class="<?php echo navClass('expenses', $activePage); ?>">Expenses</a></li>
    <li><a href="reports.php" class="<?php echo navClass('reports', $activePage); ?>">Reports</a></li>
    <?php if ($navRole === 'admin'): ?>
    <li><a href="users.php" class="<?php echo navClass('users', $activePage); ?>">User accounts</a></li>
    <?php endif; ?>
  </ul>
  <div class="sidebar-footer">
    Signed in as <strong><?php echo htmlspecialchars($navFullName); ?></strong><br>
    <a href="logout.php">Sign out</a>
  </div>
</aside>