<?php
require_once __DIR__ . '/auth_check.php';
ensure_appointments_table_exists();
ensure_clinical_tables();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../appointments.php');
}

if (!csrf_check($_POST['_csrf'] ?? null)) {
    $_SESSION['errors'] = ['Security token expired. Please try again.'];
    redirect('../appointments.php');
}

$user_id = (int)($_SESSION['user_id'] ?? 0);
$role = $_SESSION['role'] ?? '';
if ($user_id <= 0 || $role !== 'Patient') {
    redirect('../appointments.php');
}

$doctor_id = (int)($_POST['doctor_id'] ?? 0);
$appointment_date = trim((string)($_POST['appointment_date'] ?? ''));
$appointment_time = trim((string)($_POST['appointment_time'] ?? ''));
$reason = trim((string)($_POST['reason'] ?? ''));
$errors = [];

if ($doctor_id <= 0) {
    $errors[] = 'Please select a doctor.';
}

$date = DateTimeImmutable::createFromFormat('Y-m-d', $appointment_date);
if ($appointment_date === '' || $date === false) {
    $errors[] = 'Enter a valid appointment date.';
} elseif ($appointment_date < date('Y-m-d')) {
    $errors[] = 'Appointment date cannot be in the past.';
}

if ($appointment_time === '' || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $appointment_time)) {
    $errors[] = 'Enter a valid appointment time.';
}

if ($reason === '') {
    $errors[] = 'Reason for visit is required.';
} elseif (mb_strlen($reason) > 1000) {
    $errors[] = 'Reason for visit must be 1000 characters or fewer.';
}

if ($errors) {
    $_SESSION['errors'] = $errors;
    redirect('../appointments.php');
}

try {
    ensure_doctor_profile_columns();
    ensure_doctor_catalog_tables();

    $stmt = db()->prepare('SELECT id, visiting_hours FROM users WHERE id = ? AND role = ? LIMIT 1');
    $stmt->execute([$doctor_id, 'Doctor']);
    $doctor = $stmt->fetch();
    if (!$doctor) {
        $_SESSION['errors'] = ['Selected doctor is not available.'];
        redirect('../appointments.php');
    }

    $available_slots = get_doctor_time_slots($doctor_id, $appointment_date, $doctor['visiting_hours'] ?? '');
    if (!in_array($appointment_time, $available_slots, true)) {
        $_SESSION['errors'] = ['The selected time is not available for this doctor. Please choose another slot.'];
        redirect('../appointments.php');
    }

    $stmt = db()->prepare("SELECT appointment_id FROM appointments WHERE patient_id = ? AND appointment_date = ? AND appointment_time = ? AND status IN ('Pending', 'Approved') LIMIT 1");
    $stmt->execute([$user_id, $appointment_date, $appointment_time]);
    if ($stmt->fetch()) {
        $_SESSION['errors'] = ['You already have an active appointment at this time.'];
        redirect('../appointments.php');
    }

    $stmt = db()->prepare('SELECT appointment_id FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND appointment_time = ? LIMIT 1');
    $stmt->execute([$doctor_id, $appointment_date, $appointment_time]);
    if ($stmt->fetch()) {
        $_SESSION['errors'] = ['This time slot has already been booked. Please choose another time.'];
        redirect('../appointments.php');
    }

    $pdo = db();
    $pdo->beginTransaction();
    $stmt = $pdo->prepare(
        'INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, reason, status, doctor_notes, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
    );
    $stmt->execute([$user_id, $doctor_id, $appointment_date, $appointment_time, $reason, 'Pending', '']);
    $appointmentId = (int)$pdo->lastInsertId();

    create_event_notification(
        $pdo, $doctor_id,
        'New appointment request',
        'A patient has requested appointment #' . $appointmentId . ' on ' . $appointment_date . ' at ' . $appointment_time . '.',
        'appointment', 'appointment:' . $appointmentId . ':booked', 'appointments.php'
    );
    $pdo->commit();

    $_SESSION['success'] = 'Appointment request submitted successfully.';
    redirect('../appointments.php');
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
    $_SESSION['errors'] = ['Unable to book the appointment. Please try again later.'];
    redirect('../appointments.php');
}
