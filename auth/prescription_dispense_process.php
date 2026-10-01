<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../includes/pharmacy_functions.php';
require_role(['Pharmacist']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../prescriptions.php');
}

if (!csrf_check($_POST['_csrf'] ?? null)) {
    $_SESSION['errors'] = ['Session expired. Please try again.'];
    redirect('../prescriptions.php');
}

$rx_id = (int)($_POST['prescription_id'] ?? 0);
$notes = trim((string)($_POST['notes'] ?? ''));
$back = $rx_id > 0 ? '../prescription_view.php?id=' . $rx_id : '../prescriptions.php';

expire_stale_prescriptions();

$rx = get_prescription($rx_id);
if (!$rx) {
    $_SESSION['errors'] = ['Prescription not found.'];
    redirect('../prescriptions.php');
}

if (!in_array((string)$rx['status'], prescription_dispensable_statuses(), true)) {
    $_SESSION['errors'] = ['This prescription cannot be dispensed from its current state (' . $rx['status'] . ').'];
    redirect($back);
}

$items = get_prescription_items($rx_id);
$given = [];
$errors = [];

foreach ($items as $item) {
    $item_id = (int)$item['id'];
    $raw = trim((string)($_POST['quantity_given'][$item_id] ?? ''));
    $qty = is_numeric($raw) ? (float)$raw : 0.0;
    $remaining = (float)$item['quantity_prescribed'] - (float)$item['given'];
    if ($remaining <= 1e-9) {
        continue;
    }
    if ($qty <= 0) {
        continue;
    }
    if ($qty > $remaining + 1e-9) {
        $errors[] = 'Dispensed quantity for "' . $item['medicine_name'] . '" exceeds the remaining prescribed amount (' . pharmacy_qty($remaining) . ' ' . $item['unit'] . ').';
        continue;
    }
    $given[$item_id] = $qty;
}

if ($given === []) {
    $errors[] = 'Enter a dispensed quantity for at least one medicine.';
}

if (mb_strlen($notes) > 500) {
    $errors[] = 'Notes must be 500 characters or fewer.';
}

if ($errors) {
    $_SESSION['errors'] = $errors;
    redirect($back);
}

$hospital_id = current_user_hospital_id();
$pdo = db();
$plan = [];
$all_fully_given = true;

try {
    $pdo->beginTransaction();

    foreach ($items as $item) {
        $item_id = (int)$item['id'];
        if (!isset($given[$item_id])) {
            $remaining = (float)$item['quantity_prescribed'] - (float)$item['given'];
            if ($remaining > 1e-9) {
                $all_fully_given = false;
            }
            continue;
        }
        $need = $given[$item_id];

        $sql = 'SELECT id, quantity_remaining
                  FROM medicine_batches
                 WHERE medicine_id = ? AND expiry_date > CURDATE() AND quantity_remaining > 0';
        $params = [(int)$item['medicine_id']];
        if ($hospital_id !== null) {
            $sql .= ' AND (hospital_id = ? OR hospital_id IS NULL)';
            $params[] = $hospital_id;
        }
        $sql .= ' ORDER BY expiry_date ASC, id ASC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $batches = $stmt->fetchAll();

        $drawn = [];
        foreach ($batches as $batch) {
            if ($need <= 1e-9) {
                break;
            }
            $take = min((float)$batch['quantity_remaining'], $need);
            $drawn[] = ['batch_id' => (int)$batch['id'], 'qty' => $take];
            $need -= $take;
        }

        if ($need > 1e-9) {
            $available = (float)available_stock((int)$item['medicine_id']);
            $pdo->rollBack();
            $_SESSION['errors'] = [
                'Insufficient non-expired stock for "' . $item['medicine_name'] . '". '
                . 'Requested ' . pharmacy_qty($given[$item_id]) . ' but only ' . pharmacy_qty((float)$available) . ' ' . $item['unit'] . ' available.',
            ];
            redirect($back);
        }

        $plan[$item_id] = $drawn;
    }

    $updates = [];
    foreach ($plan as $item_id => $drawn) {
        foreach ($drawn as $draw) {
            $batch_id = (int)$draw['batch_id'];
            $updates[$batch_id] = ($updates[$batch_id] ?? 0.0) + (float)$draw['qty'];
        }
    }

    $deduct = $pdo->prepare('UPDATE medicine_batches SET quantity_remaining = quantity_remaining - ? WHERE id = ? AND quantity_remaining >= ?');
    foreach ($updates as $batch_id => $total) {
        $deduct->execute([$total, $batch_id, $total]);
        if ($deduct->rowCount() !== 1) {
            $pdo->rollBack();
            $_SESSION['errors'] = ['Stock changed by another transaction. Please review the prescription and try again.'];
            redirect($back);
        }
    }

    $dispensing_no = 'DSP-' . strtoupper(substr(bin2hex(random_bytes(5)), 0, 8));
    $dstatus = $all_fully_given ? 'COMPLETED' : 'PARTIAL';
    $insertD = $pdo->prepare(
        'INSERT INTO dispensings (dispensing_no, prescription_id, patient_id, pharmacist_id, status, notes)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $insertD->execute([$dispensing_no, $rx_id, (int)$rx['patient_id'], (int)$_SESSION['user_id'], $dstatus, $notes !== '' ? $notes : null]);
    $dispensing_id = (int)$pdo->lastInsertId();

    $insertDI = $pdo->prepare(
        'INSERT INTO dispensing_items (dispensing_id, prescription_item_id, medicine_id, batch_id, quantity_given)
         VALUES (?, ?, ?, ?, ?)'
    );
    foreach ($plan as $item_id => $drawn) {
        $medicine_id = null;
        foreach ($items as $item) {
            if ((int)$item['id'] === $item_id) {
                $medicine_id = (int)$item['medicine_id'];
                break;
            }
        }
        foreach ($drawn as $draw) {
            $insertDI->execute([$dispensing_id, $item_id, $medicine_id, (int)$draw['batch_id'], $draw['qty']]);
        }
    }

    $new_status = $all_fully_given ? 'DISPENSED' : 'PARTIALLY_DISPENSED';
    $updateRx = $pdo->prepare(
        "UPDATE prescriptions
            SET status = ?, verified_by = COALESCE(verified_by, ?), verified_at = COALESCE(verified_at, NOW())
          WHERE id = ?"
    );
    $updateRx->execute([$new_status, (int)$_SESSION['user_id'], $rx_id]);

    $pdo->commit();
    foreach ($items as $item) {
        $medicineId = (int)$item['medicine_id'];
        pharmacist_stock_alerts($medicineId);
    }
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['errors'] = ['Dispensing failed. Please try again.'];
    redirect($back);
}

$itemSummary = [];
foreach ($items as $item) {
    $item_id = (int)$item['id'];
    if (isset($given[$item_id])) {
        $itemSummary[] = $item['medicine_name'] . ' (' . pharmacy_qty($given[$item_id]) . ' ' . $item['unit'] . ')';
    }
}

create_notification(
    (int)$rx['patient_id'],
    $all_fully_given ? 'Medicines dispensed' : 'Medicines partially dispensed',
    'Prescription ' . $rx['prescription_no'] . ' was ' . ($all_fully_given ? 'fully dispensed' : 'partially dispensed') . ' by the pharmacy' . ($itemSummary ? ': ' . implode(', ', $itemSummary) : '') . '.',
    'prescription'
);
log_audit(
    $all_fully_given ? 'DISPENSE_MEDICINE' : 'PARTIAL_DISPENSING',
    'prescription',
    $rx_id,
    'Dispensing ' . $dispensing_no . ' — ' . implode('; ', $itemSummary)
);

$_SESSION['success'] = 'Dispensing recorded (' . $dispensing_no . '). Prescription status: ' . ucwords(strtolower(str_replace('_', ' ', $new_status))) . '.';
redirect($back);
