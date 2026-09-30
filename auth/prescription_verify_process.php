<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../includes/pharmacy_functions.php';
require_role(['Pharmacist']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../prescriptions.php');
}

if (!csrf_check($_POST['_csrf'] ?? null)) {
    $_SESSION['errors'] = ['Session expired. Please try again.'];
    redirect('../prescriptions.php');
}

$rx_id = (int)($_POST['prescription_id'] ?? 0);
$back = $rx_id > 0 ? '../prescription_view.php?id=' . $rx_id : '../prescriptions.php';

expire_stale_prescriptions();

$rx = get_prescription($rx_id);
if (!$rx) {
    $_SESSION['errors'] = ['Prescription not found.'];
    redirect('../prescriptions.php');
}

if (!can_transition_prescription((string)$rx['status'], 'VERIFIED')) {
    $_SESSION['errors'] = ['This prescription cannot be verified from its current state (' . $rx['status'] . ').'];
    redirect($back);
}

try {
    $stmt = db()->prepare(
        "UPDATE prescriptions
            SET status = 'VERIFIED', verified_by = ?, verified_at = NOW()
          WHERE id = ?"
    );
    $stmt->execute([(int)$_SESSION['user_id'], $rx_id]);
} catch (PDOException $e) {
    $_SESSION['errors'] = ['Verification failed. Please try again.'];
    redirect($back);
}

create_notification(
    (int)$rx['patient_id'],
    'Prescription verified',
    'Your prescription ' . $rx['prescription_no'] . ' has been verified by the pharmacy and is being prepared.',
    'prescription'
);
log_audit('VERIFY_PRESCRIPTION', 'prescription', $rx_id, 'Verified prescription ' . $rx['prescription_no']);

$_SESSION['success'] = 'Prescription ' . $rx['prescription_no'] . ' verified successfully.';
redirect($back);
