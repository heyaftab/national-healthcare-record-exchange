<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_check.php';
require_role(['Doctor', 'Lab Technician', 'Pharmacist']);
ensure_access_tables_exists();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../patient_search.php');
}

if (!csrf_check($_POST['_csrf'] ?? null)) {
    $_SESSION['errors'] = ['Session expired. Please try again.'];
    redirect('../patient_search.php');
}

$providerId = (int)($_SESSION['user_id'] ?? 0);
$patientId = (int)($_POST['patient_id'] ?? 0);

try {
    $stmt = db()->prepare('SELECT id, fullname FROM users WHERE id = ? AND role = ?');
    $stmt->execute([$patientId, 'Patient']);
    $patient = $stmt->fetch();

    if (!$patient) {
        $_SESSION['errors'] = ['Patient could not be verified.'];
        redirect('../patient_search.php');
    }

    $stmt = db()->prepare(
        'SELECT id, status FROM access_permissions
         WHERE patient_id = ? AND provider_id = ?
           AND status IN (\'Active\', \'Requested\')
         ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute([$patientId, $providerId]);
    $existing = $stmt->fetch();

    if ($existing) {
        $_SESSION['errors'] = [
            $existing['status'] === 'Active'
                ? 'You already have active access to this patient\'s records.'
                : 'You have already requested access to this patient\'s records.',
        ];
        redirect('../patient_search.php?q=' . urlencode((string)($patient['fullname'] ?? '')));
    }

    $stmt = db()->prepare(
        'INSERT INTO access_permissions (patient_id, provider_id, provider_role, record_types, granted_at, expires_at, status)
         VALUES (?, ?, ?, \'\', NOW(), NULL, \'Requested\')'
    );
    $stmt->execute([$patientId, $providerId, (string)($_SESSION['role'] ?? 'Doctor')]);

    create_notification(
        $patientId,
        'Medical record access requested',
        ($_SESSION['fullname'] ?? 'A healthcare provider') . ' has requested access to your medical records. Review the request from your Data Access page.',
        'access'
    );

    $_SESSION['success'] = 'Access request sent. The patient has been notified and can grant access from their Data Access page.';
    redirect('../patient_search.php?q=' . urlencode((string)($patient['fullname'] ?? '')));
} catch (PDOException $e) {
    $_SESSION['errors'] = ['Something went wrong. Please try again later.'];
    redirect('../patient_search.php');
}
