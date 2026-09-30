<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../blood_donation.php');
}

if (!csrf_check($_POST['_csrf'] ?? null)) {
    $_SESSION['errors'] = ['Session expired. Please try again.'];
    redirect('../blood_donation.php');
}

$reqName = trim((string)($_POST['requester_name'] ?? ''));
$phone = trim((string)($_POST['phone'] ?? ''));
$bloodGroup = trim((string)($_POST['blood_group'] ?? ''));
$district = trim((string)($_POST['district'] ?? ''));
$notes = trim((string)($_POST['notes'] ?? ''));

$allowedBloodGroups = ['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'];
$errors = [];

if ($reqName === '') {
    $errors[] = 'Please enter the requester name.';
} elseif (mb_strlen($reqName) > 150) {
    $errors[] = 'Requester name must be 150 characters or fewer.';
}

if ($phone === '') {
    $errors[] = 'Please enter a phone number.';
} elseif (!preg_match('/^\+?[0-9][0-9\s().\-]{7,19}$/', $phone)) {
    $errors[] = 'Please enter a valid phone number.';
}

if (!in_array($bloodGroup, $allowedBloodGroups, true)) {
    $errors[] = 'Please choose a valid blood group.';
}

if ($district === '') {
    $errors[] = 'Please enter the district.';
} elseif (mb_strlen($district) > 100) {
    $errors[] = 'District must be 100 characters or fewer.';
}

if ($notes !== '' && mb_strlen($notes) > 1000) {
    $errors[] = 'Notes must be 1000 characters or fewer.';
}

if ($errors) {
    $_SESSION['errors'] = $errors;
    redirect('../blood_donation.php');
}

try {
    $stmt = db()->prepare(
        'INSERT INTO blood_requests (requester_name, phone, blood_group, district, notes)
         VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$reqName, $phone, $bloodGroup, $district, $notes]);

    $_SESSION['success'] = 'Your blood request was submitted. Available donors in your district will be shown below.';
    redirect('../blood_donation.php');
} catch (PDOException $e) {
    $_SESSION['errors'] = ['Something went wrong. Please try again later.'];
    redirect('../blood_donation.php');
}
