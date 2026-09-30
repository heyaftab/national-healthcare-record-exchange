<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../forgot_password.php');
}

if (!csrf_check($_POST['_csrf'] ?? null)) {
    $_SESSION['errors'] = ['Session expired. Please try again.'];
    redirect('../forgot_password.php');
}

$email = strtolower(trim($_POST['email'] ?? ''));

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['errors'] = ['Please enter a valid email address.'];
    $_SESSION['old'] = ['email' => $email];
    redirect('../forgot_password.php');
}

try {
    $stmt = db()->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);

        $stmt = db()->prepare('DELETE FROM password_resets WHERE user_id = ?');
        $stmt->execute([(int)$user['id']]);

        $stmt = db()->prepare(
            'INSERT INTO password_resets (user_id, token_hash, expires_at)
             VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))'
        );
        $stmt->execute([(int)$user['id'], $tokenHash]);

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $basePath = dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php', 2);
        $basePath = $basePath === '/' ? '' : $basePath;
        $resetUrl = $scheme . $host . $basePath . '/reset_password.php?token=' . urlencode($token);

        $_SESSION['reset_url'] = $resetUrl;
        $_SESSION['success'] = 'A password reset link has been created. Open the link below to continue.';
    } else {
        $_SESSION['success'] = 'If an account exists for that email, a password reset link has been created.';
    }

    redirect('../forgot_password.php');
} catch (PDOException $e) {
    $_SESSION['errors'] = ['Something went wrong. Please try again later.'];
    $_SESSION['old'] = ['email' => $email];
    redirect('../forgot_password.php');
}
