<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../includes/pharmacy_functions.php';
require_role(['Doctor']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../prescriptions.php');
}

if (!csrf_check($_POST['_csrf'] ?? null)) {
    $_SESSION['errors'] = ['Session expired. Please try again.'];
    redirect('../prescriptions.php');
}

ensure_pharmacy_tables();
ensure_clinical_tables();

$patient_id = (int)($_POST['patient_id'] ?? 0);
$notes = trim((string)($_POST['notes'] ?? ''));
$medicine_ids = array_map('intval', (array)($_POST['medicine_id'] ?? []));
$quantities = (array)($_POST['quantity'] ?? []);
$dosages = (array)($_POST['dosage'] ?? []);
$frequencies = (array)($_POST['frequency'] ?? []);
$durations = (array)($_POST['duration_days'] ?? []);
$instructions = (array)($_POST['instructions'] ?? []);

$errors = [];

if ($patient_id <= 0) {
    $errors[] = 'Please select the patient.';
} else {
    $stmt = db()->prepare("SELECT id FROM users WHERE id = ? AND role = 'Patient' LIMIT 1");
    $stmt->execute([$patient_id]);
    if (!$stmt->fetch()) {
        $errors[] = 'The selected patient is not valid.';
    }
}

if (mb_strlen($notes) > 1000) {
    $errors[] = 'Prescription notes must be 1000 characters or fewer.';
}

$items = [];
foreach ($medicine_ids as $index => $medicine_id) {
    if ($medicine_id <= 0) {
        continue;
    }
    $quantity = trim((string)($quantities[$index] ?? ''));
    $dosage = trim((string)($dosages[$index] ?? ''));
    $frequency = trim((string)($frequencies[$index] ?? ''));
    $duration = trim((string)($durations[$index] ?? ''));
    $instruction = trim((string)($instructions[$index] ?? ''));

    if (!is_numeric($quantity) || (float)$quantity <= 0 || (float)$quantity > 10000) {
        $errors[] = 'Quantity for each medicine must be between 0 and 10000.';
        continue;
    }
    if ($dosage === '' || mb_strlen($dosage) > 100) {
        $errors[] = 'Provide a dosage (up to 100 characters) for each medicine.';
        continue;
    }
    if ($frequency === '' || mb_strlen($frequency) > 100) {
        $errors[] = 'Provide a frequency (up to 100 characters) for each medicine.';
        continue;
    }
    if ($duration !== '' && (!ctype_digit($duration) || (int)$duration < 1 || (int)$duration > 365)) {
        $errors[] = 'Duration must be between 1 and 365 days.';
        continue;
    }
    if (mb_strlen($instruction) > 500) {
        $errors[] = 'Instructions for each medicine must be 500 characters or fewer.';
        continue;
    }

    $stmt = db()->prepare('SELECT id, name, unit FROM medicines WHERE id = ? AND is_active = 1 LIMIT 1');
    $stmt->execute([$medicine_id]);
    $medicine = $stmt->fetch();
    if (!$medicine) {
        $errors[] = 'One of the selected medicines is not available.';
        continue;
    }

    $items[] = [
        'medicine_id' => $medicine_id,
        'medicine_name' => (string)$medicine['name'],
        'unit' => (string)$medicine['unit'],
        'quantity' => (float)$quantity,
        'dosage' => $dosage,
        'frequency' => $frequency,
        'duration_days' => $duration !== '' ? (int)$duration : null,
        'instructions' => $instruction !== '' ? $instruction : null,
    ];
}

if ($items === []) {
    $errors[] = 'Add at least one valid medicine to the prescription.';
}

if ($errors) {
    $_SESSION['errors'] = $errors;
    $_SESSION['old'] = [
        'patient_id' => $patient_id,
        'notes' => $notes,
        'medicine_ids' => $medicine_ids,
        'quantities' => $quantities,
        'dosages' => $dosages,
        'frequencies' => $frequencies,
        'durations' => $durations,
        'instructions' => $instructions,
    ];
    redirect('../prescriptions.php#new-prescription');
}

try {
    $stmt = db()->prepare("SELECT fullname FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$patient_id]);
    $patient_name = (string)$stmt->fetchColumn();

    $pdo = db();
    $pdo->beginTransaction();

    $prescription_no = 'RX-' . strtoupper(substr(bin2hex(random_bytes(5)), 0, 8));
    $insertRx = $pdo->prepare(
        'INSERT INTO prescriptions (prescription_no, patient_id, doctor_id, status, notes, verified_at, expires_at)
         VALUES (?, ?, ?, \'VERIFIED\', ?, NOW(), DATE_ADD(NOW(), INTERVAL ? DAY))'
    );
    $insertRx->execute([$prescription_no, $patient_id, (int)$_SESSION['user_id'], $notes !== '' ? $notes : null, pharmacy_prescription_days_valid()]);
    $prescription_id = (int)$pdo->lastInsertId();

    $insertItem = $pdo->prepare(
        'INSERT INTO prescription_items (prescription_id, medicine_id, quantity_prescribed, dosage, frequency, duration_days, instructions)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    foreach ($items as $item) {
        $insertItem->execute([
            $prescription_id,
            $item['medicine_id'],
            $item['quantity'],
            $item['dosage'],
            $item['frequency'],
            $item['duration_days'],
            $item['instructions'],
        ]);
    }

    $pdo->commit();
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['errors'] = ['The prescription could not be saved. Please try again.'];
    redirect('../prescriptions.php');
}

$itemSummary = array_map(
    static fn (array $item): string => $item['medicine_name'] . ' (' . pharmacy_qty($item['quantity']) . ' ' . $item['unit'] . ')',
    $items
);

create_event_notification(
    db(), $patient_id,
    'New prescription issued',
    'Dr. ' . ($_SESSION['fullname'] ?? 'Your doctor') . ' issued prescription ' . $prescription_no . ' for ' . $patient_name . '.',
    'prescription', 'prescription:' . $prescription_id, 'prescription_view.php?id=' . $prescription_id
);
notify_pharmacists(
    'Prescription awaiting pharmacy',
    'Prescription ' . $prescription_no . ' for ' . $patient_name . ' is pending pharmacy review.',
    'prescription'
);
log_audit('CREATE_PRESCRIPTION', 'prescription', $prescription_id, implode('; ', $itemSummary));

$_SESSION['success'] = 'Prescription ' . $prescription_no . ' was created and sent to the pharmacy.';
redirect('../prescriptions.php');
