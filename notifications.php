<?php
require_once __DIR__ . '/auth/auth_check.php';
require_auth();

$fullname = $_SESSION['fullname'] ?? 'NHRE User';
$role = $_SESSION['role'] ?? 'User';
$errors = session_pull('errors', []);
$success = session_pull('success');
ensure_notification_links_column();

try {
    $stmt = db()->prepare('SELECT id, title, message, created_at, is_read, notification_type, target_path FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 20');
    $stmt->execute([(int)($_SESSION['user_id'] ?? 0)]);
    $notifications = $stmt->fetchAll();
} catch (PDOException $e) {
    $notifications = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Notifications - NHRE</title>
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
  <nav class="dashboard-nav">
    <div class="container d-flex align-items-center justify-content-between gap-3">
      <a class="navbar-brand d-flex align-items-center gap-2" href="dashboard.php">
        <img src="assets/images/nhre-logo.svg" alt="NHRE" class="nhre-logo-img">
      </a>
      <div class="d-flex gap-2">
        <a href="dashboard.php" class="btn btn-dashboard-logout ripple"><i class="fa-solid fa-house"></i> <span>Dashboard</span></a>
        <a href="vaccination.php" class="btn btn-dashboard-logout ripple"><i class="fa-solid fa-syringe"></i> <span>Vaccination</span></a>
      </div>
    </div>
  </nav>

  <main class="dashboard-main">
    <section class="container">
      <div class="dashboard-hero glass-card">
        <div>
          <span class="auth-kicker">Notification Center</span>
          <h1>Messages for <?= e($fullname) ?></h1>
          <p>Review approvals, donor verification updates, and medical reports.</p>
        </div>
        <div class="d-flex align-items-center gap-3">
          <form action="auth/notifications_api.php" method="POST">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <button type="submit" class="btn btn-dashboard-logout ripple">
              <i class="fa-solid fa-check-double"></i>
              <span>Mark all read</span>
            </button>
          </form>
          <div class="dashboard-user-pill">
            <i class="fa-solid fa-bell"></i>
            <span><?= e($role) ?></span>
          </div>
        </div>
      </div>

      <?php if ($errors): ?>
        <div class="alert alert-danger auth-alert" role="alert">
          <i class="fa-solid fa-circle-exclamation"></i>
          <div>
            <?php foreach ($errors as $message): ?>
              <div><?= e($message) ?></div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <?php if ($success): ?>
        <div class="alert alert-success auth-alert" role="alert">
          <i class="fa-solid fa-circle-check"></i>
          <span><?= e($success) ?></span>
        </div>
      <?php endif; ?>

      <div class="row g-4">
        <div class="col-12">
          <article class="dashboard-card">
            <div class="dashboard-card-icon"><i class="fa-solid fa-bell-concierge"></i></div>
            <h2>Your notifications</h2>
            <?php if ($notifications): ?>
              <div class="list-group mt-3">
                <?php foreach ($notifications as $notification): ?>
                  <a href="auth/notification_open.php?id=<?= (int)$notification['id'] ?>" class="list-group-item d-flex justify-content-between align-items-start notification-link">
                    <div>
                      <div class="fw-semibold"><?= e($notification['title']) ?></div>
                      <div class="text-muted small"><?= e($notification['message']) ?></div>
                      <div class="text-muted small mt-2">Type: <?= e($notification['notification_type']) ?> • <?= e($notification['created_at']) ?></div>
                    </div>
                    <span class="badge rounded-pill <?= (int)$notification['is_read'] ? 'bg-success-subtle text-success-emphasis' : 'bg-warning-subtle text-warning-emphasis' ?>">
                      <?= (int)$notification['is_read'] ? 'Read' : 'New' ?>
                    </span>
                  </a>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <p class="text-muted mt-3">No notifications yet. Your doctor reports and blood donation updates will appear here.</p>
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
