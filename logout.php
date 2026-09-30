<?php
declare(strict_types=1);

require_once __DIR__ . '/auth/auth_check.php';

if (!empty($_COOKIE[COOKIE_REMEMBER])) {
    try {
        $stmt = db()->prepare('DELETE FROM auth_tokens WHERE token_hash = ?');
        $stmt->execute([hash('sha256', $_COOKIE[COOKIE_REMEMBER])]);
    } catch (PDOException $e) {
    }

    setcookie(COOKIE_REMEMBER, '', [
        'expires' => time() - 3600,
        'path' => '/',
        'domain' => '',
        'secure' => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

session_destroy();
session_start_secure();
$_SESSION['success'] = 'You have been logged out safely.';

redirect('index.php');
