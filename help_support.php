<?php require_once __DIR__ . '/auth/auth_check.php'; require_auth(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Help & Support - NHRE</title>
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
          <span class="auth-kicker">Help & Support</span>
          <h1>Using NHRE</h1>
          <p>Guidance and support resources for your account.</p>
        </div>
      </div>

      <div class="row g-4">
        <div class="col-md-6 col-lg-3">
          <article class="dashboard-card">
            <div class="dashboard-card-icon"><i class="fa-solid fa-circle-question"></i></div>
            <h2>Frequently asked questions</h2>
            <ul class="mb-0 ps-3">
              <li>Use <a href="profile.php">Profile</a> to keep your contact information current.</li>
              <li>Visit <a href="notifications.php">Notifications</a> for account and consent updates.</li>
              <li>Manage who can read your records from <a href="data_access.php">Data Access</a>.</li>
            </ul>
          </article>
        </div>
        <div class="col-md-6 col-lg-3">
          <article class="dashboard-card">
            <div class="dashboard-card-icon"><i class="fa-solid fa-headset"></i></div>
            <h2>Contact support</h2>
            <p>Support contact details are not configured in this prototype. Please contact your NHRE administrator for account or access issues.</p>
          </article>
        </div>
        <div class="col-md-6 col-lg-3">
          <article class="dashboard-card">
            <div class="dashboard-card-icon"><i class="fa-solid fa-book"></i></div>
            <h2>System guide</h2>
            <p>Book appointments, track lab tests, and control record access from the sidebar. Planned workspaces are marked <em>Planned</em> and are not yet available.</p>
          </article>
        </div>
        <div class="col-md-6 col-lg-3">
          <article class="dashboard-card">
            <div class="dashboard-card-icon"><i class="fa-solid fa-shield-halved"></i></div>
            <h2>Privacy information</h2>
            <p>Do not use this demonstration system for real patient data without appropriate privacy, security, and regulatory controls. Record access is logged and visible to patients.</p>
          </article>
        </div>
      </div>
    </section>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/app.js?v=20260811-8"></script>
</body>
</html>
