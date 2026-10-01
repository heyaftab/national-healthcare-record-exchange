<?php
require_once __DIR__ . '/auth/auth_check.php';
require_once __DIR__ . '/includes/pharmacy_functions.php';
require_role(['Pharmacist']);
ensure_pharmacy_tables();

$errors = session_pull('errors', []);
$success = session_pull('success');
$old = session_pull('old', []);

$medicines = db()->query(
    'SELECT id, name, unit FROM medicines WHERE is_active = 1 ORDER BY name ASC'
)->fetchAll();

$selectedMedicineId = (int)($_GET['medicine'] ?? (int)($old['medicine_id'] ?? 0));
$batchRows = [];
if ($selectedMedicineId > 0) {
    $stmt = db()->prepare(
        'SELECT b.id, b.batch_no, b.expiry_date, b.quantity_remaining
           FROM medicine_batches b
          WHERE b.medicine_id = ?
          ORDER BY b.expiry_date ASC, b.id ASC'
    );
    $stmt->execute([$selectedMedicineId]);
    $batchRows = $stmt->fetchAll();
}

$role = $_SESSION['role'] ?? 'User';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Stock Adjustment - NHRE</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="assets/css/styles.css?v=20260818-18">
</head>
<body class="dashboard-body">
  <?php require __DIR__ . '/includes/sidebar.php'; ?>
  <?php require __DIR__ . '/includes/topnav.php'; ?>
  <main class="dashboard-main">
    <section class="container">
      <div class="dashboard-hero glass-card">
        <div>
          <span class="auth-kicker">Inventory Control</span>
          <h1>Stock Adjustment</h1>
          <p>Correct batches for counting errors, damaged stock, returns, or other approved inventory adjustments.</p>
        </div>
        <div class="dashboard-user-pill">
          <i class="fa-solid fa-sliders"></i>
          <span><?= e($role) ?></span>
        </div>
      </div>

      <?php if ($errors): ?>
        <div class="alert alert-danger auth-alert mt-4" role="alert">
          <i class="fa-solid fa-circle-exclamation"></i>
          <div>
            <?php foreach ($errors as $message): ?>
              <div><?= e($message) ?></div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <?php if ($success): ?>
        <div class="alert alert-success auth-alert mt-4" role="alert">
          <i class="fa-solid fa-circle-check"></i>
          <span><?= e($success) ?></span>
        </div>
      <?php endif; ?>

      <div class="row g-4 mt-1">
        <div class="col-lg-5">
          <article class="dashboard-card">
            <div class="dashboard-card-icon"><i class="fa-solid fa-table-list"></i></div>
            <h2>Adjustment form</h2>
            <form action="auth/stock_adjustment_process.php" method="POST" class="mt-3">
              <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">

              <div class="mb-3">
                <label class="form-label" for="medicine_id">Medicine</label>
                <select class="form-select" id="medicine_id" name="medicine_id" required onchange="this.form.submit()">
                  <option value="">Select medicine</option>
                  <?php foreach ($medicines as $medicine): ?>
                    <option value="<?= (int)$medicine['id'] ?>" <?= $selectedMedicineId === (int)$medicine['id'] ? 'selected' : '' ?>>
                      <?= e($medicine['name']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </form>

            <?php if ($selectedMedicineId > 0): ?>
              <?php $selectedMedicine = db()->prepare('SELECT id, name FROM medicines WHERE id = ? LIMIT 1'); $selectedMedicine->execute([$selectedMedicineId]); $selectedMedicine = $selectedMedicine->fetch(); ?>
              <?php if ($selectedMedicine): ?>
                <form action="auth/stock_adjustment_process.php" method="POST" class="mt-3">
                  <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                  <input type="hidden" name="medicine_id" value="<?= (int)$selectedMedicine['id'] ?>">

                  <div class="mb-3">
                    <label class="form-label" for="batch_id">Batch</label>
                    <select class="form-select" id="batch_id" name="batch_id" required>
                      <option value="">Choose batch</option>
                      <?php foreach ($batchRows as $batch): ?>
                        <option value="<?= (int)$batch['id'] ?>" <?= (string)($old['batch_id'] ?? '') === (string)$batch['id'] ? 'selected' : '' ?>>
                          <?= e((string)$batch['batch_no']) ?> — <?= e((string)$batch['expiry_date']) ?> (<?= (int)$batch['quantity_remaining'] ?> left)
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>

                  <div class="mb-3">
                    <label class="form-label" for="adjustment_type">Adjustment</label>
                    <select class="form-select" id="adjustment_type" name="adjustment_type" required>
                      <option value="">Select adjustment</option>
                      <option value="increase" <?= (($old['adjustment_type'] ?? '') === 'increase') ? 'selected' : '' ?>>Increase stock</option>
                      <option value="decrease" <?= (($old['adjustment_type'] ?? '') === 'decrease') ? 'selected' : '' ?>>Decrease stock</option>
                    </select>
                  </div>

                  <div class="mb-3">
                    <label class="form-label" for="quantity">Quantity</label>
                    <input type="number" class="form-control" id="quantity" name="quantity" min="1" value="<?= e((string)($old['quantity'] ?? '')) ?>" required>
                  </div>

                  <div class="mb-3">
                    <label class="form-label" for="reason">Reason</label>
                    <select class="form-select" id="reason" name="reason" required>
                      <option value="">Select reason</option>
                      <option value="count_correction" <?= (($old['reason'] ?? '') === 'count_correction') ? 'selected' : '' ?>>Count correction</option>
                      <option value="damaged" <?= (($old['reason'] ?? '') === 'damaged') ? 'selected' : '' ?>>Damaged</option>
                      <option value="return" <?= (($old['reason'] ?? '') === 'return') ? 'selected' : '' ?>>Return / Reversal</option>
                      <option value="expired" <?= (($old['reason'] ?? '') === 'expired') ? 'selected' : '' ?>>Expired</option>
                      <option value="other" <?= (($old['reason'] ?? '') === 'other') ? 'selected' : '' ?>>Other</option>
                    </select>
                  </div>

                  <div class="mb-3">
                    <label class="form-label" for="notes">Notes</label>
                    <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Optional details for the adjustment"><?= e((string)($old['notes'] ?? '')) ?></textarea>
                  </div>

                  <button type="submit" class="btn btn-solid-nhre w-100">
                    <i class="fa-solid fa-arrows-rotate"></i> Save adjustment
                  </button>
                </form>
              <?php else: ?>
                <p class="text-muted mt-3">This medicine is not available.</p>
              <?php endif; ?>
            <?php endif; ?>
          </article>
        </div>

        <div class="col-lg-7">
          <article class="dashboard-card">
            <div class="dashboard-card-icon"><i class="fa-solid fa-pills"></i></div>
            <h2>Available batch summary</h2>
            <?php if (!$batchRows): ?>
              <p class="text-muted mt-3">No batch records are available for this medicine yet.</p>
            <?php else: ?>
              <div class="table-responsive mt-3">
                <table class="table table-hover align-middle">
                  <thead>
                    <tr>
                      <th>Batch</th>
                      <th>Expiry</th>
                      <th class="text-end">Remaining</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($batchRows as $batch): ?>
                      <tr>
                        <td><?= e((string)$batch['batch_no']) ?></td>
                        <td><?= e((string)$batch['expiry_date']) ?></td>
                        <td class="text-end"><?= (int)$batch['quantity_remaining'] ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </article>
        </div>
      </div>
    </section>
  </main>
</body>
</html>
