<?php
/**
 * users_process.php
 * Handles create/update/delete for user accounts. Admin-only — this is
 * re-checked here independently of users.php, since a non-admin could
 * otherwise submit a POST request directly to this file without ever
 * seeing the page.
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: users.php');
    exit;
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'create':
        handleCreate($pdo);
        break;
    case 'update':
        handleUpdate($pdo);
        break;
    case 'delete':
        handleDelete($pdo);
        break;
    default:
        header('Location: users.php');
        exit;
}

function handleCreate(PDO $pdo) {
    $fullName = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $role     = $_POST['role'] ?? '';
    $password = $_POST['password'] ?? '';

    if ($fullName === '' || $username === '' || $password === '') {
        $_SESSION['users_error'] = 'Please fill in all required fields.';
        header('Location: users.php');
        exit;
    }

    if (!in_array($role, ['admin', 'staff'], true)) {
        $_SESSION['users_error'] = 'Please select a valid role.';
        header('Location: users.php');
        exit;
    }

    if (!preg_match('/^[A-Za-z0-9_]{4,50}$/', $username)) {
        $_SESSION['users_error'] = 'Username must be at least 4 characters and contain only letters, numbers, or underscores.';
        header('Location: users.php');
        exit;
    }

    if (strlen($password) < 6) {
        $_SESSION['users_error'] = 'Password must be at least 6 characters.';
        header('Location: users.php');
        exit;
    }

    try {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = :username');
        $stmt->execute(['username' => $username]);
        if ($stmt->fetch()) {
            $_SESSION['users_error'] = 'That username is already taken.';
            header('Location: users.php');
            exit;
        }

        $stmt = $pdo->prepare('INSERT INTO users (username, password, role, full_name) VALUES (:username, :password, :role, :full_name)');
        $stmt->execute([
            'username'  => $username,
            'password'  => password_hash($password, PASSWORD_BCRYPT),
            'role'      => $role,
            'full_name' => $fullName,
        ]);

        $_SESSION['users_success'] = 'Account created.';
        header('Location: users.php');
        exit;

    } catch (PDOException $e) {
        error_log('User creation failed: ' . $e->getMessage());
        $_SESSION['users_error'] = $e->getCode() === '23000'
            ? 'That username is already taken.'
            : 'Unable to create the account right now. Please try again.';
        header('Location: users.php');
        exit;
    }
}

function handleUpdate(PDO $pdo) {
    $userId   = (int) ($_POST['user_id'] ?? 0);
    $fullName = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $role     = $_POST['role'] ?? '';
    $password = $_POST['password'] ?? '';

    if ($userId <= 0 || $fullName === '' || $username === '') {
        $_SESSION['users_error'] = 'Please fill in all required fields.';
        header('Location: users.php');
        exit;
    }

    if (!in_array($role, ['admin', 'staff'], true)) {
        $_SESSION['users_error'] = 'Please select a valid role.';
        header('Location: users.php');
        exit;
    }

    if (!preg_match('/^[A-Za-z0-9_]{4,50}$/', $username)) {
        $_SESSION['users_error'] = 'Username must be at least 4 characters and contain only letters, numbers, or underscores.';
        header('Location: users.php');
        exit;
    }

    // Prevent an admin from demoting themselves and losing access mid-session.
    if ($userId === (int) $_SESSION['user_id'] && $role !== 'admin') {
        $_SESSION['users_error'] = 'You cannot change your own role away from admin.';
        header('Location: users.php');
        exit;
    }

    try {
        // Guard against demoting/removing the last remaining admin.
        if ($role !== 'admin') {
            $stmt = $pdo->prepare("SELECT role FROM users WHERE id = :id");
            $stmt->execute(['id' => $userId]);
            $current = $stmt->fetch();

            if ($current && $current['role'] === 'admin') {
                $countStmt = $pdo->query("SELECT COUNT(*) AS c FROM users WHERE role = 'admin'");
                if ((int) $countStmt->fetch()['c'] <= 1) {
                    $_SESSION['users_error'] = 'Cannot change this account\'s role — it is the last remaining admin.';
                    header('Location: users.php');
                    exit;
                }
            }
        }

        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = :username AND id != :id');
        $stmt->execute(['username' => $username, 'id' => $userId]);
        if ($stmt->fetch()) {
            $_SESSION['users_error'] = 'That username is already taken.';
            header('Location: users.php');
            exit;
        }

        if ($password !== '') {
            if (strlen($password) < 6) {
                $_SESSION['users_error'] = 'Password must be at least 6 characters.';
                header('Location: users.php');
                exit;
            }
            $stmt = $pdo->prepare('UPDATE users SET full_name = :full_name, username = :username, role = :role, password = :password WHERE id = :id');
            $stmt->execute([
                'full_name' => $fullName,
                'username'  => $username,
                'role'      => $role,
                'password'  => password_hash($password, PASSWORD_BCRYPT),
                'id'        => $userId,
            ]);
        } else {
            $stmt = $pdo->prepare('UPDATE users SET full_name = :full_name, username = :username, role = :role WHERE id = :id');
            $stmt->execute([
                'full_name' => $fullName,
                'username'  => $username,
                'role'      => $role,
                'id'        => $userId,
            ]);
        }

        // Keep the current session in sync if the admin edited their own account.
        if ($userId === (int) $_SESSION['user_id']) {
            $_SESSION['full_name'] = $fullName;
            $_SESSION['username']  = $username;
            $_SESSION['role']      = $role;
        }

        $_SESSION['users_success'] = 'Account updated.';
        header('Location: users.php');
        exit;

    } catch (PDOException $e) {
        error_log('User update failed: ' . $e->getMessage());
        $_SESSION['users_error'] = $e->getCode() === '23000'
            ? 'That username is already taken.'
            : 'Unable to update the account right now. Please try again.';
        header('Location: users.php');
        exit;
    }
}

function handleDelete(PDO $pdo) {
    $userId = (int) ($_POST['user_id'] ?? 0);

    if ($userId <= 0) {
        $_SESSION['users_error'] = 'Invalid account reference.';
        header('Location: users.php');
        exit;
    }

    if ($userId === (int) $_SESSION['user_id']) {
        $_SESSION['users_error'] = 'You cannot delete your own account while signed in.';
        header('Location: users.php');
        exit;
    }

    try {
        $stmt = $pdo->prepare('SELECT role FROM users WHERE id = :id');
        $stmt->execute(['id' => $userId]);
        $target = $stmt->fetch();

        if (!$target) {
            $_SESSION['users_error'] = 'That account could not be found.';
            header('Location: users.php');
            exit;
        }

        if ($target['role'] === 'admin') {
            $countStmt = $pdo->query("SELECT COUNT(*) AS c FROM users WHERE role = 'admin'");
            if ((int) $countStmt->fetch()['c'] <= 1) {
                $_SESSION['users_error'] = 'Cannot delete the last remaining admin account.';
                header('Location: users.php');
                exit;
            }
        }

        $pdo->prepare('DELETE FROM users WHERE id = :id')->execute(['id' => $userId]);
        $_SESSION['users_success'] = 'Account deleted.';
        header('Location: users.php');
        exit;

    } catch (PDOException $e) {
        error_log('User deletion failed: ' . $e->getMessage());
        $_SESSION['users_error'] = 'Unable to delete this account right now. Please try again.';
        header('Location: users.php');
        exit;
    }
}