<?php
/**
 * login.php
 * Sales and Expense Monitoring System (SEMS)
 *
 * UI-first build: this page currently renders the login screen only.
 * Backend wiring (session check, PDO connection, password_verify,
 * role-based redirect) will be added in a later step once the
 * database and includes/ files are built, as agreed on.
 *
 * Planned integration points are marked with TODO comments below.
 */

session_start();

// If already logged in, skip straight to the dashboard.
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

// Pick up any error set by auth.php (wrong credentials, empty fields, DB issue).
$error = $_SESSION['login_error'] ?? null;
unset($_SESSION['login_error']);

// Pick up a success message, e.g. after creating an account via signup.php.
$success = $_SESSION['login_success'] ?? null;
unset($_SESSION['login_success']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sign in — SEMS</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght,SOFT@9..144,400..700,0..100&family=Nunito:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link rel="icon" href="favicon.svg" type="image/svg+xml">

<link rel="stylesheet" href="auth.css">
</head>
<body>

<button type="button" id="themeToggle" class="theme-toggle-fixed" aria-label="Toggle dark mode">
  <i class="bi bi-circle-half"></i>
</button>

<div class="split">

  <div class="brand-panel">
    <div class="brand-mark">
      <p class="wordmark">SEMS<em>.</em></p>
      <p class="tagline">Track daily sales and expenses in one place, and watch your business grow.</p>
      <ul class="brand-chips">
        <li><i class="bi bi-receipt-cutoff"></i> Sales</li>
        <li><i class="bi bi-wallet2"></i> Expenses</li>
        <li><i class="bi bi-graph-up-arrow"></i> Profit insights</li>
      </ul>
    </div>

    <svg class="sprout-art" viewBox="0 0 180 180" fill="none" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="A sprout growing from a peso coin">
      <ellipse cx="90" cy="160" rx="62" ry="10" fill="#A9BF98" opacity="0.18"/>
      <path d="M90 128V70" stroke="#A9BF98" stroke-width="4" stroke-linecap="round"/>
      <path class="leaf" d="M90 96C90 70 70 52 40 50c0 28 20 46 50 46Z" fill="#A9BF98"/>
      <path class="leaf right" d="M90 80c0-26 18-46 50-50 0 30-20 50-50 50Z" fill="#DDB15A"/>
      <path d="M90 96C78 86 64 72 56 60" stroke="#2F4A39" stroke-width="1.6" stroke-linecap="round" opacity="0.5"/>
      <path d="M90 80c10-12 22-28 36-38" stroke="#2F4A39" stroke-width="1.6" stroke-linecap="round" opacity="0.4"/>
      <circle cx="90" cy="132" r="26" fill="#C3922E"/>
      <circle cx="90" cy="132" r="19" stroke="#F3EEE2" stroke-opacity="0.45" stroke-width="2"/>
      <text x="90" y="141" font-family="Fraunces, serif" font-size="26" font-weight="600" fill="#F3EEE2" text-anchor="middle">₱</text>
    </svg>

    <p class="brand-footer">Sales and Expense Monitoring System</p>
  </div>

  <div class="form-panel">
    <div class="form-wrap">
      <h1>Sign in</h1>
      <p class="sub">Enter your account details to continue.</p>

      <?php if ($success): ?>
        <div class="success-banner"><?php echo htmlspecialchars($success); ?></div>
      <?php endif; ?>

      <?php if ($error): ?>
        <div class="alert-banner"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>

      <form action="auth.php" method="POST" novalidate>
        <div class="field">
          <label for="username">Username</label>
          <input type="text" id="username" name="username" autocomplete="username" required>
        </div>

        <div class="field">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" autocomplete="current-password" required>
        </div>

        <div class="row-between">
          <label class="remember">
            <input type="checkbox" name="remember">
            Keep me signed in
          </label>
          <a href="#" class="forgot">Forgot password?</a>
        </div>

        <button type="submit" class="btn-signin">Sign in</button>
      </form>

      <p class="role-note">Staff and administrator accounts use the same sign-in. Access to pages and actions is based on your assigned role.</p>
      <p class="signup-note">New staff member? <a href="signup.php">Create an account</a></p>
    </div>
  </div>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

</body>
</html>