<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_check.php';
require_role(['Hospital Admin']);

$accountView = (string)($_POST['account_view'] ?? '');
$returnPath = '../admin_credentials.php' . (in_array($accountView, ['Doctor', 'Patient', 'staff'], true) ? '?role=' . rawurlencode($accountView) : '');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['_csrf'] ?? null)) {
    $_SESSION['errors'] = ['Invalid account removal request.'];
    redirect($returnPath);
}

try {
    $pdo = db();
    $accountId = (int)($_POST['account_id'] ?? 0);
    $roles = ['Patient', 'Doctor', 'Pharmacist', 'Lab Technician'];
    if (in_array($accountView, ['Patient', 'Doctor'], true)) {
        $roles = [$accountView];
    } elseif ($accountView === 'staff') {
        $roles = ['Pharmacist', 'Lab Technician'];
    }
    $placeholders = implode(', ', array_fill(0, count($roles), '?'));
    $stmt = $pdo->prepare(
        "DELETE managed
           FROM users managed
           JOIN users admin ON admin.id = ?
          WHERE managed.id = ?
            AND managed.hospital_id = admin.hospital_id
            AND managed.role IN ($placeholders)
            AND managed.id <> admin.id"
    );
    $stmt->execute([(int)$_SESSION['user_id'], $accountId, ...$roles]);
    $_SESSION[$stmt->rowCount() === 1 ? 'success' : 'errors'] = $stmt->rowCount() === 1 ? 'Account removed.' : ['Account not found or cannot be removed.'];
} catch (PDOException $e) {
    $_SESSION['errors'] = ['Unable to remove the account.'];
}
redirect($returnPath);
