<?php
declare(strict_types=1);

require_once __DIR__ . '/auth/auth_check.php';

header('Content-Type: application/json; charset=UTF-8');

try {
    $rows = db()->query("SELECT fullname, email FROM users WHERE role = 'Doctor' AND email LIKE 'doctor%@nhre.dev' ORDER BY email")->fetchAll();
    $accounts = [];
    foreach ($rows as $row) {
        $email = (string)$row['email'];
        if ($email === 'doctor001@nhre.dev' || !preg_match('/^doctor(\\d{3})@nhre\\.dev$/', $email, $matches)) {
            continue;
        }
        $accounts[] = [
            'fullname' => (string)$row['fullname'],
            'email' => $email,
            'password' => 'Doctor' . $matches[1] . '!',
        ];
    }
    echo json_encode($accounts, JSON_THROW_ON_ERROR);
} catch (PDOException|JsonException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Doctor accounts are temporarily unavailable.']);
}
