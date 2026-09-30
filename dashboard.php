<?php
require_once __DIR__ . '/auth/auth_check.php';
require_auth();
ensure_appointments_table_exists();

$fullname = $_SESSION['fullname'] ?? 'NHRE User';
$email = $_SESSION['email'] ?? '';
$role = $_SESSION['role'] ?? 'User';
$errors = session_pull('errors', []);
$success = session_pull('success');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard - NHRE</title>
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

  document.addEventListener('DOMContentLoaded', function () {
    if (location.hash === '#doctor-profile') {
      var profileSection = document.getElementById('doctor-profile');
      if (profileSection) {
        setTimeout(function () {
          profileSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }, 80);
      }
    }
  });
</script>
</head>
<body class="dashboard-body">
  <?php require __DIR__ . '/includes/sidebar.php'; ?>
  <?php require __DIR__ . '/includes/topnav.php'; ?>


  <main class="dashboard-main">
    <section class="container">
      <div class="dashboard-hero glass-card">
        <div>
          <span class="auth-kicker">Authenticated Dashboard</span>
          <h1>Welcome,<br><?= e($fullname) ?></h1>
          <p>Role: <strong><?= e($role) ?></strong></p>
        </div>
        <div class="dashboard-user-pill">
          <i class="fa-solid fa-circle-user"></i>
          <span><?= e($email) ?></span>
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

      <?php if (false): ?><div class="row g-4 mt-1 dashboard-cards">
        <div class="col-md-6 col-xl-3">
          <article class="dashboard-card">
            <div class="dashboard-card-icon"><i class="fa-solid fa-user"></i></div>
            <h2>Profile</h2>
            <p>View and manage your verified NHRE identity details.</p>
            <a href="profile.php" class="dashboard-card-link">Open Profile</a>
          </article>
        </div>

        <div class="col-md-6 col-xl-3">
          <article class="dashboard-card">
            <div class="dashboard-card-icon"><i class="fa-solid fa-syringe"></i></div>
            <h2>Vaccination</h2>
            <p>Track vaccine schedules, required doses, and doctor reports in one place.</p>
            <a href="vaccination.php" class="dashboard-card-link">Open Vaccination</a>
          </article>
        </div>

        <div class="col-md-6 col-xl-3">
          <article class="dashboard-card">
            <div class="dashboard-card-icon"><i class="fa-solid fa-bell"></i></div>
            <h2>Notifications</h2>
            <p>Review medical approvals, blood donation updates, and profile alerts.</p>
            <a href="notifications.php" class="dashboard-card-link">Open Notifications</a>
          </article>
        </div>

        <div class="col-md-6 col-xl-3">
          <article class="dashboard-card">
            <div class="dashboard-card-icon"><i class="fa-solid fa-pills"></i></div>
            <h2>Pharmacy</h2>
            <p>View prescriptions, manage medicine inventory, and track dispensing.</p>
            <a href="pharmacy.php" class="dashboard-card-link">Open Pharmacy</a>
          </article>
        </div>

        <div class="col-md-6 col-xl-3">
          <article class="dashboard-card">
            <div class="dashboard-card-icon"><i class="fa-solid fa-calendar-check"></i></div>
            <h2>Appointments</h2>
            <p>Book, approve, and review appointments directly from your dashboard.</p>
            <a href="appointments.php" class="dashboard-card-link">Open Appointments</a>
          </article>
        </div>

        <div class="col-md-6 col-xl-3">
          <article class="dashboard-card">
            <div class="dashboard-card-icon"><i class="fa-solid fa-droplet"></i></div>
            <h2>Blood Donation</h2>
            <p>Register as a donor, request blood, and view available donors by district.</p>
            <a href="blood_donation.php" class="dashboard-card-link">Open Blood Donation</a>
          </article>
        </div>

        <div class="col-md-6 col-xl-3">
          <article class="dashboard-card">
            <div class="dashboard-card-icon"><i class="fa-solid fa-flask-vial"></i></div>
            <h2>Tests</h2>
            <p>Browse medical tests, book diagnostics, and track your lab bookings in one place.</p>
            <a href="medical_tests.php" class="dashboard-card-link">Open Marketplace</a>
          </article>
        </div>

        <div class="col-md-6 col-xl-3">
          <article class="dashboard-card dashboard-card-danger">
            <div class="dashboard-card-icon"><i class="fa-solid fa-right-from-bracket"></i></div>
            <h2>Logout</h2>
            <p>End this protected session and return to the login page.</p>
            <a href="logout.php" class="dashboard-card-link">Logout Securely</a>
          </article>
        </div>
      </div><?php endif; ?>

      <section class="mt-4">
        <div class="d-flex align-items-end justify-content-between gap-3 mb-3">
          <div>
            <span class="auth-kicker">Your Workspace</span>
            <h2 class="mb-0">All sidebar sections</h2>
          </div>
          <span class="text-muted small">Quick access for <?= e($role) ?></span>
        </div>
        <div class="row g-4 dashboard-cards">
          <?php foreach ($sidebarLinks as $workspaceLink): ?>
            <?php
            [$workspaceHref, $workspaceIcon, $workspaceLabel] = $workspaceLink;
            $workspacePlanned = (bool)($workspaceLink[3] ?? false);
            if ($workspaceLabel === 'Dashboard') {
                continue;
            }
            ?>
            <div class="col-md-6 col-xl-3">
              <article class="dashboard-card">
                <div class="dashboard-card-icon"><i class="fa-solid <?= e($workspaceIcon) ?>"></i></div>
                <h2><?= e($workspaceLabel) ?></h2>
                <p><?= $workspacePlanned ? 'This workspace is available in the current role navigation.' : 'Open this workspace from your dashboard.' ?></p>
                <a href="<?= e($workspaceHref) ?>" class="dashboard-card-link">Open <?= e($workspaceLabel) ?></a>
              </article>
            </div>
          <?php endforeach; ?>
        </div>
      </section>

      <?php if (false): ?><section class="mt-4">
        <div class="dashboard-hero glass-card">
          <div>
            <span class="auth-kicker">Appointment Workspace</span>
            <h1>Open the dedicated appointment page</h1>
            <p>Use the full appointment workspace to search doctors, book visits, and manage requests.</p>
          </div>
          <div class="dashboard-user-pill">
            <i class="fa-solid fa-calendar-check"></i>
            <span><?= e($role) ?></span>
          </div>
        </div>

        <div class="row g-4 mt-1">
          <div class="col-12">
            <article class="dashboard-card">
              <div class="dashboard-card-icon"><i class="fa-solid fa-arrow-up-right-from-square"></i></div>
              <h2>Continue to appointments</h2>
              <p>Booking and management actions now happen on the dedicated appointment page.</p>
              <a href="appointments.php" class="btn btn-solid-nhre mt-2">Open Appointment Page</a>
            </article>
          </div>
        </div>
      </section><?php endif; ?>
    </section>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/app.js?v=20260818-10"></script>
</body>
</html>
