<?php
/**
 * includes/sidebar.php
 * Shared sidebar navigation for all authenticated pages.
 *
 * Expects these variables to already be set by the including page:
 * - $activePage : string, e.g. 'dashboard'|'products'|'sales'|'expenses'|'reports'|'users'
 * - $_SESSION['full_name'], $_SESSION['role'] : set at login
 */
$navFullName = $_SESSION['full_name'] ?? 'User';
$navRole     = $_SESSION['role'] ?? 'staff';

/**
 * Returns the CSS class for a sidebar nav item.
 */
function navClass($page, $activePage) {
    return $page === $activePage ? 'active' : '';
}

/**
 * Returns the initials for the user avatar badge.
 * e.g. "Jane Doe" -> "JD"
 */
function initials($fullName) {
    $parts = preg_split('/\s+/', trim($fullName));
    $initials = '';
    foreach ($parts as $part) {
        if ($part !== '') {
            $initials .= mb_substr($part, 0, 1);
        }
    }
    return strtoupper(mb_substr($initials, 0, 2));
}
?>
<aside class="sidebar">

  <div class="sidebar-brand">SEMS</div>

  <!-- User avatar at top (personalised navigation) -->
  <div class="sidebar-user">
    <div class="user-avatar"><?php echo htmlspecialchars(initials($navFullName)); ?></div>
    <div class="user-info">
      <div class="user-name"><?php echo htmlspecialchars($navFullName); ?></div>
      <span class="role-badge"><?php echo htmlspecialchars($navRole); ?></span>
    </div>
  </div>

  <ul class="sidebar-nav">
    <li><a href="dashboard.php" class="<?php echo navClass('dashboard', $activePage); ?>">
      <i class="bi bi-graph-up-arrow"></i>
      <span>Dashboard</span>
    </a></li>
    <li><a href="products.php" class="<?php echo navClass('products', $activePage); ?>">
      <i class="bi bi-box-seam"></i>
      <span>Products</span>
    </a></li>
    <li><a href="sales.php" class="<?php echo navClass('sales', $activePage); ?>">
      <i class="bi bi-receipt-cutoff"></i>
      <span>Sales</span>
    </a></li>
    <li><a href="expenses.php" class="<?php echo navClass('expenses', $activePage); ?>">
      <i class="bi bi-wallet2"></i>
      <span>Expenses</span>
    </a></li>
    <li><a href="reports.php" class="<?php echo navClass('reports', $activePage); ?>">
      <i class="bi bi-file-bar-chart"></i>
      <span>Reports</span>
    </a></li>
    <?php if ($navRole === 'admin'): ?>
    <li><a href="users.php" class="<?php echo navClass('users', $activePage); ?>">
      <i class="bi bi-people"></i>
      <span>User accounts</span>
    </a></li>
    <?php endif; ?>
  </ul>

  <!-- Theme toggle -->
  <div class="sidebar-footer">
    <button type="button" id="themeToggle" class="theme-toggle" aria-label="Toggle dark mode">
      <i class="bi bi-circle-half"></i>
      <span>Dark mode</span>
    </button>
    <hr>
    Signed in as <strong><?php echo htmlspecialchars($navFullName); ?></strong><br>
    <a href="logout.php">Sign out</a>
  </div>
</aside>