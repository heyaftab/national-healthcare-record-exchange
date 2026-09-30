<?php
require_once __DIR__ . '/auth/auth_check.php';
require_once __DIR__ . '/includes/pharmacy_functions.php';
require_role(['Pharmacist']);

ensure_pharmacy_tables();

$fullname = $_SESSION['fullname'] ?? 'NHRE User';
$role = $_SESSION['role'] ?? 'User';
$errors = session_pull('errors', []);
$success = session_pull('success');

$dispensings = [];
try {
    $stmt = db()->prepare(
        'SELECT d.id, d.dispensing_no, d.status, d.notes, d.created_at,
                p.prescription_no, p.status AS rx_status,
                pat.fullname AS patient_name, ph.fullname AS pharmacist_name,
                (SELECT COUNT(*) FROM dispensing_items di WHERE di.dispensing_id = d.id) AS item_count
           FROM dispensings d
           JOIN prescriptions p ON p.id = d.prescription_id
           JOIN users pat ON pat.id = d.patient_id
           JOIN users ph ON ph.id = d.pharmacist_id
          ORDER BY d.created_at DESC
          LIMIT 100'
    );
    $stmt->execute();
    $dispensings = $stmt->fetchAll();
} catch (PDOException $e) {
    $dispensings = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dispensing History - NHRE</title>
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
          <span class="auth-kicker">Dispensing Log</span>
          <h1>Dispensing History</h1>
          <p>Every medicine hand-over is recorded against a prescription and batch.</p>
        </div>
        <div class="dashboard-user-pill">
          <i class="fa-solid fa-clock-rotate-left"></i>
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

      <article class="dashboard-card mt-4">
        <div class="dashboard-card-icon"><i class="fa-solid fa-prescription-bottle"></i></div>
        <h2>Dispensing records</h2>
        <?php if ($dispensings): ?>
          <div class="table-responsive mt-3">
            <table class="table table-hover align-middle">
              <thead>
                <tr>
                  <th>Dispensing No.</th>
                  <th>Prescription</th>
                  <th>Patient</th>
                  <th>Pharmacist</th>
                  <th class="text-end">Items</th>
                  <th>Status</th>
                  <th>Date</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($dispensings as $dispensing): ?>
                  <tr>
                    <td><?= e($dispensing['dispensing_no']) ?></td>
                    <td>
                      <?= e($dispensing['prescription_no']) ?>
                      <?= pharmacy_status_badge((string)$dispensing['rx_status']) ?>
                    </td>
                    <td><?= e($dispensing['patient_name']) ?></td>
                    <td><?= e($dispensing['pharmacist_name']) ?></td>
                    <td class="text-end"><?= (int)$dispensing['item_count'] ?></td>
                    <td>
                      <span class="badge rounded-pill <?= $dispensing['status'] === 'COMPLETED' ? 'bg-success-subtle text-success-emphasis' : 'bg-primary-subtle text-primary-emphasis' ?>">
                        <?= e(ucfirst(strtolower((string)$dispensing['status']))) ?>
                      </span>
                    </td>
                    <td><?= e(date('j M Y, g:i a', strtotime((string)$dispensing['created_at']))) ?></td>
                    <td class="text-end">
                      <a href="prescription_view.php?id=<?= (int)$dispensing['prescription_id'] ?>" class="btn btn-sm btn-solid-nhre"><i class="fa-solid fa-eye"></i> View</a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <p class="text-muted mt-3">No medicines have been dispensed yet.</p>
        <?php endif; ?>
      </article>
    </section>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/app.js?v=20260811-8"></script>
</body>
</html>
