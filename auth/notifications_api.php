<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_check.php';
require_auth();

header('Content-Type: application/json; charset=utf-8');

$user_id = (int)$_SESSION['user_id'];

ensure_notification_links_column();

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        if (!csrf_check($_POST['_csrf'] ?? null)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Invalid security token']);
            exit;
        }

        $stmt = db()->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?');
        $stmt->execute([$user_id]);

        $is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

        if ($is_ajax) {
            echo json_encode(['ok' => true, 'unread' => 0]);
        } else {
            $_SESSION['success'] = 'All notifications marked as read.';
            redirect('../notifications.php');
        }
        exit;
    }

    $stmt = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $stmt->execute([$user_id]);
    $unread = (int)$stmt->fetchColumn();

    $stmt = db()->prepare(
        'SELECT id, title, message, notification_type, target_path, created_at, is_read
         FROM notifications
         WHERE user_id = ?
         ORDER BY created_at DESC
         LIMIT 10'
    );
    $stmt->execute([$user_id]);
    $notifications = $stmt->fetchAll();
    foreach ($notifications as &$notification) {
        $notification['url'] = 'auth/notification_open.php?id=' . (int)$notification['id'];
    }
    unset($notification);

    echo json_encode([
        'ok' => true,
        'unread' => $unread,
        'notifications' => $notifications,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Database error']);
}
