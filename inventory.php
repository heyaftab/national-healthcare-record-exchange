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
$edit_id = (int)($_GET['edit'] ?? 0);

$medicines = db()->query(
    'SELECT id, name, generic_name, category, unit, reorder_level, price, is_active, created_at
       FROM medicines
      ORDER BY name ASC'
)->fetchAll();

foreach ($medicines as &$medicine) {
    $summary = medicine_stock_summary((int)$medicine['id']);
    $medicine['available'] = $summary['available'];
    $medicine['total'] = $summary['total'];
    $medicine['expiring'] = $summary['expiring'];
    if ($medicine['available'] <= 0) {
        $medicine['stock_label'] = 'OUT OF STOCK';
        $medicine['stock_class'] = 'bg-danger-subtle text-danger-emphasis';
    } elseif ($medicine['available'] < (int)$medicine['reorder_level']) {
        $medicine['stock_label'] = 'LOW STOCK';
        $medicine['stock_class'] = 'bg-warning-subtle text-warning-emphasis';
    } else {
        $medicine['stock_label'] = 'In stock';
        $medicine['stock_class'] = 'bg-success-subtle text-success-emphasis';
    }
}
unset($medicine);

$editMedicine = null;
if ($edit_id > 0) {
    foreach ($medicines as $medicine) {
        if ((int)$medicine['id'] === $edit_id) {
            $editMedicine = $medicine;
            break;
        }
    }
}

$lowStock = array_values(array_filter($medicines, static fn (array $m): bool => $m['available'] < (int)$m['reorder_level']));
$expiringMedicines = array_values(array_filter($medicines, static fn (array $m): bool => $m['expiring'] > 0));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Medicine Inventory - NHRE</title>
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
          <span class="auth-kicker">Medicine Catalog</span>
          <h1>Medicine Inventory</h1>
          <p>Manage the medicine catalog, reorder levels, and current stock position.</p>
        </div>
        <div class="dashboard-user-pill">
          <i class="fa-solid fa-boxes-stacked"></i>
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
        <div class="col-lg-4">
          <article class="dashboard-card">
            <div class="dashboard-card-icon"><i class="fa-solid fa-boxes-packing"></i></div>
            <h2><?= $editMedicine ? 'Edit medicine' : 'Add medicine' ?></h2>
            <p>Add a new medicine to the catalog or update its reorder level.</p>
            <form action="auth/medicine_process.php" method="POST" class="mt-3">
              <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
              <?php if ($editMedicine): ?><input type="hidden" name="medicine_id" value="<?= (int)$editMedicine['id'] ?>"><?php endif; ?>
              <div class="mb-3">
                <label class="form-label" for="name">Medicine name</label>
                <input type="text" class="form-control" id="name" name="name" maxlength="190" required value="<?= e((string)($old['name'] ?? ($editMedicine['name'] ?? ''))) ?>">
              </div>
              <div class="mb-3">
                <label class="form-label" for="generic_name">Generic name</label>
                <input type="text" class="form-control" id="generic_name" name="generic_name" maxlength="190" value="<?= e((string)($old['generic_name'] ?? ($editMedicine['generic_name'] ?? ''))) ?>">
              </div>
              <div class="mb-3">
                <label class="form-label" for="category">Category</label>
                <input type="text" class="form-control" id="category" name="category" maxlength="100" value="<?= e((string)($old['category'] ?? ($editMedicine['category'] ?? ''))) ?>">
              </div>
              <div class="mb-3">
                <label class="form-label" for="unit">Unit</label>
                <input type="text" class="form-control" id="unit" name="unit" maxlength="50" required value="<?= e((string)($old['unit'] ?? ($editMedicine['unit'] ?? 'tablet'))) ?>">
              </div>
              <div class="mb-3">
                <label class="form-label" for="reorder_level">Reorder level</label>
                <input type="number" class="form-control" id="reorder_level" name="reorder_level" min="0" max="100000" required value="<?= e((string)($old['reorder_level'] ?? ($editMedicine['reorder_level'] ?? 5))) ?>">
                <div class="form-text">Stock below this level is flagged as LOW STOCK.</div>
              </div>
              <div class="mb-3">
                <label class="form-label" for="price">Unit price (৳)</label>
                <input type="number" step="0.01" min="0" max="9999999.99" class="form-control" id="price" name="price" required value="<?= e((string)($old['price'] ?? ($editMedicine['price'] ?? '0.00'))) ?>">
                <div class="form-text">Current market retail price per <?= e($editMedicine['unit'] ?? 'unit') ?> in Bangladeshi Taka.</div>
              </div>
              <button type="submit" class="btn btn-solid-nhre w-100">
                <i class="fa-solid fa-floppy-disk"></i> <?= $editMedicine ? 'Save changes' : 'Add medicine' ?>
              </button>
              <?php if ($editMedicine): ?>
                <a href="inventory.php" class="btn btn-outline-nhre w-100 mt-2">Cancel edit</a>
              <?php endif; ?>
            </form>
          </article>

          <article class="dashboard-card mt-4">
            <div class="dashboard-card-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
            <h2>Low / Out of stock</h2>
            <?php if ($lowStock): ?>
              <ul class="list-group list-group-flush mt-2">
                <?php foreach ($lowStock as $m): ?>
                  <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
                    <span><?= e($m['name']) ?></span>
                    <span class="badge rounded-pill <?= $m['stock_class'] ?>"><?= $m['stock_label'] ?> (<?= $m['available'] ?>)</span>
                  </li>
                <?php endforeach; ?>
              </ul>
              <a href="stock.php" class="btn btn-outline-nhre w-100 mt-3">Restock from batches</a>
            <?php else: ?>
              <p class="text-muted mb-0 mt-2">All medicines are above their reorder level.</p>
            <?php endif; ?>
          </article>

          <?php if ($expiringMedicines): ?>
            <article class="dashboard-card mt-4">
              <div class="dashboard-card-icon"><i class="fa-solid fa-hourglass-half"></i></div>
              <h2>Expiring within 60 days</h2>
              <ul class="list-group list-group-flush mt-2">
                <?php foreach ($expiringMedicines as $m): ?>
                  <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
                    <span><?= e($m['name']) ?></span>
                    <span class="badge rounded-pill bg-warning-subtle text-warning-emphasis"><?= $m['expiring'] ?> <?= e($m['unit']) ?></span>
                  </li>
                <?php endforeach; ?>
              </ul>
            </article>
          <?php endif; ?>
        </div>

        <div class="col-lg-8">
          <article class="dashboard-card">
            <div class="dashboard-card-icon"><i class="fa-solid fa-table-list"></i></div>
            <h2>Medicine catalog</h2>
            <div class="table-responsive mt-3">
              <table class="table table-hover align-middle">
                <thead>
                  <tr>
                    <th>Medicine</th>
                    <th>Category</th>
                    <th>Unit</th>
                    <th class="text-end">Price</th>
                    <th>Reorder</th>
                    <th class="text-end">Available</th>
                    <th class="text-end">Total</th>
                    <th>Status</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($medicines as $medicine): ?>
                    <tr>
                      <td>
                        <strong><?= e($medicine['name']) ?></strong>
                        <?php if (!empty($medicine['generic_name'])): ?><div class="small text-muted"><?= e($medicine['generic_name']) ?></div><?php endif; ?>
                      </td>
                      <td><?= $medicine['category'] ? e($medicine['category']) : '—' ?></td>
                      <td><?= e($medicine['unit']) ?></td>
                      <td class="text-end">৳<?= number_format((float)$medicine['price'], 2) ?></td>
                      <td><?= (int)$medicine['reorder_level'] ?></td>
                      <td class="text-end"><?= $medicine['available'] ?></td>
                      <td class="text-end"><?= $medicine['total'] ?></td>
                      <td><span class="badge rounded-pill <?= $medicine['stock_class'] ?>"><?= e($medicine['stock_label']) ?></span></td>
                      <td class="text-end">
                        <a href="inventory.php?edit=<?= (int)$medicine['id'] ?>" class="btn btn-sm btn-outline-nhre"><i class="fa-solid fa-pen"></i></a>
                        <a href="stock.php?medicine=<?= (int)$medicine['id'] ?>" class="btn btn-sm btn-solid-nhre"><i class="fa-solid fa-boxes-packing"></i> Batches</a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </article>
        </div>
      </div>
    </section>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/app.js?v=20260811-8"></script>
</body>
</html>
