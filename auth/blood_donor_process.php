<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_check.php';
require_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../blood_donation.php');
}

if (!csrf_check($_POST['_csrf'] ?? null)) {
    $_SESSION['errors'] = ['Session expired. Please try again.'];
    redirect('../blood_donation.php');
}

$bloodGroup = trim((string)($_POST['blood_group'] ?? ''));
$phone = trim((string)($_POST['phone'] ?? ''));
$district = trim((string)($_POST['district'] ?? ''));

if ($bloodGroup === '' || $phone === '' || $district === '') {
    $_SESSION['errors'] = ['Blood group, phone number, and district are required.'];
    redirect('../blood_donation.php');
}

try {
    $userId = (int)($_SESSION['user_id'] ?? 0);
    $stmt = db()->prepare('SELECT id, next_eligible_date FROM blood_donors WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $existing = $stmt->fetch();

    $available = 1;
    $nextEligibleDate = null;
    $now = new DateTimeImmutable('now');

    if ($existing) {
        $nextEligibleDate = $existing['next_eligible_date'] ?? null;
        if ($nextEligibleDate !== null && new DateTimeImmutable($nextEligibleDate) > $now) {
            $available = 0;
        }
    }

    if ($existing) {
        $stmt = db()->prepare(
            'UPDATE blood_donors SET blood_group = ?, phone = ?, district = ?, available = ?, next_eligible_date = ? WHERE user_id = ?'
        );
        $stmt->execute([$bloodGroup, $phone, $district, $available, $nextEligibleDate, $userId]);
    } else {
        $stmt = db()->prepare(
            'INSERT INTO blood_donors (user_id, blood_group, phone, district, available, next_eligible_date)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $bloodGroup, $phone, $district, $available, $nextEligibleDate]);
    }

    $_SESSION['success'] = 'Your blood donor profile has been saved successfully.';
    redirect('../blood_donation.php');
} catch (PDOException $e) {
    $_SESSION['errors'] = ['Something went wrong. Please try again later.'];
    redirect('../blood_donation.php');
}
