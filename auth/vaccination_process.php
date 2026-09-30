<?php
require_once __DIR__ . '/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../vaccination.php');
}

if (!csrf_check($_POST['_csrf'] ?? null)) {
    $_SESSION['errors'] = ['Security token expired. Please try again.'];
    redirect('../vaccination.php');
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$role = (string)($_SESSION['role'] ?? '');
if ($userId <= 0) {
    redirect('../login.php');
}

try {
    ensure_vaccination_center_tables();
} catch (PDOException $e) {
    $_SESSION['errors'] = ['Unable to prepare vaccination bookings. Please try again later.'];
    redirect('../vaccination.php');
}

$action = (string)($_POST['action'] ?? '');
if ($action === 'book_vaccination') {
    if ($role !== 'Patient') {
        $_SESSION['errors'] = ['Only patients can submit vaccination bookings.'];
        redirect('../vaccination.php');
    }

    $vaccineName = trim((string)($_POST['vaccine_name'] ?? ''));
    $doseNumber = (int)($_POST['dose_number'] ?? 0);
    $centerId = (int)($_POST['center_id'] ?? 0);
    $bookingDate = trim((string)($_POST['booking_date'] ?? ''));
    $bookingTime = trim((string)($_POST['booking_time'] ?? ''));
    $contactPhone = trim((string)($_POST['contact_phone'] ?? ''));
    $notes = trim((string)($_POST['notes'] ?? ''));
    $errors = [];

    $vaccineDoses = ['BCG' => 1, 'DPT' => 3, 'Polio' => 4, 'Hepatitis B' => 3, 'Measles' => 2, 'MMR' => 2, 'Typhoid' => 2, 'Rabies' => 3, 'COVID-19' => 3, 'Influenza' => 1, 'HPV' => 2, 'Tetanus' => 3];
    if (!isset($vaccineDoses[$vaccineName])) {
        $errors[] = 'Please select a valid vaccine.';
    }
    if ($doseNumber < 1 || (isset($vaccineDoses[$vaccineName]) && $doseNumber > $vaccineDoses[$vaccineName])) {
        $errors[] = 'Please select a valid dose number.';
    }
    $date = DateTimeImmutable::createFromFormat('Y-m-d', $bookingDate);
    if ($bookingDate === '' || $date === false || $date->format('Y-m-d') !== $bookingDate || $bookingDate < date('Y-m-d')) {
        $errors[] = 'Please enter a valid future booking date.';
    }
    if ($bookingTime !== '' && !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $bookingTime)) {
        $errors[] = 'Please enter a valid booking time.';
    }
    if ($contactPhone === '' || mb_strlen($contactPhone) > 30) {
        $errors[] = 'Please provide a contact phone number of 30 characters or fewer.';
    }
    if ($centerId <= 0) {
        $errors[] = 'Please select your preferred hospital or vaccination center.';
    }
    if (mb_strlen($notes) > 1000) {
        $errors[] = 'Booking notes must be 1000 characters or fewer.';
    }
    if ($centerId > 0) {
        $stmt = db()->prepare('SELECT id FROM vaccination_centers WHERE id = ? AND is_active = 1 LIMIT 1');
        $stmt->execute([$centerId]);
        if (!$stmt->fetch()) {
            $errors[] = 'The selected vaccination center is not available.';
        }
    }
    if ($errors) {
        $_SESSION['errors'] = $errors;
        redirect('../vaccination.php');
    }

    try {
        $stmt = db()->prepare(
            'INSERT INTO vaccination_bookings (user_id, vaccine_name, dose_number, center_id, booking_date, booking_time, contact_phone, notes, status, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, \'Pending\', NOW(), NOW())'
        );
        $stmt->execute([$userId, $vaccineName, $doseNumber, $centerId > 0 ? $centerId : null, $bookingDate, $bookingTime !== '' ? $bookingTime : null, $contactPhone, $notes !== '' ? $notes : null]);
        create_notification($userId, 'Vaccination booking received', 'Your ' . $vaccineName . ' vaccination booking request is awaiting review.', 'vaccination');
        $_SESSION['success'] = 'Your vaccination booking request has been submitted.';
    } catch (PDOException $e) {
        $_SESSION['errors'] = ['Unable to save your vaccination booking. Please try again later.'];
    }
    redirect('../vaccination.php');
}

if ($action === 'update_booking') {
    if ($role !== 'Lab Technician') {
        $_SESSION['errors'] = ['You do not have permission to manage vaccination bookings.'];
        redirect('../vaccination.php');
    }

    $bookingId = (int)($_POST['booking_id'] ?? 0);
    $status = trim((string)($_POST['status'] ?? ''));
    $statusNotes = trim((string)($_POST['status_notes'] ?? ''));
    $allowedStatuses = ['Pending', 'Confirmed', 'Ongoing', 'Completed', 'Cancelled'];
    if ($bookingId <= 0 || !in_array($status, $allowedStatuses, true) || mb_strlen($statusNotes) > 2000) {
        $_SESSION['errors'] = ['Please provide valid booking status details.'];
        redirect('../vaccination.php');
    }

    try {
        $stmt = db()->prepare('SELECT vb.user_id, vb.vaccine_name, vb.center_id FROM vaccination_bookings vb WHERE vb.id = ? LIMIT 1');
        $stmt->execute([$bookingId]);
        $booking = $stmt->fetch();
        if (!$booking) {
            $_SESSION['errors'] = ['The selected vaccination booking could not be found.'];
            redirect('../vaccination.php');
        }
        if (!technician_can_manage_center($userId, (int)$booking['center_id'])) {
            $_SESSION['errors'] = ['You are not authorized to manage bookings for this hospital.'];
            redirect('../vaccination.php');
        }
        $stmt = db()->prepare('UPDATE vaccination_bookings SET status = ?, status_notes = ?, technician_id = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([$status, $statusNotes !== '' ? $statusNotes : null, $userId, $bookingId]);
        create_notification((int)$booking['user_id'], 'Vaccination booking updated', 'Your ' . $booking['vaccine_name'] . ' vaccination booking is now ' . strtolower($status) . '.', 'vaccination');
        $_SESSION['success'] = 'Vaccination booking updated successfully.';
    } catch (PDOException $e) {
        $_SESSION['errors'] = ['Unable to update the vaccination booking. Please try again later.'];
    }
    redirect('../vaccination.php');
}

$_SESSION['errors'] = ['Invalid request.'];
redirect('../vaccination.php');
