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

$email = strtolower(trim($_POST['email'] ?? ''));
$password = (string)($_POST['password'] ?? '');
$remember = !empty($_POST['remember_me']);

if ($email === '' || $password === '') {
    $_SESSION['errors'] = ['Email and password are required.'];
    $_SESSION['old'] = ['email' => $email];
    redirect('../login.php');
}

try {
    $stmt = db()->prepare(
        'SELECT COUNT(*) FROM login_attempts
         WHERE email = ? AND ip_address = ?
           AND attempted_at > (NOW() - INTERVAL ? MINUTE)'
    );
    $stmt->execute([$email, client_ip(), LOGIN_ATTEMPT_WINDOW_MINUTES]);

    if ((int)$stmt->fetchColumn() >= LOGIN_ATTEMPT_LIMIT) {
        $_SESSION['errors'] = [
            'Too many failed attempts. Please wait ' . LOGIN_ATTEMPT_WINDOW_MINUTES . ' minutes and try again.',
        ];
        $_SESSION['old'] = ['email' => $email];
        redirect('../login.php');
    }

    $stmt = db()->prepare(
        'SELECT id, fullname, email, role, password_hash FROM users WHERE email = ? LIMIT 1'
    );
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        $stmt = db()->prepare('INSERT INTO login_attempts (email, ip_address) VALUES (?, ?)');
        $stmt->execute([$email, client_ip()]);

        $_SESSION['errors'] = ['Invalid email or password.'];
        $_SESSION['old'] = ['email' => $email];
        redirect('../login.php');
    }

    login_user($user, $remember);

    $stmt = db()->prepare('DELETE FROM login_attempts WHERE email = ?');
    $stmt->execute([$email]);

    redirect($user['role'] === 'Hospital Admin' || $user['role'] === 'System Admin' ? '../admin_dashboard.php' : '../dashboard.php');
} catch (PDOException $e) {
    $_SESSION['errors'] = ['Something went wrong. Please try again later.'];
    redirect('../login.php');
}
