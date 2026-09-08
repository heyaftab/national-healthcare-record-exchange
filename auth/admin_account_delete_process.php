<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_check.php';
require_role(['Hospital Admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['_csrf'] ?? null)) {
    $_SESSION['errors'] = ['Invalid account removal request.'];
    redirect('../admin_credentials.php');
}

try {
    $pdo = db();
    $accountId = (int)($_POST['account_id'] ?? 0);
    $stmt = $pdo->prepare('DELETE managed FROM users managed JOIN users admin ON admin.id = ? WHERE managed.id = ? AND managed.hospital_id = admin.hospital_id AND managed.role IN (\'Patient\', \'Doctor\', \'Pharmacist\', \'Lab Technician\') AND managed.id <> admin.id');
    $stmt->execute([(int)$_SESSION['user_id'], $accountId]);
    $_SESSION[$stmt->rowCount() === 1 ? 'success' : 'errors'] = $stmt->rowCount() === 1 ? 'Account removed.' : ['Account not found or cannot be removed.'];
} catch (PDOException $e) {
    $_SESSION['errors'] = ['Unable to remove the account.'];
}
redirect('../admin_credentials.php');
