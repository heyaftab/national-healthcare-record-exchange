<?php
require_once __DIR__ . '/auth_check.php';

require_role(['Doctor', 'Hospital Admin', 'System Admin']);
ensure_clinical_tables();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['_csrf'] ?? null)) {
    $_SESSION['errors'] = ['Invalid verification request.'];
    redirect('../medical_documents.php');
}

$id = (int)($_POST['id'] ?? 0);
$status = (string)($_POST['status'] ?? '');
if (!in_array($status, ['Verified', 'Rejected'], true)) {
    $_SESSION['errors'] = ['Invalid verification status.'];
    redirect('../medical_documents.php');
}

$stmt = db()->prepare('SELECT patient_id FROM medical_documents WHERE id = ?');
$stmt->execute([$id]);
$document = $stmt->fetch();
if (!$document) {
    $_SESSION['errors'] = ['Document access denied.'];
    redirect('../medical_documents.php');
}

$patientId = (int)$document['patient_id'];
$viewer = (int)$_SESSION['user_id'];
$role = (string)$_SESSION['role'];
$allowed = match ($role) {
    'Doctor' => active_access_for_record_type($patientId, $viewer, 'Medical Documents') !== null,
    'Hospital Admin' => hospital_admin_can_manage_user($viewer, $patientId),
    'System Admin' => true,
    default => false,
};

if (!$allowed) {
    $_SESSION['errors'] = ['Document access denied.'];
    redirect($role === 'Doctor' ? '../patient_search.php' : '../admin_dashboard.php');
}

$stmt = db()->prepare('UPDATE medical_documents SET verification_status = ?, verified_by = ?, verified_at = NOW() WHERE id = ?');
$stmt->execute([$status, $viewer, $id]);

create_notification($patientId, 'Document ' . $status, 'A medical document was marked ' . $status . '.', 'medical_document');
$_SESSION['success'] = 'Document status updated.';
redirect($role === 'Doctor' ? '../medical_documents.php?patient=' . $patientId : '../admin_dashboard.php');
