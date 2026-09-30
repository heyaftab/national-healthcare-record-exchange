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
        'SELECT provider_id, provider_role, record_types FROM access_permissions WHERE id = ? AND patient_id = ?'
    );
    $stmt->execute([$permissionId, $patientId]);
    $permission = $stmt->fetch();

    if (!$permission) {
        $_SESSION['errors'] = ['Access permission not found.'];
        redirect('../data_access.php');
    }

    $stmt = db()->prepare(
        'UPDATE access_permissions SET status = \'Revoked\' WHERE id = ?'
    );
    $stmt->execute([$permissionId]);

    if (($permission['provider_role'] ?? '') === 'Doctor') {
        create_notification(
            (int)$permission['provider_id'],
            'Medical record access revoked',
            'A patient has revoked your access to their records.',
            'access'
        );
    }

    $_SESSION['success'] = 'Access permission revoked. The provider can no longer view your records.';
    redirect('../data_access.php');
} catch (PDOException $e) {
    $_SESSION['errors'] = ['Something went wrong. Please try again later.'];
    redirect('../data_access.php');
}
