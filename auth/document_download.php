<?php
require_once __DIR__ . '/auth_check.php';

require_auth();
ensure_clinical_tables();

$id = (int)($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT * FROM medical_documents WHERE id = ?');
$stmt->execute([$id]);
$document = $stmt->fetch();

if (!$document) {
    http_response_code(404);
    exit('Document not found.');
}

$viewer = (int)$_SESSION['user_id'];
$patientId = (int)$document['patient_id'];
$allowed = $patientId === $viewer
    || active_access_for_record_type($patientId, $viewer, 'Medical Documents') !== null;

if (!$allowed) {
    http_response_code(403);
    exit('Access denied.');
}

$filename = basename((string)$document['stored_name']);
$storedFile = nhre_read_stored_file('private_documents', $filename);
if ($storedFile === null) {
    http_response_code(404);
    exit('Stored file unavailable.');
}

$downloadName = str_replace(["\r", "\n", '"'], '', (string)$document['original_name']);
header('Content-Type: ' . $document['mime_type']);
header('Content-Length: ' . strlen($storedFile['contents']));
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('X-Content-Type-Options: nosniff');
echo $storedFile['contents'];
exit;
