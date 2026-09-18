<?php
/**
 * signup_process.php
 * Handles the staff sign-up form submission from signup.php.
 *
 * Flow: Input -> Validation -> Duplicate check -> Hash -> Insert -> Redirect
 * Always creates role = 'staff'. The role is never read from the form,
 * so this endpoint can never be used to create an admin account.
 */

session_start();
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: signup.php');
    exit;
}

$fullName        = trim($_POST['full_name'] ?? '');
$username        = trim($_POST['username'] ?? '');
$password        = $_POST['password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

// Keep whatever was typed (except passwords) so the user isn't forced
// to retype everything after a validation error.
$_SESSION['signup_old'] = [
    'full_name' => $fullName,
    'username'  => $username,
];

// --- Validation (rubric requirement 6) ---
if ($fullName === '' || $username === '' || $password === '' || $confirmPassword === '') {
    $_SESSION['signup_error'] = 'Please fill in all fields.';
    header('Location: signup.php');
    exit;
}

if (!preg_match('/^[A-Za-z0-9_]{4,50}$/', $username)) {
    $_SESSION['signup_error'] = 'Username must be at least 4 characters and contain only letters, numbers, or underscores.';
    header('Location: signup.php');
    exit;
}

if (strlen($password) < 6) {
    $_SESSION['signup_error'] = 'Password must be at least 6 characters.';
    header('Location: signup.php');
    exit;
}

if ($password !== $confirmPassword) {
    $_SESSION['signup_error'] = 'Passwords do not match.';
    header('Location: signup.php');
    exit;
}

try {
    // Duplicate check up front for a clean message; the UNIQUE constraint
    // on username is still the real guarantee against race conditions.
    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = :username LIMIT 1');
    $stmt->execute(['username' => $username]);

    if ($stmt->fetch()) {
        $_SESSION['signup_error'] = 'That username is already taken. Please choose another.';
        header('Location: signup.php');
        exit;
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);

    $stmt = $pdo->prepare(
        'INSERT INTO users (username, password, role, full_name)
         VALUES (:username, :password, :role, :full_name)'
    );
    $stmt->execute([
        'username'  => $username,
        'password'  => $hash,
        'role'      => 'staff',
        'full_name' => $fullName,
    ]);

    unset($_SESSION['signup_old']);
    $_SESSION['login_success'] = 'Account created. You can sign in now.';
    header('Location: login.php');
    exit;

} catch (PDOException $e) {
    // Covers the race-condition case (duplicate username inserted between
    // the check and the insert) and any other DB failure.
    error_log('Signup failed: ' . $e->getMessage());

    if ($e->getCode() === '23000') {
        $_SESSION['signup_error'] = 'That username is already taken. Please choose another.';
    } else {
        $_SESSION['signup_error'] = 'Unable to create the account right now. Please try again.';
    }
    header('Location: signup.php');
    exit;
}