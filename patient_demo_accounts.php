<?php
declare(strict_types=1);

require_once __DIR__ . '/auth/auth_check.php';
header('Content-Type: application/json; charset=UTF-8');

try {
    ensure_demo_patients_and_records();
    $stmt = db()->query("SELECT fullname, email, account_number FROM users WHERE role = 'Patient' AND (email = 'patient@nhre.gov' OR email LIKE 'patient%\\@nhre.demo') AND email <> 'patient@nhre.gov' ORDER BY email");
    echo json_encode($stmt->fetchAll(), JSON_THROW_ON_ERROR);
} catch (PDOException|JsonException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Patient demo accounts are temporarily unavailable.']);
}
