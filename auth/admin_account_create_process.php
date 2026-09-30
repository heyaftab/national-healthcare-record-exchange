<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_check.php';
require_role(['Hospital Admin']);

$accountView = (string)($_POST['account_view'] ?? '');
$viewRoles = ['Doctor' => ['Doctor'], 'Patient' => ['Patient'], 'staff' => ['Pharmacist', 'Lab Technician']];
$returnPath = '../admin_credentials.php' . (isset($viewRoles[$accountView]) ? '?role=' . rawurlencode($accountView) : '');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['_csrf'] ?? null)) {
    $_SESSION['errors'] = ['Invalid account creation request.'];
    redirect($returnPath);
}

$fullname = trim((string)($_POST['fullname'] ?? ''));
$nid = trim((string)($_POST['nid'] ?? ''));
$email = strtolower(trim((string)($_POST['email'] ?? '')));
$phone = trim((string)($_POST['phone'] ?? ''));
$password = (string)($_POST['password'] ?? '');
$role = (string)($_POST['role'] ?? '');
$errors = [];

if (mb_strlen($fullname) < 2 || mb_strlen($fullname) > 150) $errors[] = 'Enter a valid full name.';
if (!preg_match('/^[0-9]{10,20}$/', $nid)) $errors[] = 'National ID must contain 10 to 20 digits.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
if (!preg_match('/^\\+?[0-9][0-9\\s().-]{7,19}$/', $phone)) $errors[] = 'Enter a valid phone number.';
if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\\d)(?=.*[\\W_]).{8,}$/', $password)) $errors[] = 'Temporary password must be 8+ characters and include upper/lowercase, a number, and a symbol.';
if (!in_array($role, ['Patient', 'Doctor', 'Pharmacist', 'Lab Technician'], true)) $errors[] = 'Select a supported hospital account role.';
if (isset($viewRoles[$accountView]) && !in_array($role, $viewRoles[$accountView], true)) $errors[] = 'Select an account role for this management workspace.';

try {
    $pdo = db();
    $scope = $pdo->prepare('SELECT hospital_id FROM users WHERE id = ? LIMIT 1');
    $scope->execute([(int)$_SESSION['user_id']]);
    $hospitalId = (int)$scope->fetchColumn();
    if ($hospitalId <= 0) $errors[] = 'Your administrator account is not assigned to a hospital.';
    $exists = $pdo->prepare('SELECT 1 FROM users WHERE email = ? OR nid = ? OR phone = ? LIMIT 1');
    $exists->execute([$email, $nid, $phone]);
    if ($exists->fetchColumn()) $errors[] = 'An account already uses this email, National ID, or phone number.';
    if ($errors) throw new RuntimeException(implode(' ', $errors));

    $insert = $pdo->prepare('INSERT INTO users (fullname, nid, email, phone, password_hash, role, hospital_id) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $insert->execute([$fullname, $nid, $email, $phone, password_hash($password, PASSWORD_DEFAULT), $role, $hospitalId]);
    $id = (int)$pdo->lastInsertId();
    $pdo->prepare('UPDATE users SET account_number = ? WHERE id = ?')->execute(['NHRE-' . str_pad((string)$id, 8, '0', STR_PAD_LEFT), $id]);
    $_SESSION['success'] = 'Account created and assigned to your hospital.';
} catch (PDOException|RuntimeException $e) {
    $_SESSION['errors'] = [$e instanceof RuntimeException ? $e->getMessage() : 'Unable to create the account.'];
}
redirect($returnPath);
