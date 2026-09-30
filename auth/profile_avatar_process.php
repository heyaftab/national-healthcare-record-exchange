<?php
require_once __DIR__ . '/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../profile.php');
}

if (!csrf_check($_POST['_csrf'] ?? null)) {
    $_SESSION['errors'] = ['Security token expired. Please try again.'];
    redirect('../profile.php');
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$avatar = trim((string)($_POST['cartoon_avatar'] ?? ''));
$allowedAvatars = ['kind-caregiver', 'bright-clinician', 'friendly-helper', 'calm-specialist', 'happy-neighbor', 'trusted-guide'];

if ($userId <= 0 || !in_array($avatar, $allowedAvatars, true)) {
    $_SESSION['errors'] = ['Please choose one of the available cartoon profile pictures.'];
    redirect('../profile.php');
}

$avatarUrl = 'https://api.dicebear.com/9.x/avataaars/svg?seed=' . rawurlencode($avatar)
    . '&backgroundColor=b6e3f4,c0aede,d1d4f9&radius=50';

try {
    $currentPhotoStmt = db()->prepare('SELECT profile_photo FROM users WHERE id = ? LIMIT 1');
    $currentPhotoStmt->execute([$userId]);
    $currentPhoto = (string)$currentPhotoStmt->fetchColumn();

    $updateStmt = db()->prepare('UPDATE users SET profile_photo = ? WHERE id = ?');
    $updateStmt->execute([$avatarUrl, $userId]);

    if (str_starts_with($currentPhoto, 'uploads/profile_pics/')) {
        $oldPhoto = __DIR__ . '/../' . $currentPhoto;
        if (is_file($oldPhoto)) {
            unlink($oldPhoto);
        }
    }

    $_SESSION['success'] = 'Your cartoon profile picture has been updated.';
} catch (PDOException $e) {
    $_SESSION['errors'] = ['Unable to update your cartoon profile picture. Please try again later.'];
}

redirect('../profile.php');
