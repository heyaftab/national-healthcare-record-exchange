<?php
require_once __DIR__ . '/auth/auth_check.php';
require_once __DIR__ . '/includes/pharmacy_functions.php';
require_role(['Pharmacist']);
ensure_pharmacy_tables();

$userId = (int)($_SESSION['user_id'] ?? 0);
$search = trim((string)($_GET['search'] ?? ''));
$date = trim((string)($_GET['date'] ?? ''));

$logs = [];
try {
    $sql = 'SELECT id, action, entity_type, entity_id, details, ip_address, created_at
              FROM audit_logs
             WHERE user_id = ?';
    $params = [$userId];
    if ($search !== '') {
        $sql .= ' AND (action LIKE ? OR details LIKE ?)';
        $pattern = '%' . $search . '%';
        $params[] = $pattern;
        $params[] = $pattern;
    }
    if ($date !== '') {
        $sql .= ' AND DATE(created_at) = ?';
        $params[] = $date;
    }
    $sql .= ' ORDER BY created_at DESC LIMIT 200';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll();
} catch (PDOException $e) {
    $logs = [];
}

$role = $_SESSION['role'] ?? 'User';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Audit Log - NHRE</title>
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
          <span class="auth-kicker">Pharmacist Activity</span>
          <h1>Audit / Activity Log</h1>
          <p>Review pharmacist actions, stock changes, dispensing, and other recorded activity tied to your account.</p>
        </div>
        <div class="dashboard-user-pill">
          <i class="fa-solid fa-clipboard-list"></i>
          <span><?= e($role) ?></span>
        </div>
      </div>

      <article class="dashboard-card mt-4">
        <div class="dashboard-card-icon"><i class="fa-solid fa-filter"></i></div>
        <h2>Filters</h2>
        <form method="GET" class="row g-3 mt-1">
          <div class="col-md-5">
            <label class="form-label" for="search">Search</label>
            <input type="text" class="form-control" id="search" name="search" value="<?= e($search) ?>" placeholder="Action or detail text">
          </div>
          <div class="col-md-4">
            <label class="form-label" for="date">Date</label>
            <input type="date" class="form-control" id="date" name="date" value="<?= e($date) ?>">
          </div>
          <div class="col-md-3 d-flex align-items-end">
            <button type="submit" class="btn btn-solid-nhre w-100"><i class="fa-solid fa-magnifying-glass"></i> Apply</button>
          </div>
        </form>
      </article>

      <article class="dashboard-card mt-4">
        <div class="dashboard-card-icon"><i class="fa-solid fa-list"></i></div>
        <h2>Recent activity</h2>
        <?php if (!$logs): ?>
          <p class="text-muted mt-3 mb-0">No records were found for the selected filter.</p>
        <?php else: ?>
          <div class="table-responsive mt-3">
            <table class="table table-hover align-middle">
              <thead>
                <tr>
                  <th>Time</th>
                  <th>Action</th>
                  <th>Entity</th>
                  <th>Details</th>
                  <th>IP</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($logs as $log): ?>
                  <tr>
                    <td><?= e((string)$log['created_at']) ?></td>
                    <td><span class="badge rounded-pill bg-primary-subtle text-primary-emphasis"><?= e((string)$log['action']) ?></span></td>
                    <td><?= e((string)($log['entity_type'] ?? '—')) ?> #<?= (int)($log['entity_id'] ?? 0) ?></td>
                    <td><?= e((string)($log['details'] ?? '—')) ?></td>
                    <td><?= e((string)($log['ip_address'] ?? '—')) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </article>
    </section>
  </main>
</body>
</html>
