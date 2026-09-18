<?php
/**
 * signup.php
 * Sales and Expense Monitoring System (SEMS)
 *
 * Public registration page — but only ever creates 'staff' accounts.
 * Role is never taken from the form (security: prevents anyone from
 * signing themselves up as admin). Admin accounts should be created
 * directly in the database or, later, through an admin-only user
 * management page.
 */

session_start();

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error   = $_SESSION['signup_error'] ?? null;
$old     = $_SESSION['signup_old'] ?? [];
unset($_SESSION['signup_error'], $_SESSION['signup_old']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Create staff account — SEMS</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600&family=IBM+Plex+Sans:wght@400;500;600&display=swap" rel="stylesheet">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

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
  html, body { height: 100%; }

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

  .brand-mark { position: relative; z-index: 1; }

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

  .brand-footer {
    position: relative;
    z-index: 1;
    font-size: 0.8125rem;
    color: rgba(239, 236, 227, 0.6);
  }

  .form-panel {
    flex: 1 1 420px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 3rem 2rem;
    background: var(--paper);
  }

  .form-wrap { width: 100%; max-width: 380px; }

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
    margin: 0 0 2rem;
    line-height: 1.5;
  }

  .field { margin-bottom: 1.4rem; }

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

  .field .hint {
    font-size: 0.75rem;
    color: rgba(22, 33, 28, 0.5);
    margin-top: 0.35rem;
  }

  .btn-signup {
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
    margin-top: 0.5rem;
  }

  .btn-signup:hover { background: var(--gold-hover); }

  .btn-signup:focus-visible {
    outline: 2px solid var(--ledger-green);
    outline-offset: 2px;
  }

  .signin-note {
    margin-top: 1.75rem;
    font-size: 0.875rem;
    color: rgba(22, 33, 28, 0.65);
  }

  .signin-note a {
    color: var(--gold);
    text-decoration: none;
    font-weight: 500;
  }

  .signin-note a:hover { text-decoration: underline; color: var(--gold-hover); }

  .alert-banner {
    background: rgba(179, 38, 30, 0.08);
    border-left: 3px solid var(--error-red);
    color: var(--error-red);
    font-size: 0.875rem;
    padding: 0.75rem 1rem;
    margin-bottom: 1.75rem;
  }

  @media (max-width: 767px) {
    .brand-panel { padding: 2.75rem 1.75rem; }
    .brand-mark .wordmark { font-size: 2.1rem; }
    .form-panel { padding: 2.75rem 1.75rem; }
  }
</style>
</head>
<body>

<div class="split">

  <div class="brand-panel">
    <div class="brand-mark">
      <p class="wordmark">SEMS</p>
      <p class="tagline">Create a staff account to start recording sales and expenses.</p>
    </div>
    <p class="brand-footer">Sales and Expense Monitoring System</p>
  </div>

  <div class="form-panel">
    <div class="form-wrap">
      <h1>Create staff account</h1>
      <p class="sub">This creates a staff-level account. Administrator accounts are set up separately.</p>

      <?php if ($error): ?>
        <div class="alert-banner"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>

      <form action="signup_process.php" method="POST" novalidate>
        <div class="field">
          <label for="full_name">Full name</label>
          <input type="text" id="full_name" name="full_name" required
                 value="<?php echo htmlspecialchars($old['full_name'] ?? ''); ?>">
        </div>

        <div class="field">
          <label for="username">Username</label>
          <input type="text" id="username" name="username" autocomplete="username" required
                 value="<?php echo htmlspecialchars($old['username'] ?? ''); ?>">
          <div class="hint">At least 4 characters, letters/numbers/underscore only.</div>
        </div>

        <div class="field">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" autocomplete="new-password" required>
          <div class="hint">At least 6 characters.</div>
        </div>

        <div class="field">
          <label for="confirm_password">Confirm password</label>
          <input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password" required>
        </div>

        <button type="submit" class="btn-signup">Create account</button>
      </form>

      <p class="signin-note">Already have an account? <a href="login.php">Sign in</a></p>
    </div>
  </div>

</div>

</body>
</html>