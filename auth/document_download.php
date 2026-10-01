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

$storageDir = realpath(__DIR__ . '/../uploads/private_documents');
$filename = basename((string)$document['stored_name']);
$file = $storageDir !== false ? realpath($storageDir . DIRECTORY_SEPARATOR . $filename) : false;

if ($storageDir === false || $file === false || !str_starts_with($file, $storageDir . DIRECTORY_SEPARATOR) || !is_file($file)) {
    http_response_code(404);
    exit('Stored file unavailable.');
}

$downloadName = str_replace(["\r", "\n", '"'], '', (string)$document['original_name']);
header('Content-Type: ' . $document['mime_type']);
header('Content-Length: ' . filesize($file));
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('X-Content-Type-Options: nosniff');
readfile($file);
exit;
