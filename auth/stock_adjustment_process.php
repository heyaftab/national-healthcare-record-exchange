<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../includes/pharmacy_functions.php';
require_role(['Pharmacist']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../stock_adjustment.php');
}

if (!csrf_check($_POST['_csrf'] ?? null)) {
    $_SESSION['errors'] = ['Session expired. Please try again.'];
    redirect('../stock_adjustment.php');
}

$medicine_id = (int)($_POST['medicine_id'] ?? 0);
$batch_id = (int)($_POST['batch_id'] ?? 0);
$adjustment_type = trim((string)($_POST['adjustment_type'] ?? ''));
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

if (!in_array($adjustment_type, ['increase', 'decrease'], true)) {
    $errors[] = 'Please specify whether stock is being increased or decreased.';
}

if ($quantity <= 0 || $quantity > 1000000) {
    $errors[] = 'Quantity must be greater than 0 and within the supported range.';
}

if (!in_array($reason, ['count_correction', 'damaged', 'return', 'expired', 'other'], true)) {
    $errors[] = 'Please select a valid reason for the stock adjustment.';
}

if (!$errors) {
    try {
        $stmt = db()->prepare('SELECT medicine_id, batch_no, quantity_remaining FROM medicine_batches WHERE id = ? AND medicine_id = ? LIMIT 1');
        $stmt->execute([$batch_id, $medicine_id]);
        $batch = $stmt->fetch();
        if (!$batch) {
            $errors[] = 'The selected batch is invalid.';
        } elseif ($adjustment_type === 'decrease' && (int)$batch['quantity_remaining'] < $quantity) {
            $errors[] = 'The selected batch does not have enough stock to reduce by this amount.';
        }
    } catch (PDOException $e) {
        $errors[] = 'Could not validate stock details.';
    }
}

if ($errors) {
    $_SESSION['errors'] = $errors;
    $_SESSION['old'] = ['medicine_id' => $medicine_id, 'batch_id' => $batch_id, 'adjustment_type' => $adjustment_type, 'quantity' => $quantity, 'reason' => $reason, 'notes' => $notes];
    redirect('../stock_adjustment.php?medicine=' . $medicine_id);
}

try {
    $delta = $adjustment_type === 'increase' ? $quantity : -$quantity;
    $stmt = db()->prepare('UPDATE medicine_batches SET quantity_remaining = quantity_remaining + ? WHERE id = ? AND medicine_id = ?');
    $stmt->execute([$delta, $batch_id, $medicine_id]);

    $detail = 'Updated stock for batch ' . $batch['batch_no'] . ' by ' . ($delta > 0 ? '+' : '') . $delta . ' units (' . $reason . ')';
    if ($notes !== '') {
        $detail .= ': ' . $notes;
    }
    log_audit('STOCK_ADJUSTMENT', 'batch', $batch_id, $detail);
    pharmacist_stock_alerts($medicine_id);
    $_SESSION['success'] = 'Stock adjustment saved successfully.';
} catch (PDOException $e) {
    $_SESSION['errors'] = ['Unable to save the stock adjustment. Please try again.'];
    redirect('../stock_adjustment.php?medicine=' . $medicine_id);
}

redirect('../stock_adjustment.php?medicine=' . $medicine_id);
