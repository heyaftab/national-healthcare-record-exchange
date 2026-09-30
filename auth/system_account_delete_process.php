<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_check.php';
require_role(['System Admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['_csrf'] ?? null)) {
    $_SESSION['errors'] = ['Invalid account removal request.'];
    redirect('../admin_operations.php?view=user-management');
}

$accountId = (int)($_POST['account_id'] ?? 0);
if ($accountId === (int)$_SESSION['user_id']) {
    $_SESSION['errors'] = ['You cannot remove the account currently in use.'];
    redirect('../admin_operations.php?view=user-management');
}

try {
    $stmt = db()->prepare('DELETE FROM users WHERE id = ?');
    $stmt->execute([$accountId]);
    $_SESSION[$stmt->rowCount() === 1 ? 'success' : 'errors'] = $stmt->rowCount() === 1 ? 'Account removed.' : ['Account not found.'];
} catch (PDOException $e) {
    $_SESSION['errors'] = ['Unable to remove this account because related records require review.'];
}
redirect('../admin_operations.php?view=user-management');
