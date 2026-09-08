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
$appointment_id = (int)($_POST['appointment_id'] ?? 0);
$action = trim((string)($_POST['action'] ?? ''));
$doctor_notes = trim((string)($_POST['doctor_notes'] ?? ''));
$status = trim((string)($_POST['status'] ?? ''));

$allowed_statuses = ['Pending', 'Approved', 'Completed', 'Cancelled', 'Rejected'];
if ($appointment_id <= 0) {
    $_SESSION['errors'] = ['Invalid appointment request.'];
    redirect('../appointments.php');
}

try {
    if ($role === 'Doctor') {
        $stmt = db()->prepare('SELECT a.patient_id, a.doctor_id, a.status, a.appointment_date, a.appointment_time, u.fullname AS doctor_name, u.specialization, u.hospital_name FROM appointments a JOIN users u ON u.id = a.doctor_id WHERE a.appointment_id = ? AND a.doctor_id = ? LIMIT 1');
        $stmt->execute([$appointment_id, $user_id]);
        $appointment = $stmt->fetch();
        if (!$appointment) {
            $_SESSION['errors'] = ['Appointment not found or access denied.'];
            redirect('../appointments.php');
        }

        if ($action === 'delete') {
            $_SESSION['errors'] = ['Doctors cannot delete appointments.'];
            redirect('../appointments.php');
        }

        if ($status !== '' && !in_array($status, ['Approved', 'Rejected', 'Completed'], true)) {
            $_SESSION['errors'] = ['Invalid appointment action.'];
            redirect('../appointments.php');
        }

        if ($status !== '') {
            $transitions = ['Pending' => ['Approved', 'Rejected'], 'Approved' => ['Completed', 'Rejected'], 'Rejected' => [], 'Completed' => []];
            if (!in_array($status, $transitions[(string)$appointment['status']] ?? [], true)) {
                $_SESSION['errors'] = ['This appointment cannot be changed from its current status.'];
                redirect('../appointments.php');
            }
            $pdo = db();
            $pdo->beginTransaction();
            $updateStmt = $pdo->prepare('UPDATE appointments SET status = ?, doctor_notes = ?, rejection_reason = ?, status_updated_at = NOW() WHERE appointment_id = ?');
            $rejectionReason = $status === 'Rejected' ? ($doctor_notes !== '' ? $doctor_notes : 'No reason was provided.') : null;
            $updateStmt->execute([$status, $doctor_notes, $rejectionReason, $appointment_id]);
            $specialization = (string)($appointment['specialization'] ?: 'Specialist');
            $hospital = (string)($appointment['hospital_name'] ?: 'NHRE partner clinic');
            $message = sprintf('Appointment #%d with Dr. %s (%s) on %s at %s, %s, is %s.', $appointment_id, $appointment['doctor_name'], $specialization, $appointment['appointment_date'], substr((string)$appointment['appointment_time'], 0, 5), $hospital, $status);
            if ($status === 'Rejected') { $message .= ' Reason: ' . $rejectionReason; }
            create_event_notification($pdo, (int)$appointment['patient_id'], 'Appointment ' . $status, $message, 'appointment', 'appointment:' . $appointment_id . ':' . strtolower($status), 'appointments.php#my-appointments');
            $pdo->commit();
            $_SESSION['success'] = 'Appointment status updated successfully.';
        } else {
            $updateStmt = db()->prepare('UPDATE appointments SET doctor_notes = ? WHERE appointment_id = ?');
            $updateStmt->execute([$doctor_notes, $appointment_id]);
            $_SESSION['success'] = 'Doctor notes saved successfully.';
        }

        redirect('../appointments.php');
    }

    if ($role === 'Hospital Admin') {
        $stmt = db()->prepare(
            'SELECT a.patient_id, a.doctor_id
             FROM appointments a
             JOIN users doctor ON doctor.id = a.doctor_id
             JOIN users admin ON admin.id = ?
             WHERE a.appointment_id = ? AND doctor.hospital_id = admin.hospital_id AND admin.hospital_id IS NOT NULL
             LIMIT 1'
        );
        $stmt->execute([$user_id, $appointment_id]);
        $appointment = $stmt->fetch();
        if (!$appointment) {
            $_SESSION['errors'] = ['Appointment not found.'];
            redirect('../appointments.php');
        }

        if ($action === 'delete') {
            $deleteStmt = db()->prepare('DELETE FROM appointments WHERE appointment_id = ?');
            $deleteStmt->execute([$appointment_id]);

            create_notification(
                (int)$appointment['patient_id'],
                'Appointment deleted',
                'An administrator removed your appointment request.',
                'appointment'
            );
            create_notification(
                (int)$appointment['doctor_id'],
                'Appointment deleted',
                'An administrator removed an appointment assigned to you.',
                'appointment'
            );

            $_SESSION['success'] = 'Appointment deleted successfully.';
            redirect('../appointments.php');
        }

        if (!in_array($status, $allowed_statuses, true)) {
            $_SESSION['errors'] = ['Invalid appointment status.'];
            redirect('../appointments.php');
        }

        $updateStmt = db()->prepare('UPDATE appointments SET status = ?, doctor_notes = ? WHERE appointment_id = ?');
        $updateStmt->execute([$status, $doctor_notes, $appointment_id]);

        create_notification(
            (int)$appointment['patient_id'],
            'Appointment updated',
            'An administrator updated the appointment status to ' . $status . '.',
            'appointment'
        );

        $_SESSION['success'] = 'Appointment updated successfully.';
        redirect('../appointments.php');
    }

    $_SESSION['errors'] = ['You do not have permission to update appointments.'];
    redirect('../appointments.php');
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['errors'] = ['Unable to update appointment. Please try again later.'];
    redirect('../appointments.php');
}
