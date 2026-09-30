<?php
declare(strict_types=1);
require_once __DIR__ . '/auth_check.php';
require_role(['Hospital Admin']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['_csrf'] ?? null)) { $_SESSION['errors'] = ['Invalid hospital connection request.']; redirect('../hospital_profile.php'); }
$name = trim((string)($_POST['name'] ?? ''));
$districtId = (int)($_POST['district_id'] ?? 0);
$phone = trim((string)($_POST['phone'] ?? ''));
$email = trim((string)($_POST['email'] ?? ''));
$address = trim((string)($_POST['address'] ?? ''));
try {
    if ($name === '' || $districtId <= 0 || ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))) throw new RuntimeException('Provide a hospital name, district, and valid email when supplied.');
    $pdo = db();
    $district = $pdo->prepare('SELECT 1 FROM districts WHERE id = ?'); $district->execute([$districtId]);
    if (!$district->fetchColumn()) throw new RuntimeException('Select a valid district.');
    $insert = $pdo->prepare('INSERT INTO hospitals (name, district_id, address, phone, email, is_active) VALUES (?, ?, ?, ?, ?, 1)');
    $insert->execute([$name, $districtId, $address ?: null, $phone ?: null, $email ?: null]);
    $_SESSION['success'] = 'Hospital connected to NHRE.';
} catch (PDOException|RuntimeException $e) { $_SESSION['errors'] = [$e instanceof RuntimeException ? $e->getMessage() : 'This hospital may already be connected.']; }
redirect('../hospital_profile.php');
