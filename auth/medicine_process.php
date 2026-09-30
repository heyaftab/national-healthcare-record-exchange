<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../includes/pharmacy_functions.php';
require_role(['Pharmacist']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../inventory.php');
}

if (!csrf_check($_POST['_csrf'] ?? null)) {
    $_SESSION['errors'] = ['Session expired. Please try again.'];
    redirect('../inventory.php');
}

$id = (int)($_POST['medicine_id'] ?? 0);
$name = trim((string)($_POST['name'] ?? ''));
$generic_name = trim((string)($_POST['generic_name'] ?? ''));
$category = trim((string)($_POST['category'] ?? ''));
$unit = trim((string)($_POST['unit'] ?? ''));
$reorder_level = trim((string)($_POST['reorder_level'] ?? ''));
$price = trim((string)($_POST['price'] ?? ''));

$errors = [];

if (mb_strlen($name) < 2 || mb_strlen($name) > 190) {
    $errors[] = 'Medicine name is required (2 to 190 characters).';
}
if (mb_strlen($generic_name) > 190) {
    $errors[] = 'Generic name must be 190 characters or fewer.';
}
if (mb_strlen($category) > 100) {
    $errors[] = 'Category must be 100 characters or fewer.';
}
if ($unit === '' || mb_strlen($unit) > 50) {
    $errors[] = 'Unit is required (e.g. tablet, capsule, ml, sachet).';
}
if ($reorder_level !== '' && (!ctype_digit($reorder_level) || (int)$reorder_level < 0 || (int)$reorder_level > 100000)) {
    $errors[] = 'Reorder level must be a whole number between 0 and 100000.';
}
if ($price === '' || !is_numeric($price) || (float)$price < 0 || (float)$price > 9999999.99) {
    $errors[] = 'Price must be a valid amount in Taka (0 to 9,999,999.99).';
}

if (!$errors) {
    try {
        $stmt = db()->prepare('SELECT id FROM medicines WHERE name = ? AND id <> ? LIMIT 1');
        $stmt->execute([$name, $id]);
        if ($stmt->fetch()) {
            $errors[] = 'A medicine with this name already exists.';
        }
    } catch (PDOException $e) {
        $errors[] = 'Something went wrong. Please try again.';
    }
}

$back = $id > 0 ? '../inventory.php?edit=' . $id : '../inventory.php';

if ($errors) {
    $_SESSION['errors'] = $errors;
    $_SESSION['old'] = [
        'name' => $name,
        'generic_name' => $generic_name,
        'category' => $category,
        'unit' => $unit,
        'reorder_level' => $reorder_level,
        'price' => $price,
    ];
    redirect($back);
}

$priceValue = round((float)$price, 2);

try {
    if ($id > 0) {
        $stmt = db()->prepare(
            'UPDATE medicines
                SET name = ?, generic_name = ?, category = ?, unit = ?, reorder_level = ?, price = ?, is_active = 1
              WHERE id = ?'
        );
        $stmt->execute([$name, $generic_name !== '' ? $generic_name : null, $category !== '' ? $category : null, $unit, (int)$reorder_level, $priceValue, $id]);
        log_audit('UPDATE_INVENTORY', 'medicine', $id, 'Updated medicine "' . $name . '" (reorder level ' . $reorder_level . ', price ৳' . number_format($priceValue, 2) . ')');
        $_SESSION['success'] = 'Medicine updated successfully.';
    } else {
        $stmt = db()->prepare(
            'INSERT INTO medicines (name, generic_name, category, unit, reorder_level, price, is_active)
             VALUES (?, ?, ?, ?, ?, ?, 1)'
        );
        $stmt->execute([$name, $generic_name !== '' ? $generic_name : null, $category !== '' ? $category : null, $unit, (int)$reorder_level, $priceValue]);
        $new_id = (int)db()->lastInsertId();
        log_audit('UPDATE_INVENTORY', 'medicine', $new_id, 'Added medicine "' . $name . '" (reorder level ' . $reorder_level . ', price ৳' . number_format($priceValue, 2) . ')');
        $_SESSION['success'] = 'Medicine added to inventory.';
    }
} catch (PDOException $e) {
    $_SESSION['errors'] = ['Could not save the medicine. Please try again.'];
    redirect($back);
}

redirect($back);
