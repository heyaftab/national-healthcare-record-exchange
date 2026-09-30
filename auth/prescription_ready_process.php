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

if (!can_transition_prescription((string)$rx['status'], 'READY')) {
    $_SESSION['errors'] = ['This prescription cannot be marked ready from its current state (' . $rx['status'] . ').'];
    redirect($back);
}

$items = get_prescription_items($rx_id);
$missing = [];
foreach ($items as $item) {
    $remaining = (float)$item['quantity_prescribed'] - (float)$item['given'];
    if ($remaining > 1e-9) {
        $available = available_stock((int)$item['medicine_id']);
        if ($available < $remaining - 1e-9) {
            $missing[] = $item['medicine_name'] . ' (needed ' . pharmacy_qty($remaining) . ', available ' . pharmacy_qty((float)$available) . ' ' . $item['unit'] . ')';
        }
    }
}

if ($missing) {
    $_SESSION['errors'] = ['Cannot mark as ready — insufficient stock for: ' . implode('; ', $missing)];
    redirect($back);
}

try {
    db()->prepare("UPDATE prescriptions SET status = 'READY' WHERE id = ?")->execute([$rx_id]);
} catch (PDOException $e) {
    $_SESSION['errors'] = ['Update failed. Please try again.'];
    redirect($back);
}

create_notification(
    (int)$rx['patient_id'],
    'Prescription ready for pickup',
    'Prescription ' . $rx['prescription_no'] . ' is ready. All medicines are in stock.',
    'prescription'
);
log_audit('MARK_PRESCRIPTION_READY', 'prescription', $rx_id, 'Marked ' . $rx['prescription_no'] . ' as ready');

$_SESSION['success'] = 'Prescription ' . $rx['prescription_no'] . ' marked as ready.';
redirect($back);
