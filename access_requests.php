<?php
require_once __DIR__ . '/auth/auth_check.php';
require_role(['Doctor']);
ensure_access_tables_exists();

$fullname = $_SESSION['fullname'] ?? 'NHRE User';
$errors = session_pull('errors', []);
$success = session_pull('success');
$providerId = (int)($_SESSION['user_id'] ?? 0);

try {
    $stmt = db()->prepare(
        'SELECT ap.id, ap.patient_id, ap.record_types, ap.granted_at, ap.expires_at, ap.status,
                u.fullname AS patient_name
         FROM access_permissions ap
         JOIN users u ON u.id = ap.patient_id
         WHERE ap.provider_id = ?
         ORDER BY ap.created_at DESC'
    );
    $stmt->execute([$providerId]);
    $permissions = $stmt->fetchAll();
} catch (PDOException $e) {
    $permissions = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Access Requests - NHRE</title>
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
          <span class="auth-kicker">Consent</span>
          <h1>Access Requests</h1>
          <p>Track the patient access you have requested and been granted across the exchange.</p>
        </div>
        <div class="dashboard-user-pill">
          <i class="fa-solid fa-shield-halved"></i>
          <span><?= e($fullname) ?></span>
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
        <div class="col-12">
          <article class="dashboard-card">
            <div class="dashboard-card-icon"><i class="fa-solid fa-key"></i></div>
            <h2>My Access</h2>
            <?php if ($permissions): ?>
              <div class="table-responsive mt-3">
                <table class="table table-hover align-middle">
                  <thead>
                    <tr>
                      <th>Patient</th>
                      <th>Permissions</th>
                      <th>Expires</th>
                      <th>Status</th>
                      <th></th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($permissions as $permission): ?>
                      <?php
                      $isActive = $permission['status'] === 'Active'
                        && !empty($permission['expires_at'])
                        && strtotime($permission['expires_at']) > time();
                      ?>
                      <tr>
                        <td>
                          <?= e($permission['patient_name']) ?>
                          <small class="d-block text-muted">Patient ID #<?= (int)$permission['patient_id'] ?></small>
                        </td>
                        <td>
                          <?php
                          $types = array_filter(array_map('trim', explode(',', (string)$permission['record_types'])));
                          if ($types):
                            foreach ($types as $type): ?>
                              <span class="badge bg-light text-dark"><?= e($type) ?></span>
                            <?php endforeach;
                          else:
                            echo '<span class="text-muted">—</span>';
                          endif;
                          ?>
                        </td>
                        <td>
                          <?= !empty($permission['expires_at']) ? e(date('j M Y', strtotime($permission['expires_at']))) : '—' ?>
                        </td>
                        <td>
                          <?php if ($isActive): ?>
                            <span class="badge bg-success">Active</span>
                          <?php elseif ($permission['status'] === 'Requested'): ?>
                            <span class="badge bg-warning text-dark">Pending</span>
                          <?php elseif ($permission['status'] === 'Approved'): ?>
                            <span class="badge bg-info text-dark">Approved</span>
                          <?php elseif ($permission['status'] === 'Rejected'): ?>
                            <span class="badge bg-danger">Rejected</span>
                          <?php else: ?>
                            <span class="badge bg-secondary">Revoked / Expired</span>
                          <?php endif; ?>
                        </td>
                        <td>
                          <?php if ($isActive): ?>
                            <a class="btn btn-sm btn-solid-nhre" href="authorized_records.php?patient=<?= (int)$permission['patient_id'] ?>">View Authorized Records</a>
                          <?php endif; ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php else: ?>
              <p class="text-muted mt-3">You have not requested access to any patient records yet. Use <a href="patient_search.php">Patient Search</a> to find a patient and request access.</p>
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
