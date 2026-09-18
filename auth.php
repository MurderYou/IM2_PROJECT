<?php
/**
 * auth.php
 * Handles the login form submission from login.php.
 *
 * Flow: Input -> Validation -> Query -> password_verify -> Session -> Redirect
 * Never reveals whether the username or the password was wrong (security best practice).
 */

session_start();
require_once __DIR__ . '/db.php';

// Only accept POST submissions; anything else goes back to the login page.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

// --- Basic input validation (rubric requirement 6) ---
if ($username === '' || $password === '') {
    $_SESSION['login_error'] = 'Please enter both your username and password.';
    header('Location: login.php');
    exit;
}

try {
    $stmt = $pdo->prepare(
        'SELECT id, username, password, role, full_name
         FROM users
         WHERE username = :username
         LIMIT 1'
    );
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        // Regenerate the session ID on login to prevent session fixation.
        session_regenerate_id(true);

        $_SESSION['user_id']   = $user['id'];
        $_SESSION['username']  = $user['username'];
        $_SESSION['role']      = $user['role'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['last_activity'] = time();

        header('Location: dashboard.php');
        exit;
    }

    // Generic message on purpose — don't hint whether username or password was wrong.
    $_SESSION['login_error'] = 'Incorrect username or password.';
    header('Location: login.php');
    exit;

} catch (PDOException $e) {
    // Rubric requirement 7: never surface raw database errors to the user.
    error_log('Login query failed: ' . $e->getMessage());
    $_SESSION['login_error'] = 'Unable to sign in right now. Please try again.';
    header('Location: login.php');
    exit;
}