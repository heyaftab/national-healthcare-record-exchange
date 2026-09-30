<?php
require_once __DIR__ . '/auth_check.php';
ensure_appointments_table_exists();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../appointments.php');
}

if (!csrf_check($_POST['_csrf'] ?? null)) {
    $_SESSION['errors'] = ['Security token expired. Please try again.'];
    redirect('../appointments.php');
}

$user_id = (int)($_SESSION['user_id'] ?? 0);
$role = $_SESSION['role'] ?? '';
$appointment_id = (int)($_POST['appointment_id'] ?? 0);

if ($role !== 'Patient' || $user_id <= 0) {
    $_SESSION['errors'] = ['You do not have permission to cancel this appointment.'];
    redirect('../appointments.php');
}

if ($appointment_id <= 0) {
    $_SESSION['errors'] = ['Invalid appointment request.'];
    redirect('../appointments.php');
}

try {
    $stmt = db()->prepare('SELECT doctor_id, status FROM appointments WHERE appointment_id = ? AND patient_id = ? LIMIT 1');
    $stmt->execute([$appointment_id, $user_id]);
    $appointment = $stmt->fetch();

    if (!$appointment) {
        $_SESSION['errors'] = ['Appointment not found or access denied.'];
        redirect('../appointments.php');
    }

    if (!in_array($appointment['status'], ['Pending', 'Approved'], true)) {
        $_SESSION['errors'] = ['Only pending or approved appointments may be cancelled.'];
        redirect('../appointments.php');
    }

    $stmt = db()->prepare('UPDATE appointments SET status = ? WHERE appointment_id = ?');
    $stmt->execute(['Cancelled', $appointment_id]);

    create_notification(
        (int)$appointment['doctor_id'],
        'Appointment cancelled',
        'A patient has cancelled the appointment scheduled with you.',
        'appointment'
    );

    $_SESSION['success'] = 'Appointment cancelled successfully.';
    redirect('../appointments.php');
} catch (PDOException $e) {
    $_SESSION['errors'] = ['Unable to cancel the appointment. Please try again later.'];
    redirect('../appointments.php');
}
