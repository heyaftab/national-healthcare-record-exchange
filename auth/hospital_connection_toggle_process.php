<?php
declare(strict_types=1);
require_once __DIR__ . '/auth_check.php';
require_role(['Hospital Admin']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['_csrf'] ?? null)) { $_SESSION['errors'] = ['Invalid hospital connection request.']; redirect('../hospital_profile.php'); }
try {
    $pdo = db(); $hospitalId = (int)($_POST['hospital_id'] ?? 0);
    $stmt = $pdo->prepare('UPDATE hospitals SET is_active = CASE WHEN is_active = 1 THEN 0 ELSE 1 END WHERE id = ?');
    $stmt->execute([$hospitalId]);
    $_SESSION[$stmt->rowCount() === 1 ? 'success' : 'errors'] = $stmt->rowCount() === 1 ? 'Hospital connection updated.' : ['Hospital connection not found.'];
} catch (PDOException $e) { $_SESSION['errors'] = ['Unable to update this hospital connection.']; }
redirect('../hospital_profile.php');
