<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../includes/pharmacy_functions.php';
require_role(['Pharmacist']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../stock_out.php');
}

if (!csrf_check($_POST['_csrf'] ?? null)) {
    $_SESSION['errors'] = ['Session expired. Please try again.'];
    redirect('../stock_out.php');
}

$medicine_id = (int)($_POST['medicine_id'] ?? 0);
$batch_id = (int)($_POST['batch_id'] ?? 0);
$quantity = (int)($_POST['quantity'] ?? 0);
$reason = trim((string)($_POST['reason'] ?? ''));
$notes = trim((string)($_POST['notes'] ?? ''));
$errors = [];

if ($medicine_id <= 0) {
    $errors[] = 'Please select a medicine.';
}

if ($batch_id <= 0) {
    $errors[] = 'Please select a batch.';
}

if ($quantity <= 0 || $quantity > 1000000) {
    $errors[] = 'Quantity removed must be greater than 0 and within the supported range.';
}

if (!in_array($reason, ['expired', 'damaged', 'lost', 'recall', 'other'], true)) {
    $errors[] = 'Please select a valid reason for the stock out.';
}

if (!$errors) {
    try {
        $stmt = db()->prepare('SELECT medicine_id, batch_no, quantity_remaining FROM medicine_batches WHERE id = ? AND medicine_id = ? LIMIT 1');
        $stmt->execute([$batch_id, $medicine_id]);
        $batch = $stmt->fetch();
        if (!$batch) {
            $errors[] = 'The selected batch does not exist for this medicine.';
        } elseif ((int)$batch['quantity_remaining'] < $quantity) {
            $errors[] = 'The selected batch does not have enough stock to remove this quantity.';
        }
    } catch (PDOException $e) {
        $errors[] = 'Could not validate stock details.';
    }
}

if ($errors) {
    $_SESSION['errors'] = $errors;
    $_SESSION['old'] = ['medicine_id' => $medicine_id, 'batch_id' => $batch_id, 'quantity' => $quantity, 'reason' => $reason, 'notes' => $notes];
    redirect('../stock_out.php?medicine=' . $medicine_id);
}

try {
    $stmt = db()->prepare('UPDATE medicine_batches SET quantity_remaining = quantity_remaining - ? WHERE id = ? AND medicine_id = ?');
    $stmt->execute([$quantity, $batch_id, $medicine_id]);
    $detail = 'Recorded stock out of ' . $quantity . ' units for batch ' . $batch['batch_no'] . ' (' . $reason . ')';
    if ($notes !== '') {
        $detail .= ': ' . $notes;
    }
    log_audit('STOCK_OUT', 'batch', $batch_id, $detail);
    pharmacist_stock_alerts($medicine_id);
    $_SESSION['success'] = 'Stock out recorded successfully.';
} catch (PDOException $e) {
    $_SESSION['errors'] = ['Unable to record stock out. Please try again.'];
    redirect('../stock_out.php?medicine=' . $medicine_id);
}

redirect('../stock_out.php?medicine=' . $medicine_id);
