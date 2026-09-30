<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_check.php';
require_auth();

$notificationId = (int)($_GET['id'] ?? 0);
$userId = (int)($_SESSION['user_id'] ?? 0);
$role = (string)($_SESSION['role'] ?? '');
if ($notificationId <= 0 || $userId <= 0) {
    redirect('../notifications.php');
}

try {
    ensure_notification_links_column();
    $stmt = db()->prepare('SELECT notification_type, target_path FROM notifications WHERE id = ? AND user_id = ? LIMIT 1');
    $stmt->execute([$notificationId, $userId]);
    $notification = $stmt->fetch();
    if (!$notification) {
        redirect('../notifications.php');
    }

    $allowedPath = notification_destination((string)$notification['notification_type'], $role);
    $storedPath = (string)($notification['target_path'] ?? '');
    $destination = $storedPath === $allowedPath ? $storedPath : $allowedPath;

    $stmt = db()->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?');
    $stmt->execute([$notificationId, $userId]);
    redirect('../' . $destination);
} catch (PDOException $e) {
    redirect('../notifications.php');
}
