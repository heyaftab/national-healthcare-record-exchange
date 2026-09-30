<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_check.php';
require_auth();

if (($_SESSION['role'] ?? '') !== 'Hospital Admin') {
    $_SESSION['errors'] = ['You do not have permission to update donor eligibility.'];
    redirect('../blood_donation.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../blood_donation.php');
}

if (!csrf_check($_POST['_csrf'] ?? null)) {
    $_SESSION['errors'] = ['Session expired. Please try again.'];
    redirect('../blood_donation.php');
}

$donorId = (int)($_POST['donor_id'] ?? 0);
$action = (string)($_POST['action'] ?? '');

if ($donorId <= 0 || !in_array($action, ['mark_donated', 'mark_available'], true)) {
    $_SESSION['errors'] = ['Invalid hospital update request.'];
    redirect('../blood_donation.php');
}

try {
    if ($action === 'mark_donated') {
        $nextEligibleDate = (new DateTimeImmutable('+4 months'))->format('Y-m-d H:i:s');
        $stmt = db()->prepare('UPDATE blood_donors SET available = 0, last_donation_date = NOW(), next_eligible_date = ? WHERE id = ?');
        $stmt->execute([$nextEligibleDate, $donorId]);
        $_SESSION['success'] = 'Donor eligibility updated. The donor will be unavailable for the next 4 months.';
    } else {
        $stmt = db()->prepare('UPDATE blood_donors SET available = 1, next_eligible_date = NULL WHERE id = ?');
        $stmt->execute([$donorId]);
        $_SESSION['success'] = 'Donor availability has been restored.';
    }

    redirect('../blood_donation.php');
} catch (PDOException $e) {
    $_SESSION['errors'] = ['Something went wrong. Please try again later.'];
    redirect('../blood_donation.php');
}
