<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_check.php';
require_role(['Patient']);
ensure_access_tables_exists();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../data_access.php');
}

if (!csrf_check($_POST['_csrf'] ?? null)) {
    $_SESSION['errors'] = ['Session expired. Please try again.'];
    redirect('../data_access.php');
}

$patientId = (int)($_SESSION['user_id'] ?? 0);
$permissionId = (int)($_POST['permission_id'] ?? 0);

try {
    $stmt = db()->prepare(
        'SELECT provider_id FROM access_permissions WHERE id = ? AND patient_id = ? AND status = \'Requested\''
    );
    $stmt->execute([$permissionId, $patientId]);
    $permission = $stmt->fetch();

    if (!$permission) {
        $_SESSION['errors'] = ['Access request not found.'];
        redirect('../data_access.php');
    }

    $stmt = db()->prepare('UPDATE access_permissions SET status = \'Rejected\' WHERE id = ?');
    $stmt->execute([$permissionId]);

    create_notification(
        (int)$permission['provider_id'],
        'Medical record access request rejected',
        'A patient has declined your request to access their medical records.',
        'access'
    );

    $_SESSION['success'] = 'Access request rejected. The provider has been notified.';
    redirect('../data_access.php');
} catch (PDOException $e) {
    $_SESSION['errors'] = ['Something went wrong. Please try again later.'];
    redirect('../data_access.php');
}
