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
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600&family=IBM+Plex+Sans:wght@400;500;600&display=swap" rel="stylesheet">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link rel="icon" href="favicon.svg" type="image/svg+xml">

<style>
  :root {
    --ledger-green: #1E3D32;
    --ledger-green-light: #2B5646;
    --paper: #EFECE3;
    --ink: #16211C;
    --gold: #B8860B;
    --gold-hover: #9C7109;
    --error-red: #B3261E;
  }

  * { box-sizing: border-box; }

  html, body {
    height: 100%;
  }

  body {
    margin: 0;
    font-family: 'IBM Plex Sans', sans-serif;
    color: var(--ink);
    background: var(--paper);
  }

  .split {
    min-height: 100vh;
    display: flex;
    flex-wrap: wrap;
  }

  /* ---------- Left panel: brand ---------- */
  .brand-panel {
    flex: 1 1 420px;
    background: var(--ledger-green);
    color: var(--paper);
    padding: 4rem 3.5rem;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    position: relative;
    overflow: hidden;
  }

  /* Ledger-rule texture: faint horizontal lines like a bookkeeping page */
  .brand-panel::before {
    content: "";
    position: absolute;
    inset: 0;
    background-image: repeating-linear-gradient(
      to bottom,
      rgba(239, 236, 227, 0.06) 0px,
      rgba(239, 236, 227, 0.06) 1px,
      transparent 1px,
      transparent 48px
    );
    pointer-events: none;
  }

  .brand-mark {
    position: relative;
    z-index: 1;
  }

  .brand-mark .wordmark {
    font-family: 'Fraunces', serif;
    font-weight: 500;
    font-size: 2.75rem;
    letter-spacing: -0.01em;
    margin: 0 0 0.75rem;
    line-height: 1.05;
  }

  .brand-mark .tagline {
    font-size: 1rem;
    line-height: 1.6;
    max-width: 34ch;
    color: rgba(239, 236, 227, 0.82);
    margin: 0;
  }

  .ledger-icon {
    position: relative;
    z-index: 1;
    width: 88px;
    height: 88px;
    opacity: 0.9;
  }

  .brand-footer {
    position: relative;
    z-index: 1;
    font-size: 0.8125rem;
    color: rgba(239, 236, 227, 0.6);
  }

  /* ---------- Right panel: form ---------- */
  .form-panel {
    flex: 1 1 420px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 3rem 2rem;
    background: var(--paper);
  }

  .form-wrap {
    width: 100%;
    max-width: 360px;
  }

  .form-wrap h1 {
    font-family: 'Fraunces', serif;
    font-weight: 500;
    font-size: 1.625rem;
    margin: 0 0 0.5rem;
    color: var(--ink);
  }

  .form-wrap .sub {
    font-size: 0.9375rem;
    color: rgba(22, 33, 28, 0.65);
    margin: 0 0 2.25rem;
    line-height: 1.5;
  }

  .field {
    margin-bottom: 1.5rem;
  }

  .field label {
    display: block;
    font-size: 0.8125rem;
    font-weight: 500;
    color: rgba(22, 33, 28, 0.75);
    margin-bottom: 0.4rem;
  }

  .field input {
    width: 100%;
    border: none;
    border-bottom: 1.5px solid rgba(22, 33, 28, 0.25);
    background: transparent;
    padding: 0.5rem 0.1rem;
    font-family: 'IBM Plex Sans', sans-serif;
    font-size: 1rem;
    color: var(--ink);
    border-radius: 0;
    transition: border-color 0.15s ease;
  }

  .field input:focus {
    outline: none;
    border-bottom-color: var(--gold);
    box-shadow: none;
  }

  .field input.is-invalid {
    border-bottom-color: var(--error-red);
  }

  .field-error {
    font-size: 0.8125rem;
    color: var(--error-red);
    margin-top: 0.4rem;
  }

  .row-between {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1.75rem;
  }

  .remember {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.875rem;
    color: rgba(22, 33, 28, 0.75);
  }

  .remember input {
    width: 15px;
    height: 15px;
    accent-color: var(--gold);
  }

  a.forgot {
    font-size: 0.875rem;
    color: var(--gold);
    text-decoration: none;
  }

  a.forgot:hover {
    text-decoration: underline;
    color: var(--gold-hover);
  }

  .btn-signin {
    width: 100%;
    background: var(--gold);
    color: var(--paper);
    border: none;
    padding: 0.75rem 1rem;
    font-size: 0.9375rem;
    font-weight: 500;
    font-family: 'IBM Plex Sans', sans-serif;
    border-radius: 4px;
    cursor: pointer;
    transition: background 0.15s ease;
  }

  .btn-signin:hover {
    background: var(--gold-hover);
  }

  .btn-signin:focus-visible {
    outline: 2px solid var(--ledger-green);
    outline-offset: 2px;
  }

  .role-note {
    margin-top: 1.75rem;
    font-size: 0.8125rem;
    color: rgba(22, 33, 28, 0.55);
    line-height: 1.5;
  }

  .alert-banner {
    background: rgba(179, 38, 30, 0.08);
    border-left: 3px solid var(--error-red);
    border-radius: 0;
    color: var(--error-red);
    font-size: 0.875rem;
    padding: 0.75rem 1rem;
    margin-bottom: 1.75rem;
  }

  .success-banner {
    background: rgba(43, 86, 70, 0.08);
    border-left: 3px solid var(--ledger-green-light);
    border-radius: 0;
    color: var(--ledger-green);
    font-size: 0.875rem;
    padding: 0.75rem 1rem;
    margin-bottom: 1.75rem;
  }

  .signup-note {
    margin-top: 1rem;
    font-size: 0.875rem;
    color: rgba(22, 33, 28, 0.65);
  }

  .signup-note a {
    color: var(--gold);
    text-decoration: none;
    font-weight: 500;
  }

  .signup-note a:hover { text-decoration: underline; color: var(--gold-hover); }

  @media (max-width: 767px) {
    .brand-panel {
      padding: 2.75rem 1.75rem;
    }
    .brand-mark .wordmark {
      font-size: 2.1rem;
    }
        .form-panel {
      padding: 2.75rem 1.75rem;
    }
  }

  /* ---------- Dark mode (auth pages) ---------- */
  body.dark {
    --ledger-green: #0e231e;
    --ledger-green-light: #15332a;
    --gold: #d9b558;
    --paper: #1a1f1d;
    --paper-card: #222825;
    --ink: #e8e2d6;
    --ink-dim: #c4bdae;
    --error-red: #d9534f;
  }
  body.dark .brand-panel { opacity: 0.92; }
  body.dark .form-panel { background: var(--paper); color: var(--ink); }
  body.dark .wordmark,
  body.dark .tagline,
  body.dark .brand-footer { color: var(--paper); }
  body.dark .form-wrap h1,
  body.dark .form-wrap .sub,
  body.dark .signup-note,
  body.dark .signin-note,
  body.dark .role-note { color: var(--ink); }
  body.dark .field input { background: #2a322f; border-color: #444d4a; color: var(--ink); }
  body.dark .alert-banner { background: rgba(217, 83, 79, 0.12); }
  body.dark .success-banner { background: rgba(67, 136, 99, 0.12); }
  body.dark .row-between label { color: var(--ink); }

  .theme-toggle-fixed {
    position: fixed;
    top: 1rem;
    right: 1rem;
    z-index: 100;
    background: rgba(255, 255, 255, 0.9);
    border: 1px solid rgba(22, 33, 28, 0.15);
    border-radius: 6px;
    padding: 0.4rem 0.6rem;
    cursor: pointer;
    font-size: 1.1rem;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
    transition: all 0.2s ease;
    backdrop-filter: blur(4px);
  }
  body.dark .theme-toggle-fixed {
    background: rgba(26, 31, 29, 0.9);
    border-color: rgba(239, 236, 227, 0.15);
  }
  .theme-toggle-fixed:hover { opacity: 0.8; }
</style>
</head>
<body>

<button type="button" id="themeToggle" class="theme-toggle-fixed" aria-label="Toggle dark mode">
  <i class="bi bi-circle-half"></i>
</button>

<div class="split">

  <div class="brand-panel">
    <div class="brand-mark">
      <p class="wordmark">SEMS</p>
      <p class="tagline">Track daily sales and expenses in one place, and see how the business is really doing.</p>
    </div>

    <svg class="ledger-icon" viewBox="0 0 88 88" fill="none" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Ledger book icon">
      <rect x="14" y="10" width="52" height="68" rx="2" stroke="#EFECE3" stroke-width="2"/>
      <line x1="24" y1="26" x2="56" y2="26" stroke="#EFECE3" stroke-width="1.5"/>
      <line x1="24" y1="36" x2="56" y2="36" stroke="#EFECE3" stroke-width="1.5"/>
      <line x1="24" y1="46" x2="46" y2="46" stroke="#EFECE3" stroke-width="1.5"/>
      <circle cx="66" cy="62" r="14" fill="#1E3D32" stroke="#B8860B" stroke-width="2"/>
      <text x="66" y="67" font-family="Fraunces, serif" font-size="14" fill="#B8860B" text-anchor="middle">₱</text>
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