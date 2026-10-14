<?php
require_once __DIR__ . '/auth_check.php';

require_auth();
ensure_medical_test_tables_exists();

$bookingId = (int)($_GET['id'] ?? 0);
$stmt = db()->prepare(
    'SELECT mtb.id, mtb.user_id, mtb.result_file, mt.name AS test_name, mt.department, mt.center_id
       FROM medical_test_bookings mtb
       JOIN medical_tests mt ON mt.id = mtb.test_id
      WHERE mtb.id = ?
      LIMIT 1'
);
$stmt->execute([$bookingId]);
$booking = $stmt->fetch();

if (!$booking || empty($booking['result_file'])) {
    http_response_code(404);
    exit('Result file not found.');
}

$viewer = (int)$_SESSION['user_id'];
$role = (string)($_SESSION['role'] ?? '');
$patientId = (int)$booking['user_id'];
$allowed = $patientId === $viewer
    || ($role === 'Doctor' && active_access_for_record_type($patientId, $viewer, 'Lab Reports') !== null)
    || ($role === 'Lab Technician' && technician_can_manage_center($viewer, (int)$booking['center_id'], (string)$booking['department']))
    || $role === 'System Admin';

if (!$allowed) {
    http_response_code(403);
    exit('Access denied.');
}

$filename = basename((string)$booking['result_file']);
$storedFile = nhre_read_stored_file('test_results', $filename);
if ($storedFile === null) {
    http_response_code(404);
    exit('Stored file unavailable.');
}

$extension = pathinfo($filename, PATHINFO_EXTENSION);
$downloadName = 'lab-result-' . (int)$booking['id'] . ($extension !== '' ? '.' . $extension : '');
$mime = $storedFile['mime_type'] ?: 'application/octet-stream';

header('Content-Type: ' . $mime);
header('Content-Length: ' . strlen($storedFile['contents']));
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('X-Content-Type-Options: nosniff');
echo $storedFile['contents'];
exit;
