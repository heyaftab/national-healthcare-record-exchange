<?php
require_once __DIR__ . '/auth/auth_check.php';
require_once __DIR__ . '/includes/pharmacy_functions.php';
require_role(['Patient', 'Pharmacist']);

ensure_pharmacy_tables();
expire_stale_prescriptions();

$fullname = $_SESSION['fullname'] ?? 'NHRE User';
$email = $_SESSION['email'] ?? '';
$role = $_SESSION['role'] ?? 'User';

$errors = session_pull('errors', []);
$old = session_pull('old', []);
$success = session_pull('success');
$pharmacyQuery = trim((string)($_GET['pharmacy_query'] ?? ''));

if ($role === 'Pharmacist') {
    try {
        $stats = [];
        foreach (db()->query("SELECT status, COUNT(*) AS c FROM prescriptions GROUP BY status")->fetchAll() as $row) {
            $stats[(string)$row['status']] = (int)$row['c'];
        }
        $stats['dispensed_today'] = (int)db()->query("SELECT COUNT(*) FROM dispensings WHERE DATE(created_at) = CURDATE()")->fetchColumn();

        $recent = db()->query(
            'SELECT p.id, p.prescription_no, p.status, p.created_at, pat.fullname AS patient_name
               FROM prescriptions p
               JOIN users pat ON pat.id = p.patient_id
              ORDER BY p.created_at DESC
              LIMIT 8'
        )->fetchAll();

        $medicines = db()->query(
            'SELECT id, name, unit, reorder_level FROM medicines WHERE is_active = 1 ORDER BY name ASC'
        )->fetchAll();
        $lowStock = [];
        $medicineLookup = [];
        foreach ($medicines as $medicine) {
            $summary = medicine_stock_summary((int)$medicine['id']);
            $medicine['available'] = $summary['available'];
            $medicine['expiring'] = $summary['expiring'];
            $medicine['total'] = $summary['total'];
            $medicineLookup[] = $medicine;
            if ($summary['available'] < (int)$medicine['reorder_level']) {
                $lowStock[] = $medicine;
            }
        }

        $pharmacyResults = [];
        if ($pharmacyQuery !== '') {
            foreach ($medicineLookup as $medicine) {
                $name = strtolower((string)$medicine['name']);
                if (stripos((string)$medicine['name'], $pharmacyQuery) === false && stripos($name, strtolower($pharmacyQuery)) === false) {
                    continue;
                }
                $pharmacyResults[] = $medicine;
            }
        } else {
            $pharmacyResults = array_slice($medicineLookup, 0, 6);
        }

        $nearExpiry = [];
        foreach ($medicineLookup as $medicine) {
            if ((int)$medicine['expiring'] > 0) {
                $nearExpiry[] = $medicine;
            }
        }
        usort($nearExpiry, static fn($a, $b) => (int)$b['expiring'] <=> (int)$a['expiring']);
        $nearExpiry = array_slice($nearExpiry, 0, 5);
    } catch (PDOException $e) {
        $stats = [];
        $recent = [];
        $lowStock = [];
        $pharmacyResults = [];
        $nearExpiry = [];
    }
} else {
    $recent_requests = [];
    try {
        $stmt = db()->prepare(
            'SELECT medicine_name, notes, status, created_at
               FROM pharmacy_requests
              WHERE user_id = ?
              ORDER BY created_at DESC
              LIMIT 10'
        );
        $stmt->execute([(int)$_SESSION['user_id']]);
        $recent_requests = $stmt->fetchAll();
    } catch (PDOException $e) {
        $recent_requests = [];
    }

    $medicines = [];
    try {
        $rows = db()->query(
            'SELECT m.name, m.category, m.unit, m.reorder_level,
                    (SELECT COALESCE(SUM(b.quantity_remaining), 0) FROM medicine_batches b WHERE b.medicine_id = m.id AND b.expiry_date > CURDATE()) AS available
               FROM medicines m
              WHERE m.is_active = 1
              ORDER BY m.name ASC
              LIMIT 20'
        )->fetchAll();
        foreach ($rows as $row) {
            $medicines[] = [
                'name' => $row['name'],
                'category' => $row['category'] ?: 'General',
                'stock' => (int)$row['available'] > 0 ? 'In stock' : 'Unavailable',
                'unit' => $row['unit'],
            ];
        }
    } catch (PDOException $e) {
        $medicines = [];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pharmacy<?= $role === 'Pharmacist' ? ' Dashboard' : '' ?> - NHRE</title>
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
          <span class="auth-kicker"><?= $role === 'Pharmacist' ? 'Pharmacy Dashboard' : 'Pharmacy Section' ?></span>
          <h1><?= $role === 'Pharmacist' ? 'Pharmacy workspace for ' . e($fullname) : 'Medicine access for ' . e($fullname) ?></h1>
          <p><?= $role === 'Pharmacist' ? 'Review prescriptions, manage inventory, and dispense medicines.' : 'Browse available medicines, check availability, and request pharmacy support.' ?></p>
        </div>
        <div class="dashboard-user-pill">
          <i class="fa-solid fa-pills"></i>
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

      <?php if ($role === 'Pharmacist'): ?>
        <div class="row g-4 mt-1 dashboard-cards">
          <div class="col-6 col-xl-3">
            <article class="dashboard-card">
              <div class="dashboard-card-icon"><i class="fa-solid fa-clock"></i></div>
              <h2><?= (int)($stats['PENDING'] ?? 0) ?></h2>
              <p>Pending prescriptions</p>
              <a href="prescriptions.php?status=PENDING" class="dashboard-card-link">Review</a>
            </article>
          </div>
          <div class="col-6 col-xl-3">
            <article class="dashboard-card">
              <div class="dashboard-card-icon"><i class="fa-solid fa-box-open"></i></div>
              <h2><?= (int)($stats['VERIFIED'] ?? 0) + (int)($stats['READY'] ?? 0) ?></h2>
              <p>Verified / ready</p>
              <a href="prescriptions.php?status=VERIFIED" class="dashboard-card-link">Prepare</a>
            </article>
          </div>
          <div class="col-6 col-xl-3">
            <article class="dashboard-card">
              <div class="dashboard-card-icon"><i class="fa-solid fa-hand-holding-medical"></i></div>
              <h2><?= (int)($stats['dispensed_today'] ?? 0) ?></h2>
              <p>Dispensed today</p>
              <a href="dispensing_history.php" class="dashboard-card-link">History</a>
            </article>
          </div>
          <div class="col-6 col-xl-3">
            <article class="dashboard-card">
              <div class="dashboard-card-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
              <h2><?= count($lowStock) ?></h2>
              <p>Low / out of stock</p>
              <a href="stock.php" class="dashboard-card-link">Restock</a>
            </article>
          </div>
        </div>

        <div class="row g-4 mt-1">
          <div class="col-12">
            <article class="dashboard-card">
              <div class="dashboard-card-icon"><i class="fa-solid fa-magnifying-glass"></i></div>
              <h2>Medicine quick search</h2>
              <form method="GET" action="pharmacy.php" class="mt-3">
                <div class="input-group">
                  <input type="text" class="form-control" name="pharmacy_query" value="<?= e($pharmacyQuery) ?>" placeholder="Search medicine by name or part of the name">
                  <button class="btn btn-solid-nhre" type="submit"><i class="fa-solid fa-search"></i> Search</button>
                </div>
              </form>

              <?php if ($pharmacyResults): ?>
                <div class="table-responsive mt-3">
                  <table class="table table-hover align-middle">
                    <thead>
                      <tr>
                        <th>Medicine</th>
                        <th>Available</th>
                        <th>Reorder</th>
                        <th>Expiring soon</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($pharmacyResults as $medicine): ?>
                        <tr>
                          <td><?= e((string)$medicine['name']) ?></td>
                          <td><?= (int)$medicine['available'] ?></td>
                          <td><?= (int)$medicine['reorder_level'] ?></td>
                          <td><?= (int)$medicine['expiring'] ?></td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              <?php else: ?>
                <p class="text-muted mt-3 mb-0">No medicines matched the current search.</p>
              <?php endif; ?>
            </article>
          </div>
        </div>

        <div class="row g-4 mt-1">
          <div class="col-lg-5">
            <article class="dashboard-card">
              <div class="dashboard-card-icon"><i class="fa-solid fa-clock-rotate-left"></i></div>
              <h2>Near-expiry medicines</h2>
              <?php if ($nearExpiry): ?>
                <div class="list-group list-group-flush mt-3">
                  <?php foreach ($nearExpiry as $medicine): ?>
                    <div class="list-group-item px-0">
                      <div class="d-flex justify-content-between align-items-center">
                        <strong><?= e((string)$medicine['name']) ?></strong>
                        <span class="badge rounded-pill bg-warning-subtle text-warning-emphasis"><?= (int)$medicine['expiring'] ?> units</span>
                      </div>
                      <small class="text-muted">Available: <?= (int)$medicine['available'] ?> • Reorder: <?= (int)$medicine['reorder_level'] ?></small>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php else: ?>
                <p class="text-muted mt-3 mb-0">No medicines are approaching expiry.</p>
              <?php endif; ?>
            </article>
          </div>
          <div class="col-lg-7">
            <article class="dashboard-card">
              <div class="dashboard-card-icon"><i class="fa-solid fa-prescription"></i></div>
              <h2>Recent prescriptions</h2>
              <?php if ($recent): ?>
                <div class="list-group list-group-flush mt-3">
                  <?php foreach ($recent as $prescription): ?>
                    <a href="prescription_view.php?id=<?= (int)$prescription['id'] ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                      <div>
                        <strong><?= e($prescription['prescription_no']) ?></strong>
                        <div class="text-muted small"><?= e($prescription['patient_name']) ?> • <?= e(date('j M Y, g:i a', strtotime($prescription['created_at']))) ?></div>
                      </div>
                      <?= pharmacy_status_badge((string)$prescription['status']) ?>
                    </a>
                  <?php endforeach; ?>
                </div>
              <?php else: ?>
                <p class="text-muted mt-3">No prescriptions yet.</p>
              <?php endif; ?>
            </article>
          </div>
        </div>

        <div class="row g-4 mt-1">
          <div class="col-12">
            <article class="dashboard-card">
              <div class="dashboard-card-icon"><i class="fa-solid fa-boxes-stacked"></i></div>
              <h2>Inventory shortcuts</h2>
              <div class="d-grid gap-2 mt-3">
                <a href="inventory.php" class="btn btn-solid-nhre"><i class="fa-solid fa-boxes-stacked"></i> Medicine inventory</a>
                <a href="stock.php" class="btn btn-outline-nhre"><i class="fa-solid fa-boxes-packing"></i> Stock management</a>
                <a href="dispensing_history.php" class="btn btn-outline-nhre"><i class="fa-solid fa-clock-rotate-left"></i> Dispensing history</a>
                <a href="patient_search.php" class="btn btn-outline-nhre"><i class="fa-solid fa-magnifying-glass"></i> Patient search</a>
              </div>
            </article>
          </div>
        </div>
      <?php else: ?>
        <div class="row g-4">
          <div class="col-lg-8">
            <div class="row g-4">
              <?php if ($medicines): ?>
                <?php foreach ($medicines as $medicine): ?>
                  <div class="col-md-6">
                    <article class="dashboard-card">
                      <div class="dashboard-card-icon"><i class="fa-solid fa-capsules"></i></div>
                      <h2><?= e($medicine['name']) ?></h2>
                      <p class="mb-2"><strong><?= e($medicine['category']) ?></strong> • <?= e($medicine['unit']) ?></p>
                      <span class="badge rounded-pill <?= $medicine['stock'] === 'In stock' ? 'bg-success-subtle text-success-emphasis' : 'bg-danger-subtle text-danger-emphasis' ?>">
                        <?= e($medicine['stock']) ?>
                      </span>
                    </article>
                  </div>
                <?php endforeach; ?>
              <?php else: ?>
                <div class="col-12"><p class="text-muted">The medicine catalog is being prepared.</p></div>
              <?php endif; ?>
            </div>
          </div>

          <div class="col-lg-4">
            <article class="dashboard-card">
              <div class="dashboard-card-icon"><i class="fa-solid fa-file-medical"></i></div>
              <h2>Request Pharmacy Support</h2>
              <p>Need urgent medication assistance? Submit a request and our team will follow up.</p>
              <form class="mt-3" action="auth/pharmacy_request_process.php" method="POST">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <div class="mb-3">
                  <label class="form-label" for="medicine_name">Medicine Name</label>
                  <input type="text" class="form-control" id="medicine_name" name="medicine_name" placeholder="e.g. Paracetamol" value="<?= e((string)($old['medicine_name'] ?? '')) ?>" required>
                </div>
                <div class="mb-3">
                  <label class="form-label" for="notes">Prescription Notes</label>
                  <textarea class="form-control" id="notes" name="notes" rows="4" placeholder="Add dosage or urgency details"><?= e((string)($old['notes'] ?? '')) ?></textarea>
                </div>
                <button type="submit" class="btn btn-solid-nhre w-100">
                  <i class="fa-solid fa-paper-plane"></i> Submit Request
                </button>
              </form>

              <?php if ($recent_requests): ?>
                <hr class="my-4">
                <h2 class="fs-6">Recent Requests</h2>
                <ul class="list-group list-group-flush mt-3">
                  <?php foreach ($recent_requests as $request): ?>
                    <li class="list-group-item px-0">
                      <div class="d-flex justify-content-between align-items-center">
                        <strong><?= e($request['medicine_name']) ?></strong>
                        <span class="badge bg-secondary text-white text-capitalize"><?= e($request['status']) ?></span>
                      </div>
                      <small class="text-muted"><?= e(date('j M Y, g:i a', strtotime($request['created_at']))) ?></small>
                    </li>
                  <?php endforeach; ?>
                </ul>
              <?php endif; ?>
            </article>
          </div>
        </div>
      <?php endif; ?>
    </section>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/app.js?v=20260818-10"></script>
</body>
</html>
