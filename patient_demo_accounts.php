<?php
declare(strict_types=1);

require_once __DIR__ . '/auth/auth_check.php';
header('Content-Type: application/json; charset=UTF-8');

try {
    if (function_exists('ensure_realistic_patient_population')) {
        ensure_realistic_patient_population();
    }
    if (function_exists('ensure_named_patient_test_accounts')) {
        ensure_named_patient_test_accounts();
    }

    $emails = [
        'patient@nhre.gov',
        'patient002@nhre.demo',
        'patient003@nhre.demo',
        'patient004@nhre.demo',
        'patient005@nhre.demo',
        'patient006@nhre.demo',
        'patient007@nhre.demo',
        'patient008@nhre.demo',
        'patient009@nhre.demo',
        'patient010@nhre.demo',
        'patient011@nhre.demo',
        'patient012@nhre.demo',
        'patient013@nhre.demo',
        'patient014@nhre.demo',
        'patient015@nhre.demo',
        'patient016@nhre.demo',
        'patient017@nhre.demo',
        'patient018@nhre.demo',
        'patient019@nhre.demo',
        'patient020@nhre.demo',
        'patient021@nhre.demo',
        'patient022@nhre.demo',
        'patient023@nhre.demo',
        'patient024@nhre.demo',
        'patient025@nhre.demo',
    ];

    $rows = db()->prepare("SELECT fullname, email, role FROM users WHERE role = 'Patient' AND email IN ('" . implode("','", array_fill(0, count($emails), '?')) . "') ORDER BY email");
    $rows->execute($emails);
    $payload = [];
    foreach ($rows->fetchAll() as $row) {
        $payload[] = [
            'fullname' => $row['fullname'],
            'email' => $row['email'],
            'role' => $row['role'],
            'password' => 'Patient123!',
        ];
    }

    echo json_encode($payload, JSON_THROW_ON_ERROR);
} catch (JsonException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Patient demo accounts are temporarily unavailable.']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Unable to load patient demo accounts.']);
}
