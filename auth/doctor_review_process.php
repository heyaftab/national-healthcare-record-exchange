<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_check.php';
require_role(['Patient']);
ensure_doctor_ratings_table();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../appointments.php');
}

if (!csrf_check($_POST['_csrf'] ?? null)) {
    $_SESSION['errors'] = ['Session expired. Please try again.'];
    redirect('../appointments.php');
}

$patientId = (int)($_SESSION['user_id'] ?? 0);
$doctorId = (int)($_POST['doctor_id'] ?? 0);
$rating = (int)($_POST['rating'] ?? 0);
$review = trim((string)($_POST['review'] ?? ''));

if ($rating < 1 || $rating > 5) {
    $_SESSION['errors'] = ['Please choose a rating between 1 and 5 stars.'];
    redirect('../appointments.php?doctor_id=' . $doctorId . '#doctor-profile');
}

if (mb_strlen($review) > 1000) {
    $_SESSION['errors'] = ['Review is too long. Please keep it under 1000 characters.'];
    redirect('../appointments.php?doctor_id=' . $doctorId . '#doctor-profile');
}

try {
    $stmt = db()->prepare('SELECT id, fullname FROM users WHERE id = ? AND role = ?');
    $stmt->execute([$doctorId, 'Doctor']);
    $doctor = $stmt->fetch();

    if (!$doctor) {
        $_SESSION['errors'] = ['Doctor could not be verified.'];
        redirect('../appointments.php');
    }

    $existing = get_patient_review($doctorId, $patientId);

    $stmt = db()->prepare('SELECT rating, reviews_count FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$doctorId]);
    $aggregate = $stmt->fetch();

    $baseRating = (float)($aggregate['rating'] ?? 0);
    $baseCount = (int)($aggregate['reviews_count'] ?? 0);

    if ($existing === null) {
        $newCount = $baseCount + 1;
        $newRating = $baseCount > 0
            ? round(($baseRating * $baseCount + $rating) / $newCount, 1)
            : (float)$rating;
    } else {
        $newCount = $baseCount;
        $newRating = $baseCount > 0
            ? round(($baseRating * $baseCount - (int)$existing['rating'] + $rating) / $newCount, 1)
            : (float)$rating;
    }
    $newRating = max(1.0, min(5.0, $newRating));

    db()->beginTransaction();
    $stmt = db()->prepare(
        'INSERT INTO doctor_ratings (doctor_id, patient_id, rating, review)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE rating = VALUES(rating), review = VALUES(review), updated_at = NOW()'
    );
    $stmt->execute([$doctorId, $patientId, $rating, $review !== '' ? $review : null]);

    $stmt = db()->prepare('UPDATE users SET rating = ?, reviews_count = ? WHERE id = ?');
    $stmt->execute([$newRating, $newCount, $doctorId]);
    db()->commit();

    if ($existing === null) {
        create_notification(
            $doctorId,
            'New patient review',
            ($_SESSION['fullname'] ?? 'A patient') . ' left a ' . $rating . '-star review for you.',
            'general'
        );
    }

    $_SESSION['success'] = $existing === null
        ? 'Thank you! Your review has been posted.'
        : 'Your review has been updated.';
    redirect('../appointments.php?doctor_id=' . $doctorId . '#doctor-profile');
} catch (PDOException $e) {
    if (db()->inTransaction()) {
        db()->rollBack();
    }
    $_SESSION['errors'] = ['Something went wrong. Please try again later.'];
    redirect('../appointments.php?doctor_id=' . $doctorId . '#doctor-profile');
}
