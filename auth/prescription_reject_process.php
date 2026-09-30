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
$reason = trim((string)($_POST['reason'] ?? ''));
$back = $rx_id > 0 ? '../prescription_view.php?id=' . $rx_id : '../prescriptions.php';

expire_stale_prescriptions();

$rx = get_prescription($rx_id);
if (!$rx) {
    $_SESSION['errors'] = ['Prescription not found.'];
    redirect('../prescriptions.php');
}

if (!can_transition_prescription((string)$rx['status'], 'REJECTED')) {
    $_SESSION['errors'] = ['This prescription cannot be rejected from its current state (' . $rx['status'] . ').'];
    redirect($back);
}

if (mb_strlen($reason) < 3 || mb_strlen($reason) > 1000) {
    $_SESSION['errors'] = ['A rejection reason is required (3 to 1000 characters).'];
    redirect($back);
}

try {
    $stmt = db()->prepare(
        "UPDATE prescriptions
            SET status = 'REJECTED', rejection_reason = ?, verified_by = COALESCE(verified_by, ?), verified_at = COALESCE(verified_at, NOW())
          WHERE id = ?"
    );
    $stmt->execute([$reason, (int)$_SESSION['user_id'], $rx_id]);
} catch (PDOException $e) {
    $_SESSION['errors'] = ['Rejection failed. Please try again.'];
    redirect($back);
}

create_notification(
    (int)$rx['patient_id'],
    'Prescription rejected',
    'Your prescription ' . $rx['prescription_no'] . ' was rejected by the pharmacy. Reason: ' . $reason,
    'prescription'
);
create_notification(
    (int)$rx['doctor_id'],
    'Prescription rejected',
    'Prescription ' . $rx['prescription_no'] . ' for ' . $rx['patient_name'] . ' was rejected. Reason: ' . $reason,
    'prescription'
);
log_audit('REJECT_PRESCRIPTION', 'prescription', $rx_id, 'Rejected ' . $rx['prescription_no'] . ': ' . $reason);

$_SESSION['success'] = 'Prescription ' . $rx['prescription_no'] . ' rejected.';
redirect($back);
