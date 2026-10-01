<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../includes/pharmacy_functions.php';
require_role(['Pharmacist']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../stock.php');
}

if (!csrf_check($_POST['_csrf'] ?? null)) {
    $_SESSION['errors'] = ['Session expired. Please try again.'];
    redirect('../stock.php');
}

$medicine_id = (int)($_POST['medicine_id'] ?? 0);
$batch_no = trim((string)($_POST['batch_no'] ?? ''));
$expiry_date = trim((string)($_POST['expiry_date'] ?? ''));
$quantity = trim((string)($_POST['quantity'] ?? ''));

$errors = [];

if ($medicine_id <= 0) {
    $errors[] = 'Please select a medicine.';
} else {
    $stmt = db()->prepare('SELECT id, name FROM medicines WHERE id = ? AND is_active = 1 LIMIT 1');
    $stmt->execute([$medicine_id]);
    $medicine = $stmt->fetch();
    if (!$medicine) {
        $errors[] = 'The selected medicine is not valid.';
    }
}

if (!preg_match('/^[A-Za-z0-9\-_ ]{1,100}$/', $batch_no)) {
    $errors[] = 'Batch number is required (letters, numbers, dashes, underscores, spaces — up to 100 characters).';
}

$expiryDate = DateTimeImmutable::createFromFormat('Y-m-d', $expiry_date);
if ($expiryDate === false) {
    $errors[] = 'Please provide a valid expiry date.';
} elseif ($expiryDate <= new DateTimeImmutable('today')) {
    $errors[] = 'Expiry date must be in the future.';
}

if ($quantity === '' || !ctype_digit($quantity) || (int)$quantity <= 0 || (int)$quantity > 1000000) {
    $errors[] = 'Quantity must be a whole number between 1 and 1000000.';
}

if (!$errors) {
    try {
        $stmt = db()->prepare('SELECT id FROM medicine_batches WHERE medicine_id = ? AND batch_no = ? LIMIT 1');
        $stmt->execute([$medicine_id, $batch_no]);
        if ($stmt->fetch()) {
            $errors[] = 'A batch with this number already exists for this medicine.';
        }
    } catch (PDOException $e) {
        $errors[] = 'Something went wrong. Please try again.';
    }
}

if ($errors) {
    $_SESSION['errors'] = $errors;
    $_SESSION['old'] = ['medicine_id' => $medicine_id, 'batch_no' => $batch_no, 'expiry_date' => $expiry_date, 'quantity' => $quantity];
    redirect('../stock.php');
}

try {
    $stmt = db()->prepare(
        'INSERT INTO medicine_batches (medicine_id, batch_no, expiry_date, quantity_remaining, hospital_id, created_by)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$medicine_id, $batch_no, $expiry_date, (int)$quantity, current_user_hospital_id(), (int)$_SESSION['user_id']]);
    $batch_id = (int)db()->lastInsertId();

    log_audit('ADD_STOCK', 'batch', $batch_id, 'Added ' . $quantity . ' units of "' . $medicine['name'] . '" (batch ' . $batch_no . ', expiry ' . $expiry_date . ')');
    pharmacist_stock_alerts($medicine_id);
    $_SESSION['success'] = 'Stock added: ' . $quantity . ' units of "' . $medicine['name'] . '" (batch ' . $batch_no . ').';
} catch (PDOException $e) {
    $_SESSION['errors'] = ['Could not add stock. Please try again.'];
    redirect('../stock.php');
}

redirect('../stock.php?medicine=' . $medicine_id);
