<?php
/**
 * users.php
 * Sales and Expense Monitoring System (SEMS)
 *
 * Admin-only. This is the first page in the system with real
 * page-level role enforcement (rubric requirement 1 and 11) — a
 * logged-in staff account is redirected away even if they type this
 * URL directly, not just hidden from the sidebar.
 *
 * Unlike signup.php (which only ever creates 'staff' accounts), this
 * page lets an admin create accounts of either role, edit existing
 * accounts, and delete them — with safeguards against locking
 * yourself out (can't delete your own account, can't delete the last
 * remaining admin).
 */

session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: dashboard.php?denied=1');
    exit;
}

$activePage = 'users';
$fullName   = $_SESSION['full_name'] ?? 'User';
$role       = $_SESSION['role'] ?? 'staff';

$error   = $_SESSION['users_error'] ?? null;
$success = $_SESSION['users_success'] ?? null;
unset($_SESSION['users_error'], $_SESSION['users_success']);

$users = [];
$editingUser = null;
$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$dataUnavailable = false;

try {
    if ($editId > 0) {
        $stmt = $pdo->prepare('SELECT id, username, role, full_name FROM users WHERE id = :id');
        $stmt->execute(['id' => $editId]);
        $editingUser = $stmt->fetch();
    }

    $users = $pdo->query('SELECT id, username, role, full_name, created_at FROM users ORDER BY created_at ASC')->fetchAll();

} catch (PDOException $e) {
    error_log('Users page query failed: ' . $e->getMessage());
    $dataUnavailable = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>User accounts — SEMS</title>

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

  .you-tag {
    font-size: 0.7rem; background: rgba(43,86,70,0.12); color: var(--ledger-green);
    padding: 0.1rem 0.4rem; border-radius: 3px; margin-left: 0.4rem;
  }
</style>
</head>
<body>

<div class="layout">

  <?php include __DIR__ . '/includes/sidebar.php'; ?>

  <main class="main">
    <div class="topbar">
      <h1>User accounts</h1>
      <div class="welcome">
        <?php echo htmlspecialchars($fullName); ?>
        <span class="role-badge"><?php echo htmlspecialchars($role); ?></span>
      </div>
    </div>

    <?php if ($dataUnavailable): ?>
      <div class="notice-banner">Unable to load account data right now. Please try again shortly.</div>
    <?php endif; ?>

    <?php if ($success): ?><div class="success-banner"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="error-banner"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <div class="split-panels">
      <div class="panel">
        <h2><?php echo $editingUser ? 'Edit account' : 'Create account'; ?></h2>

        <?php if ($editingUser): ?>
          <div class="edit-banner">
            Editing "<?php echo htmlspecialchars($editingUser['username']); ?>"
            <a href="users.php">Cancel edit</a>
          </div>
        <?php endif; ?>

        <form action="users_process.php" method="POST">
          <input type="hidden" name="action" value="<?php echo $editingUser ? 'update' : 'create'; ?>">
          <?php if ($editingUser): ?>
            <input type="hidden" name="user_id" value="<?php echo (int) $editingUser['id']; ?>">
          <?php endif; ?>

          <div class="field">
            <label for="full_name">Full name</label>
            <input type="text" id="full_name" name="full_name" required
                   value="<?php echo htmlspecialchars($editingUser['full_name'] ?? ''); ?>">
          </div>

          <div class="field">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" required
                   value="<?php echo htmlspecialchars($editingUser['username'] ?? ''); ?>">
          </div>

          <div class="field">
            <label for="role">Role</label>
            <select id="role" name="role" required>
              <option value="staff" <?php echo (($editingUser['role'] ?? '') === 'staff') ? 'selected' : ''; ?>>Staff</option>
              <option value="admin" <?php echo (($editingUser['role'] ?? '') === 'admin') ? 'selected' : ''; ?>>Admin</option>
            </select>
          </div>

          <div class="field">
            <label for="password"><?php echo $editingUser ? 'New password' : 'Password'; ?></label>
            <input type="password" id="password" name="password" <?php echo $editingUser ? '' : 'required'; ?>>
            <?php if ($editingUser): ?>
              <div class="hint" style="font-size:0.75rem; color:rgba(22,33,28,0.5); margin-top:0.35rem;">Leave blank to keep the current password.</div>
            <?php endif; ?>
          </div>

          <button type="submit" class="btn btn-primary"><?php echo $editingUser ? 'Update account' : 'Create account'; ?></button>
        </form>
      </div>

      <div class="panel">
        <h2>All accounts</h2>
        <?php if (empty($users)): ?>
          <p class="empty-state">No accounts found.</p>
        <?php else: ?>
          <table class="data-table">
            <thead><tr><th>Full name</th><th>Username</th><th>Role</th><th>Created</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($users as $u): ?>
                <tr>
                  <td>
                    <?php echo htmlspecialchars($u['full_name']); ?>
                    <?php if ((int) $u['id'] === (int) $_SESSION['user_id']): ?><span class="you-tag">You</span><?php endif; ?>
                  </td>
                  <td><?php echo htmlspecialchars($u['username']); ?></td>
                  <td><span class="role-badge" style="margin-left:0;"><?php echo htmlspecialchars($u['role']); ?></span></td>
                  <td><?php echo date('M j, Y', strtotime($u['created_at'])); ?></td>
                  <td>
                    <a href="users.php?edit=<?php echo (int) $u['id']; ?>" class="btn btn-secondary btn-sm">Edit</a>
                    <?php if ((int) $u['id'] !== (int) $_SESSION['user_id']): ?>
                      <form action="users_process.php" method="POST" style="display:inline" onsubmit="return confirm('Delete this account? This cannot be undone.');">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="user_id" value="<?php echo (int) $u['id']; ?>">
                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                      </form>
                    <?php endif; ?>
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