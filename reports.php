<?php
require_once __DIR__ . '/auth/auth_check.php';
require_once __DIR__ . '/includes/pharmacy_functions.php';
require_role(['Pharmacist']);

ensure_pharmacy_tables();
expire_stale_prescriptions();

$fullname = $_SESSION['fullname'] ?? 'NHRE User';
$role = $_SESSION['role'] ?? 'User';
$errors = session_pull('errors', []);
$success = session_pull('success');

$stats = [
    'prescriptions_total' => 0,
    'prescriptions_pending' => 0,
    'prescriptions_verified' => 0,
    'dispensed_today' => 0,
    'low_stock' => 0,
    'expiring_soon' => 0,
];

$lowStock = [];
$recentDispensing = [];
$byStatus = [];

try {
    $stats['prescriptions_total'] = (int)db()->query('SELECT COUNT(*) FROM prescriptions')->fetchColumn();
    $stats['prescriptions_pending'] = (int)db()->query("SELECT COUNT(*) FROM prescriptions WHERE status = 'PENDING'")->fetchColumn();
    $stats['prescriptions_verified'] = (int)db()->query("SELECT COUNT(*) FROM prescriptions WHERE status IN ('VERIFIED', 'READY', 'PARTIALLY_DISPENSED')")->fetchColumn();
    $stats['dispensed_today'] = (int)db()->query("SELECT COUNT(*) FROM dispensings WHERE DATE(created_at) = CURDATE()")->fetchColumn();

    foreach (db()->query("SELECT status, COUNT(*) AS c FROM prescriptions GROUP BY status ORDER BY status ASC")->fetchAll() as $row) {
        $byStatus[(string)$row['status']] = (int)$row['c'];
    }

    $lowStock = db()->query(
        'SELECT m.id, m.name, m.unit, m.reorder_level, COALESCE(SUM(b.quantity_remaining), 0) AS available
           FROM medicines m
           LEFT JOIN medicine_batches b ON b.medicine_id = m.id AND b.expiry_date > CURDATE() AND b.quantity_remaining > 0
          GROUP BY m.id, m.name, m.unit, m.reorder_level
         HAVING available < m.reorder_level
          ORDER BY available ASC, m.name ASC
          LIMIT 10'
    )->fetchAll();
    $stats['low_stock'] = count($lowStock);

    $expiringSoon = db()->query(
        'SELECT m.id, m.name, m.unit, SUM(b.quantity_remaining) AS expiring_units
           FROM medicines m
           JOIN medicine_batches b ON b.medicine_id = m.id
          WHERE b.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 60 DAY)
          GROUP BY m.id, m.name, m.unit
          ORDER BY expiring_units DESC
          LIMIT 10'
    )->fetchAll();
    $stats['expiring_soon'] = count($expiringSoon);

    $recentDispensing = db()->query(
        'SELECT d.dispensing_no, d.created_at, p.prescription_no, pat.fullname AS patient_name, ph.fullname AS pharmacist_name
           FROM dispensings d
           JOIN prescriptions p ON p.id = d.prescription_id
           JOIN users pat ON pat.id = d.patient_id
           JOIN users ph ON ph.id = d.pharmacist_id
          ORDER BY d.created_at DESC
          LIMIT 10'
    )->fetchAll();
} catch (PDOException $e) {
    $lowStock = [];
    $recentDispensing = [];
    $byStatus = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pharmacist Reports - NHRE</title>
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
          <span class="auth-kicker">Pharmacy Reports</span>
          <h1>Pharmacist dashboard analytics</h1>
          <p>Review prescription activity, dispensing, and stock risk for the pharmacy workspace.</p>
        </div>
        <div class="dashboard-user-pill">
          <i class="fa-solid fa-chart-column"></i>
          <span><?= e($role) ?></span>
        </div>
      </div>

      <?php if ($errors): ?>
        <div class="alert alert-danger auth-alert mt-4" role="alert">
          <i class="fa-solid fa-circle-exclamation"></i>
          <div>
            <?php foreach ($errors as $message): ?><div><?= e($message) ?></div><?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <?php if ($success): ?>
        <div class="alert alert-success auth-alert mt-4" role="alert">
          <i class="fa-solid fa-circle-check"></i>
          <span><?= e($success) ?></span>
        </div>
      <?php endif; ?>

      <div class="row g-4 mt-1 dashboard-cards">
        <div class="col-6 col-xl-3">
          <article class="dashboard-card">
            <div class="dashboard-card-icon"><i class="fa-solid fa-prescription"></i></div>
            <h2><?= $stats['prescriptions_total'] ?></h2>
            <p>Total prescriptions</p>
          </article>
        </div>
        <div class="col-6 col-xl-3">
          <article class="dashboard-card">
            <div class="dashboard-card-icon"><i class="fa-solid fa-clock"></i></div>
            <h2><?= $stats['prescriptions_pending'] ?></h2>
            <p>Pending</p>
          </article>
        </div>
        <div class="col-6 col-xl-3">
          <article class="dashboard-card">
            <div class="dashboard-card-icon"><i class="fa-solid fa-user-check"></i></div>
            <h2><?= $stats['prescriptions_verified'] ?></h2>
            <p>Verified / ready</p>
          </article>
        </div>
        <div class="col-6 col-xl-3">
          <article class="dashboard-card">
            <div class="dashboard-card-icon"><i class="fa-solid fa-hand-holding-medical"></i></div>
            <h2><?= $stats['dispensed_today'] ?></h2>
            <p>Dispensed today</p>
          </article>
        </div>
      </div>

      <div class="row g-4 mt-1">
        <div class="col-lg-4">
          <article class="dashboard-card">
            <div class="dashboard-card-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
            <h2>Stock risk</h2>
            <p class="mb-3"><strong><?= $stats['low_stock'] ?></strong> medicines below reorder level</p>
            <p class="mb-3"><strong><?= $stats['expiring_soon'] ?></strong> medicines expiring within 60 days</p>
            <?php if ($lowStock): ?>
              <ul class="list-group list-group-flush">
                <?php foreach ($lowStock as $medicine): ?>
                  <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
                    <span><?= e($medicine['name']) ?></span>
                    <span class="badge rounded-pill bg-warning-subtle text-warning-emphasis"><?= (int)$medicine['available'] ?> / <?= (int)$medicine['reorder_level'] ?></span>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php else: ?>
              <p class="text-muted mb-0">No low-stock medicines detected.</p>
            <?php endif; ?>
          </article>
        </div>

        <div class="col-lg-8">
          <article class="dashboard-card">
            <div class="dashboard-card-icon"><i class="fa-solid fa-chart-simple"></i></div>
            <h2>Prescription status</h2>
            <?php if ($byStatus): ?>
              <div class="table-responsive mt-3">
                <table class="table table-hover align-middle">
                  <thead>
                    <tr><th>Status</th><th class="text-end">Count</th></tr>
                  </thead>
                  <tbody>
                    <?php foreach ($byStatus as $status => $count): ?>
                      <tr>
                        <td><?= e(ucwords(strtolower(str_replace('_', ' ', $status)))) ?></td>
                        <td class="text-end"><?= (int)$count ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php else: ?>
              <p class="text-muted mt-3 mb-0">No prescription activity has been recorded yet.</p>
            <?php endif; ?>
          </article>
        </div>
      </div>

      <div class="row g-4 mt-1">
        <div class="col-12">
          <article class="dashboard-card">
            <div class="dashboard-card-icon"><i class="fa-solid fa-clock-rotate-left"></i></div>
            <h2>Recent dispensing log</h2>
            <?php if ($recentDispensing): ?>
              <div class="table-responsive mt-3">
                <table class="table table-hover align-middle">
                  <thead>
                    <tr>
                      <th>Dispensing</th>
                      <th>Prescription</th>
                      <th>Patient</th>
                      <th>Pharmacist</th>
                      <th>Time</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($recentDispensing as $row): ?>
                      <tr>
                        <td><?= e($row['dispensing_no']) ?></td>
                        <td><?= e($row['prescription_no']) ?></td>
                        <td><?= e($row['patient_name']) ?></td>
                        <td><?= e($row['pharmacist_name']) ?></td>
                        <td><?= e(date('j M Y, g:i a', strtotime((string)$row['created_at']))) ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php else: ?>
              <p class="text-muted mt-3 mb-0">No dispensing events have been recorded yet.</p>
            <?php endif; ?>
          </article>
        </div>
      </div>
    </section>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/app.js?v=20260818-10"></script>
</body>
</html>
