<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../login.php');
}

if (!csrf_check($_POST['_csrf'] ?? null)) {
    $_SESSION['errors'] = ['Session expired. Please try again.'];
    redirect('../login.php');
}

$token = (string)($_POST['token'] ?? '');
$password = (string)($_POST['password'] ?? '');
$confirm = (string)($_POST['confirm_password'] ?? '');

if ($token === '' || $password === '' || $confirm === '') {
    $_SESSION['errors'] = ['All fields are required.'];
    redirect('../reset_password.php?token=' . urlencode($token));
}

if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/', $password)) {
    $_SESSION['errors'] = ['Password must be 8+ characters with uppercase, lowercase, number, and symbol.'];
    redirect('../reset_password.php?token=' . urlencode($token));
}

if ($password !== $confirm) {
    $_SESSION['errors'] = ['Passwords do not match.'];
    redirect('../reset_password.php?token=' . urlencode($token));
}

try {
    $tokenHash = hash('sha256', $token);
    $stmt = db()->prepare(
        'SELECT pr.user_id FROM password_resets pr
         WHERE pr.token_hash = ? AND pr.expires_at > NOW() LIMIT 1'
    );
    $stmt->execute([$tokenHash]);
    $reset = $stmt->fetch();

    if (!$reset) {
        $_SESSION['errors'] = ['This reset link is invalid or has expired.'];
        redirect('../forgot_password.php');
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
    $stmt->execute([$hash, (int)$reset['user_id']]);

    $stmt = db()->prepare('DELETE FROM password_resets WHERE token_hash = ?');
    $stmt->execute([$tokenHash]);

    $_SESSION['success'] = 'Your password has been updated successfully. Please login with your new password.';
    redirect('../login.php');
} catch (PDOException $e) {
    $_SESSION['errors'] = ['Something went wrong. Please try again later.'];
    redirect('../forgot_password.php');
}
