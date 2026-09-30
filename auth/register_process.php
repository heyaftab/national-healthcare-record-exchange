<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../register.php');
}

if (!csrf_check($_POST['_csrf'] ?? null)) {
    $_SESSION['errors'] = ['Session expired. Please try again.'];
    redirect('../register.php');
}

$fullname = trim($_POST['fullname'] ?? '');
$nid = trim($_POST['nid'] ?? '');
$email = strtolower(trim($_POST['email'] ?? ''));
$phone = trim($_POST['phone'] ?? '');
$date_of_birth = trim($_POST['date_of_birth'] ?? '');
$nationality = trim($_POST['nationality'] ?? '');
$gender = trim($_POST['gender'] ?? '');
$address = trim($_POST['address'] ?? '');
$emergency_contact = trim($_POST['emergency_contact'] ?? '');
$blood_group = trim($_POST['blood_group'] ?? '');
$marital_status = trim($_POST['marital_status'] ?? '');
$occupation = trim($_POST['occupation'] ?? '');
$password = (string)($_POST['password'] ?? '');
$confirm = (string)($_POST['confirm_password'] ?? '');
$role = (string)($_POST['role'] ?? '');

$errors = [];

if (mb_strlen($fullname) < 2 || mb_strlen($fullname) > 150) {
    $errors[] = 'Full name must be between 2 and 150 characters.';
}

if (!preg_match('/^[0-9]{10,20}$/', $nid)) {
    $errors[] = 'National ID must be 10 to 20 digits.';
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
    $errors[] = 'Enter a valid email address.';
}

if (!preg_match('/^\+?[0-9][0-9\s().\-]{7,19}$/', $phone)) {
    $errors[] = 'Enter a valid phone number.';
}

if ($date_of_birth !== '' && DateTimeImmutable::createFromFormat('Y-m-d', $date_of_birth) === false) {
    $errors[] = 'Enter a valid date of birth.';
}

if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/', $password)) {
    $errors[] = 'Password must be 8+ characters with uppercase, lowercase, number, and symbol.';
}

if ($password !== $confirm) {
    $errors[] = 'Passwords do not match.';
}

if (!in_array($role, self_service_roles(), true)) {
    $errors[] = 'Please select a valid role. Administrative roles cannot be self-registered.';
}

if ($errors) {
    $_SESSION['errors'] = $errors;
    $_SESSION['old'] = compact('fullname', 'nid', 'email', 'phone', 'role', 'date_of_birth', 'nationality', 'gender', 'address', 'emergency_contact', 'blood_group', 'marital_status', 'occupation');
    redirect('../register.php');
}

try {
    $stmt = db()->prepare('SELECT id FROM users WHERE email = ? OR nid = ? OR phone = ? LIMIT 1');
    $stmt->execute([$email, $nid, $phone]);

    if ($stmt->fetch()) {
        $_SESSION['errors'] = ['An account with that email, National ID, or phone number already exists.'];
        $_SESSION['old'] = compact('fullname', 'nid', 'email', 'phone', 'role', 'date_of_birth', 'nationality', 'gender', 'address', 'emergency_contact', 'blood_group', 'marital_status', 'occupation');
        redirect('../register.php');
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $account_number_value = null;
    $date_of_birth_value = $date_of_birth !== '' ? $date_of_birth : null;
    $nationality_value = $nationality !== '' ? $nationality : null;
    $gender_value = $gender !== '' ? $gender : null;
    $address_value = $address !== '' ? $address : null;
    $emergency_contact_value = $emergency_contact !== '' ? $emergency_contact : null;
    $blood_group_value = $blood_group !== '' ? $blood_group : null;
    $marital_status_value = $marital_status !== '' ? $marital_status : null;
    $occupation_value = $occupation !== '' ? $occupation : null;

    $stmt = db()->prepare(
        'INSERT INTO users (fullname, nid, email, phone, password_hash, role, account_number, date_of_birth, nationality, gender, address, emergency_contact, blood_group, marital_status, occupation)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$fullname, $nid, $email, $phone, $hash, $role, $account_number_value, $date_of_birth_value, $nationality_value, $gender_value, $address_value, $emergency_contact_value, $blood_group_value, $marital_status_value, $occupation_value]);

    $new_user_id = (int)db()->lastInsertId();
    $assigned_account_number = 'NHRE-' . str_pad((string)$new_user_id, 8, '0', STR_PAD_LEFT);
    $stmt = db()->prepare('UPDATE users SET account_number = ? WHERE id = ?');
    $stmt->execute([$assigned_account_number, $new_user_id]);

    $_SESSION['success'] = 'Account created successfully. You can now log in.';
    redirect('../login.php');
} catch (PDOException $e) {
    $_SESSION['errors'] = ['Something went wrong. Please try again later.'];
    $_SESSION['old'] = compact('fullname', 'nid', 'email', 'phone', 'role', 'date_of_birth', 'nationality', 'gender', 'address', 'emergency_contact', 'blood_group', 'marital_status', 'occupation');
    redirect('../register.php');
}
