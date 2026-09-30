<?php
require_once __DIR__ . '/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../profile.php');
}

if (!csrf_check($_POST['_csrf'] ?? null)) {
    $_SESSION['errors'] = ['Security token expired. Please try again.'];
    redirect('../profile.php');
}

$user_id = (int)($_SESSION['user_id'] ?? 0);
if ($user_id <= 0) {
    redirect('../login.php');
}

$fullname = trim((string)($_POST['fullname'] ?? ''));
$email = strtolower(trim((string)($_POST['email'] ?? '')));
$phone = trim((string)($_POST['phone'] ?? ''));
$date_of_birth = trim((string)($_POST['date_of_birth'] ?? ''));
$nationality = trim((string)($_POST['nationality'] ?? ''));
$gender = trim((string)($_POST['gender'] ?? ''));
$address = trim((string)($_POST['address'] ?? ''));
$emergency_contact = trim((string)($_POST['emergency_contact'] ?? ''));
$blood_group = trim((string)($_POST['blood_group'] ?? ''));
$marital_status = trim((string)($_POST['marital_status'] ?? ''));
$occupation = trim((string)($_POST['occupation'] ?? ''));

$errors = [];

$profile_photo_path = null;
$uploaded_photo_file = null;
$upload_dir = __DIR__ . '/../uploads/profile_pics';
if (!is_dir($upload_dir) && !mkdir($upload_dir, 0775, true) && !is_dir($upload_dir)) {
    $errors[] = 'The profile photo directory could not be created.';
} elseif (!is_writable($upload_dir)) {
    $errors[] = 'The profile photo directory is not writable.';
}

$current_photo_stmt = db()->prepare('SELECT profile_photo FROM users WHERE id = ? LIMIT 1');
$current_photo_stmt->execute([$user_id]);
$current_photo = $current_photo_stmt->fetchColumn();

if (!empty($_FILES['profile_photo']['name']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK && !$errors) {
    $allowed_types = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($_FILES['profile_photo']['tmp_name']) ?: '';
    if (!isset($allowed_types[$mime])) {
        $errors[] = 'Only PNG, JPG, WEBP, or GIF images are allowed.';
    } elseif ((int)$_FILES['profile_photo']['size'] > 2 * 1024 * 1024) {
        $errors[] = 'Profile photo must be 2 MB or smaller.';
    } else {
        $filename = 'user_' . $user_id . '_' . bin2hex(random_bytes(8)) . '.' . $allowed_types[$mime];
        $target = $upload_dir . '/' . $filename;
        if (!move_uploaded_file($_FILES['profile_photo']['tmp_name'], $target)) {
            $errors[] = 'Unable to save the uploaded photo.';
        } else {
            $uploaded_photo_file = $target;
            $profile_photo_path = 'uploads/profile_pics/' . $filename;
        }
    }
} elseif (!empty($_FILES['profile_photo']['error']) && $_FILES['profile_photo']['error'] !== UPLOAD_ERR_NO_FILE) {
    $errors[] = 'The photo upload failed. Please try again.';
}

if (mb_strlen($fullname) < 2 || mb_strlen($fullname) > 150) {
    $errors[] = 'Full name must be between 2 and 150 characters.';
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

if ($errors) {
    if ($uploaded_photo_file !== null && is_file($uploaded_photo_file)) {
        unlink($uploaded_photo_file);
    }
    $_SESSION['errors'] = $errors;
    $_SESSION['old'] = compact('fullname', 'email', 'phone', 'date_of_birth', 'nationality', 'gender', 'address', 'emergency_contact', 'blood_group', 'marital_status', 'occupation');
    redirect('../profile.php');
}

try {
    $stmt = db()->prepare('SELECT id FROM users WHERE (email = ? OR phone = ?) AND id != ? LIMIT 1');
    $stmt->execute([$email, $phone, $user_id]);
    if ($stmt->fetch()) {
        $_SESSION['errors'] = ['That email or phone number already belongs to another account.'];
        $_SESSION['old'] = compact('fullname', 'email', 'phone', 'date_of_birth', 'nationality', 'gender', 'address', 'emergency_contact', 'blood_group', 'marital_status', 'occupation');
        redirect('../profile.php');
    }

    $stmt = db()->prepare(
        'UPDATE users SET fullname = ?, email = ?, phone = ?, date_of_birth = ?, nationality = ?, gender = ?, address = ?, emergency_contact = ?, blood_group = ?, marital_status = ?, occupation = ?, profile_photo = ? WHERE id = ?'
    );
    $stmt->execute([
        $fullname,
        $email,
        $phone,
        $date_of_birth !== '' ? $date_of_birth : null,
        $nationality !== '' ? $nationality : null,
        $gender !== '' ? $gender : null,
        $address !== '' ? $address : null,
        $emergency_contact !== '' ? $emergency_contact : null,
        $blood_group !== '' ? $blood_group : null,
        $marital_status !== '' ? $marital_status : null,
        $occupation !== '' ? $occupation : null,
        $profile_photo_path ?? $current_photo,
        $user_id,
    ]);

    if ($profile_photo_path !== null && !empty($current_photo) && $current_photo !== $profile_photo_path) {
        $old_photo_file = __DIR__ . '/../' . ltrim((string)$current_photo, '/');
        if (is_file($old_photo_file)) {
            unlink($old_photo_file);
        }
    }

    $_SESSION['fullname'] = $fullname;
    $_SESSION['email'] = $email;
    $_SESSION['success'] = 'Profile updated successfully.';
    redirect('../profile.php');
} catch (PDOException $e) {
    if ($uploaded_photo_file !== null && is_file($uploaded_photo_file)) {
        unlink($uploaded_photo_file);
    }
    $_SESSION['errors'] = ['Unable to save your profile right now. Please try again later.'];
    redirect('../profile.php');
}
