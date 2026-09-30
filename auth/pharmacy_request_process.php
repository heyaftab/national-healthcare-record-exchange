<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_check.php';
require_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../pharmacy.php');
}

if (!csrf_check($_POST['_csrf'] ?? null)) {
    $_SESSION['errors'] = ['Session expired. Please try again.'];
    redirect('../pharmacy.php');
}

ensure_pharmacy_requests_table_exists();

$medicine_name = trim($_POST['medicine_name'] ?? '');
$notes = trim($_POST['notes'] ?? '');
$errors = [];

if (mb_strlen($medicine_name) < 2 || mb_strlen($medicine_name) > 190) {
    $errors[] = 'Please provide the medicine name (2 to 190 characters).';
}

if (mb_strlen($notes) > 1000) {
    $errors[] = 'Prescription notes must be 1000 characters or fewer.';
}

if ($errors) {
    $_SESSION['errors'] = $errors;
    $_SESSION['old'] = ['medicine_name' => $medicine_name, 'notes' => $notes];
    redirect('../pharmacy.php');
}

try {
    $stmt = db()->prepare(
        'INSERT INTO pharmacy_requests (user_id, medicine_name, notes)
         VALUES (?, ?, ?)'
    );
    $stmt->execute([(int)($_SESSION['user_id'] ?? 0), $medicine_name, $notes !== '' ? $notes : null]);

    create_notification(
        (int)($_SESSION['user_id'] ?? 0),
        'Pharmacy request submitted',
        'Your request for "' . $medicine_name . '" has been received and is pending review.',
        'pharmacy'
    );

    $_SESSION['success'] = 'Your pharmacy request has been submitted successfully. Our team will follow up soon.';
    redirect('../pharmacy.php');
} catch (PDOException $e) {
    $_SESSION['errors'] = ['Something went wrong. Please try again later.'];
    $_SESSION['old'] = ['medicine_name' => $medicine_name, 'notes' => $notes];
    redirect('../pharmacy.php');
}
