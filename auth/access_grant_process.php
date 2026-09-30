<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_check.php';
require_role(['Patient']);
ensure_access_tables_exists();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../data_access.php');
}

if (!csrf_check($_POST['_csrf'] ?? null)) {
    $_SESSION['errors'] = ['Session expired. Please try again.'];
    redirect('../data_access.php');
}

$patientId = (int)($_SESSION['user_id'] ?? 0);
$providerType = trim((string)($_POST['provider_type'] ?? 'Doctor'));
$grantedAt = trim((string)($_POST['granted_at'] ?? ''));
$expiresAt = trim((string)($_POST['expires_at'] ?? ''));
$recordTypes = array_values(array_filter((array)($_POST['record_types'] ?? []), static fn ($v) => is_string($v) && trim($v) !== ''));

$errors = [];

if (!in_array($providerType, ['Doctor', 'Hospital'], true)) {
    $errors[] = 'Please choose a valid provider type.';
}

if ($providerType === 'Doctor') {
    $providerId = (int)($_POST['provider_id'] ?? 0);
    $providerRole = 'Doctor';
} else {
    $providerId = (int)($_POST['hospital_id'] ?? 0);
    $providerRole = 'Hospital';
}

if ($providerId <= 0) {
    $errors[] = 'Please select a provider.';
}

$validTypes = access_record_types();
if (!$recordTypes) {
    $errors[] = 'Select at least one record type to share.';
} else {
    foreach ($recordTypes as $type) {
        if (!in_array($type, $validTypes, true)) {
            $errors[] = 'Invalid record type selected.';
            break;
        }
    }
}

$today = new DateTimeImmutable('today');
$grantedDate = DateTimeImmutable::createFromFormat('Y-m-d', $grantedAt);
$expiresDate = DateTimeImmutable::createFromFormat('Y-m-d', $expiresAt);
if ($grantedDate === false) {
    $errors[] = 'Enter a valid start date.';
}
if ($expiresDate === false) {
    $errors[] = 'Enter a valid expiration date.';
}
if ($grantedDate !== false && $expiresDate !== false) {
    if ($expiresDate < $grantedDate) {
        $errors[] = 'Expiration date must be on or after the start date.';
    }
    if ($expiresDate > $today->modify('+365 days')) {
        $errors[] = 'Access cannot be granted for more than one year.';
    }
}

if ($providerId > 0 && $providerType === 'Doctor') {
    try {
        $stmt = db()->prepare('SELECT id FROM users WHERE id = ? AND role = ? AND id != ?');
        $stmt->execute([$providerId, 'Doctor', $patientId]);
        if (!$stmt->fetch()) {
            $errors[] = 'Selected doctor could not be verified.';
        }
    } catch (PDOException $e) {
        $errors[] = 'Provider could not be verified. Please try again.';
    }
} elseif ($providerId > 0 && $providerType === 'Hospital') {
    try {
        $stmt = db()->prepare('SELECT id FROM hospitals WHERE id = ? AND is_active = 1');
        $stmt->execute([$providerId]);
        if (!$stmt->fetch()) {
            $errors[] = 'Selected hospital could not be verified.';
        }
    } catch (PDOException $e) {
        $errors[] = 'Provider could not be verified. Please try again.';
    }
}

if ($errors) {
    $_SESSION['errors'] = $errors;
    $_SESSION['old'] = [
        'provider_id' => $providerId,
        'granted_at' => $grantedAt,
        'expires_at' => $expiresAt,
    ];
    redirect('../data_access.php');
}

$recordTypesList = implode(',', $recordTypes);
$expiresDb = $expiresDate->format('Y-m-d 23:59:59');
$grantedDb = ($grantedDate !== false && $grantedDate > $today) ? $grantedDate->format('Y-m-d H:i:s') : date('Y-m-d H:i:s');

try {
    $stmt = db()->prepare(
        'SELECT id FROM access_permissions
         WHERE patient_id = ? AND provider_id = ? AND status IN (\'Active\', \'Requested\', \'Approved\')
         ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute([$patientId, $providerId]);
    $existing = $stmt->fetch();

    if ($existing) {
        $stmt = db()->prepare(
            'UPDATE access_permissions
             SET record_types = ?, expires_at = ?, granted_at = ?, status = \'Active\'
             WHERE id = ?'
        );
        $stmt->execute([$recordTypesList, $expiresDb, $grantedDb, (int)$existing['id']]);
    } else {
        $stmt = db()->prepare(
            'INSERT INTO access_permissions (patient_id, provider_id, provider_role, record_types, granted_at, expires_at, status)
             VALUES (?, ?, ?, ?, ?, ?, \'Active\')'
        );
        $stmt->execute([$patientId, $providerId, $providerRole, $recordTypesList, $grantedDb, $expiresDb]);
    }

    if ($providerRole === 'Doctor') {
        create_notification(
            $providerId,
            'Medical record access granted',
            'A patient has granted you access to their records until ' . $expiresDate->format('j M Y') . '.',
            'access'
        );
    }

    $_SESSION['success'] = 'Access permission saved. The provider can now view the selected record types until the expiration date.';
    redirect('../data_access.php');
} catch (PDOException $e) {
    $_SESSION['errors'] = ['Something went wrong. Please try again later.'];
    redirect('../data_access.php');
}
