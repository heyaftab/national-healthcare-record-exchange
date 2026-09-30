<?php
require_once __DIR__ . '/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../medical_tests.php');
}

if (!csrf_check($_POST['_csrf'] ?? null)) {
    $_SESSION['errors'] = ['Security token expired. Please try again.'];
    redirect('../medical_tests.php');
}

$user_id = (int)($_SESSION['user_id'] ?? 0);
$role = $_SESSION['role'] ?? '';

if ($user_id <= 0) {
    redirect('../login.php');
}

$errors = [];
$success = '';

try {
    ensure_medical_test_tables_exists();
} catch (PDOException $e) {
    $_SESSION['errors'] = ['Unable to prepare the medical test module. Please try again later.'];
    redirect('../medical_tests.php');
}

if (isset($_POST['action']) && $_POST['action'] === 'book_test') {
    $test_id = (int)($_POST['test_id'] ?? 0);
    $booking_date = trim((string)($_POST['booking_date'] ?? ''));
    $booking_time = trim((string)($_POST['booking_time'] ?? ''));
    $notes = trim((string)($_POST['notes'] ?? ''));

    if ($test_id <= 0) {
        $errors[] = 'Please select a valid medical test.';
    }

    $date = DateTimeImmutable::createFromFormat('Y-m-d', $booking_date);
    if ($booking_date === '' || $date === false) {
        $errors[] = 'Please enter a valid booking date.';
    } elseif ($booking_date < date('Y-m-d')) {
        $errors[] = 'Booking date cannot be in the past.';
    }

    if ($booking_time !== '' && !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $booking_time)) {
        $errors[] = 'Please enter a valid booking time.';
    }

    if ($notes !== '' && mb_strlen($notes) > 1000) {
        $errors[] = 'Booking notes must be 1000 characters or fewer.';
    }

    if ($errors) {
        $_SESSION['errors'] = $errors;
        redirect('../medical_tests.php');
    }

    try {
        $stmt = db()->prepare('SELECT id, availability FROM medical_tests WHERE id = ? LIMIT 1');
        $stmt->execute([$test_id]);
        $test = $stmt->fetch();

        if (!$test || (int)$test['availability'] !== 1) {
            $_SESSION['errors'] = ['The selected test is not currently available.'];
            redirect('../medical_tests.php');
        }

        $stmt = db()->prepare(
            'INSERT INTO medical_test_bookings (test_id, user_id, booking_date, booking_time, status, result_notes, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([$test_id, $user_id, $booking_date, $booking_time !== '' ? $booking_time : null, 'Pending', $notes]);

        create_notification(
            $user_id,
            'Test booking received',
            'Your medical test booking request has been received and is awaiting review.',
            'medical_test'
        );

        $_SESSION['success'] = 'Your test booking request has been submitted.';
        redirect('../medical_tests.php');
    } catch (PDOException $e) {
        $_SESSION['errors'] = ['Unable to save your booking request. Please try again later.'];
        redirect('../medical_tests.php');
    }
}

if (isset($_POST['action']) && $_POST['action'] === 'update_booking') {
    if ($role !== 'Lab Technician') {
        $_SESSION['errors'] = ['You do not have permission to manage laboratory bookings.'];
        redirect('../medical_tests.php');
    }

    $booking_id = (int)($_POST['booking_id'] ?? 0);
    $status = trim((string)($_POST['status'] ?? ''));
    $result_notes = trim((string)($_POST['result_notes'] ?? ''));
    $allowed_statuses = ['Pending', 'Confirmed', 'Ongoing', 'Completed', 'Cancelled'];

    if ($booking_id <= 0) {
        $errors[] = 'Please select a valid booking.';
    }

    if (!in_array($status, $allowed_statuses, true)) {
        $errors[] = 'Please choose a valid booking status.';
    }

    if ($result_notes !== '' && mb_strlen($result_notes) > 2000) {
        $errors[] = 'Result notes must be 2000 characters or fewer.';
    }

    $result_file_path = null;
    if (isset($_FILES['result_file']) && is_array($_FILES['result_file']) && ($_FILES['result_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        if (($_FILES['result_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $errors[] = 'The uploaded result file could not be processed.';
        } else {
            $tmp_name = $_FILES['result_file']['tmp_name'] ?? '';
            $size = (int)($_FILES['result_file']['size'] ?? 0);
            if ($tmp_name === '' || !is_uploaded_file($tmp_name) || $size <= 0) {
                $errors[] = 'Please upload a valid result file.';
            } else {
                $allowed = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'txt'];
                $extension = strtolower(pathinfo((string)($_FILES['result_file']['name'] ?? ''), PATHINFO_EXTENSION));
                if (!in_array($extension, $allowed, true)) {
                    $errors[] = 'Only PDF, DOC, DOCX, JPG, PNG, or TXT files are allowed.';
                } else {
                    $target_dir = __DIR__ . '/../uploads/test_results';
                    if (!is_dir($target_dir)) {
                        mkdir($target_dir, 0755, true);
                    }

                    $filename = 'result_' . $booking_id . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
                    $target_path = $target_dir . '/' . $filename;
                    if (!move_uploaded_file($tmp_name, $target_path)) {
                        $errors[] = 'Unable to save the uploaded result file.';
                    } else {
                        $result_file_path = 'uploads/test_results/' . $filename;
                    }
                }
            }
        }
    }

    if ($errors) {
        $_SESSION['errors'] = $errors;
        redirect('../medical_tests.php');
    }

    try {
        $stmt = db()->prepare('SELECT mtb.id, mt.center_id, mt.department FROM medical_test_bookings mtb JOIN medical_tests mt ON mt.id = mtb.test_id WHERE mtb.id = ? LIMIT 1');
        $stmt->execute([$booking_id]);
        $booking = $stmt->fetch();
        if (!$booking) {
            $_SESSION['errors'] = ['The selected booking could not be found.'];
            redirect('../medical_tests.php');
        }
        if (!technician_can_manage_center($user_id, (int)$booking['center_id'], (string)$booking['department'])) {
            $_SESSION['errors'] = ['You are not authorized to manage requests for this hospital or laboratory section.'];
            redirect('../medical_tests.php');
        }

        $result_date = null;
        if ($status === 'Completed') {
            $result_date = date('Y-m-d');
        }

        $sql = 'UPDATE medical_test_bookings SET status = ?, result_notes = ?, technician_id = ?, updated_at = NOW()';
        $params = [$status, $result_notes, $user_id];
        if ($result_date !== null) {
            $sql .= ', result_date = ?';
            $params[] = $result_date;
        }
        if ($result_file_path !== null) {
            $sql .= ', result_file = ?';
            $params[] = $result_file_path;
        }
        $sql .= ' WHERE id = ?';
        $params[] = $booking_id;

        $stmt = db()->prepare($sql);
        $stmt->execute($params);

        $_SESSION['success'] = 'Booking updated successfully.';
        redirect('../medical_tests.php');
    } catch (PDOException $e) {
        $_SESSION['errors'] = ['Unable to update the booking. Please try again later.'];
        redirect('../medical_tests.php');
    }
}

$_SESSION['errors'] = ['Invalid request.'];
redirect('../medical_tests.php');
