<?php
require_once __DIR__ . '/auth/auth_check.php';
require_once __DIR__ . '/includes/pharmacy_functions.php';
require_role(['Pharmacist']);

ensure_pharmacy_tables();

$fullname = $_SESSION['fullname'] ?? 'NHRE User';
$role = $_SESSION['role'] ?? 'User';
$errors = session_pull('errors', []);
$success = session_pull('success');
$old = session_pull('old', []);

$hospital_id = current_user_hospital_id();

$medicines = db()->query(
    'SELECT id, name, unit, reorder_level FROM medicines WHERE is_active = 1 ORDER BY name ASC'
)->fetchAll();

$selected_medicine_id = (int)($_GET['medicine'] ?? (int)($old['medicine_id'] ?? 0));
$selectedMedicine = null;
$batches = [];

if ($selected_medicine_id > 0) {
    foreach ($medicines as $medicine) {
        if ((int)$medicine['id'] === $selected_medicine_id) {
            $selectedMedicine = $medicine;
            break;
        }
    }
}

if ($selectedMedicine) {
    $sql = 'SELECT b.id, b.batch_no, b.expiry_date, b.quantity_remaining, b.hospital_id, b.created_at,
                   u.fullname AS created_by_name
              FROM medicine_batches b
              LEFT JOIN users u ON u.id = b.created_by
             WHERE b.medicine_id = ?';
    $params = [$selected_medicine_id];
    if ($hospital_id !== null) {
        $sql .= ' AND (b.hospital_id = ? OR b.hospital_id IS NULL)';
        $params[] = $hospital_id;
    }
    $sql .= ' ORDER BY b.expiry_date ASC, b.id ASC';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $batches = $stmt->fetchAll();
}

foreach ($batches as &$batch) {
    $expiry = strtotime((string)$batch['expiry_date']);
    $today = strtotime(date('Y-m-d'));
    if ($expiry < $today) {
        $batch['label'] = 'EXPIRED';
        $batch['class'] = 'bg-danger-subtle text-danger-emphasis';
    } elseif ($expiry <= strtotime('+60 days')) {
        $batch['label'] = 'Expiring soon';
        $batch['class'] = 'bg-warning-subtle text-warning-emphasis';
    } else {
        $batch['label'] = 'OK';
        $batch['class'] = 'bg-success-subtle text-success-emphasis';
    }
}
unset($batch);

$lowStock = [];
foreach ($medicines as $medicine) {
    $summary = medicine_stock_summary((int)$medicine['id']);
    if ($summary['available'] < (int)$medicine['reorder_level']) {
        $medicine['available'] = $summary['available'];
        $lowStock[] = $medicine;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Stock Management - NHRE</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="assets/css/styles.css?v=20260818-18">
<script>
  (function () {
    try {
      var t = localStorage.getItem("nhre-theme");
      if (t !== "light" && t !== "dark") {
        t = window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light";
      }
      document.documentElement.dataset.theme = t;
      document.documentElement.style.colorScheme = t;
    } catch (e) {}
  })();
</script>
</head>
<body class="dashboard-body">
  <?php require __DIR__ . '/includes/sidebar.php'; ?>
  <?php require __DIR__ . '/includes/topnav.php'; ?>
  <main class="dashboard-main">
    <section class="container">
      <div class="dashboard-hero glass-card">
        <div>
          <span class="auth-kicker">Stock Management</span>
          <h1>Medicine Batches &amp; Stock</h1>
          <p>Track expiry dates per batch. Dispensing uses the soonest-expiring stock first (FEFO).</p>
        </div>
        <div class="dashboard-user-pill">
          <i class="fa-solid fa-boxes-packing"></i>
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

      <div class="d-flex flex-wrap gap-2 mt-4">
        <a class="btn btn-outline-primary" href="stock_out.php"><i class="fa-solid fa-minus-circle"></i> Stock Out</a>
        <a class="btn btn-outline-primary" href="stock_adjustment.php"><i class="fa-solid fa-sliders"></i> Stock Adjustment</a>
        <a class="btn btn-outline-primary" href="activity_log.php"><i class="fa-solid fa-clipboard-list"></i> Audit Log</a>
      </div>

      <div class="row g-4 mt-1">
        <div class="col-lg-5">
          <article class="dashboard-card">
            <div class="dashboard-card-icon"><i class="fa-solid fa-cube"></i></div>
            <h2>Add stock</h2>
            <p>Register a new delivery batch for a medicine.</p>
            <form action="auth/medicine_stock_process.php" method="POST" class="mt-3">
              <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
              <div class="mb-3">
                <label class="form-label" for="medicine_id">Medicine</label>
                <select class="form-select" id="medicine_id" name="medicine_id" required>
                  <option value="">Select medicine</option>
                  <?php foreach ($medicines as $medicine): ?>
                    <option value="<?= (int)$medicine['id'] ?>" <?= (int)($old['medicine_id'] ?? $selected_medicine_id) === (int)$medicine['id'] ? 'selected' : '' ?>>
                      <?= e($medicine['name']) ?> (<?= e($medicine['unit']) ?>)
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="mb-3">
                <label class="form-label" for="batch_no">Batch number</label>
                <input type="text" class="form-control" id="batch_no" name="batch_no" maxlength="100" required placeholder="e.g. PAR-2605" value="<?= e((string)($old['batch_no'] ?? '')) ?>">
              </div>
              <div class="mb-3">
                <label class="form-label" for="expiry_date">Expiry date</label>
                <input type="date" class="form-control" id="expiry_date" name="expiry_date" required min="<?= date('Y-m-d', strtotime('+1 day')) ?>" value="<?= e((string)($old['expiry_date'] ?? '')) ?>">
              </div>
              <div class="mb-3">
                <label class="form-label" for="quantity">Quantity</label>
                <input type="number" class="form-control" id="quantity" name="quantity" min="1" max="1000000" required value="<?= e((string)($old['quantity'] ?? '')) ?>">
              </div>
              <button type="submit" class="btn btn-solid-nhre w-100">
                <i class="fa-solid fa-boxes-stacked"></i> Add stock
              </button>
            </form>
          </article>

          <?php if ($lowStock): ?>
            <article class="dashboard-card mt-4">
              <div class="dashboard-card-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
              <h2>Needs restocking</h2>
              <ul class="list-group list-group-flush mt-2">
                <?php foreach ($lowStock as $medicine): ?>
                  <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
                    <span><?= e($medicine['name']) ?></span>
                    <span class="badge rounded-pill <?= $medicine['available'] <= 0 ? 'bg-danger-subtle text-danger-emphasis' : 'bg-warning-subtle text-warning-emphasis' ?>">
                      <?= $medicine['available'] <= 0 ? 'OUT OF STOCK' : 'LOW (' . $medicine['available'] . ')' ?>
                    </span>
                  </li>
                <?php endforeach; ?>
              </ul>
            </article>
          <?php endif; ?>
        </div>

        <div class="col-lg-7">
          <article class="dashboard-card">
            <div class="dashboard-card-icon"><i class="fa-solid fa-box-open"></i></div>
            <h2>Select a medicine to inspect batches</h2>
            <form method="GET" action="stock.php" class="mt-3">
              <div class="d-flex gap-2">
                <select class="form-select" name="medicine" required>
                  <option value="">Choose medicine…</option>
                  <?php foreach ($medicines as $medicine): ?>
                    <option value="<?= (int)$medicine['id'] ?>" <?= $selected_medicine_id === (int)$medicine['id'] ? 'selected' : '' ?>>
                      <?= e($medicine['name']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-solid-nhre flex-shrink-0"><i class="fa-solid fa-magnifying-glass"></i> View</button>
              </div>
            </form>

            <?php if ($selectedMedicine): ?>
              <hr>
              <h2 class="fs-5"><?= e($selectedMedicine['name']) ?> — batches</h2>
              <div class="table-responsive mt-3">
                <table class="table table-hover align-middle">
                  <thead>
                    <tr>
                      <th>Batch</th>
                      <th>Expiry</th>
                      <th class="text-end">Remaining</th>
                      <th>Status</th>
                      <th>Added by</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($batches as $batch): ?>
                      <tr>
                        <td><?= e($batch['batch_no']) ?></td>
                        <td><?= e(date('j M Y', strtotime((string)$batch['expiry_date']))) ?></td>
                        <td class="text-end"><?= (int)$batch['quantity_remaining'] ?> <?= e($selectedMedicine['unit']) ?></td>
                        <td><span class="badge rounded-pill <?= $batch['class'] ?>"><?= e($batch['label']) ?></span></td>
                        <td><?= e((string)($batch['created_by_name'] ?? 'System')) ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
              <?php if (!$batches): ?>
                <p class="text-muted mt-3">No batches recorded for this medicine yet.</p>
              <?php endif; ?>
            <?php endif; ?>
          </article>
        </div>
      </div>
    </section>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/app.js?v=20260811-8"></script>
</body>
</html>
