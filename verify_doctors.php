<?php
declare(strict_types=1);

require 'auth/auth_check.php';
require_auth();

if (($_SESSION['role'] ?? '') !== 'Hospital Admin') {
    redirect('dashboard.php');
}

ensure_doctor_profile_columns();
ensure_doctor_catalog_tables();
$pdo = db();
$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'Doctor'");
$resultDoctors = 'doctors=' . $stmt->fetchColumn() . PHP_EOL;
$stmt = $pdo->query('SELECT COUNT(*) FROM hospitals');
$resultHospitals = 'hospitals=' . $stmt->fetchColumn() . PHP_EOL;
$stmt = $pdo->query('SELECT COUNT(*) FROM specializations');
$resultSpecializations = 'specializations=' . $stmt->fetchColumn() . PHP_EOL;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Verify Doctors - NHRE</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
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
    <section class="container" style="max-width: 680px;">
      <div class="glass-card p-4 mt-4">
        <h2 class="mb-4">Doctor Catalog Verification</h2>
        <pre class="mb-0"><?= e($resultDoctors . $resultHospitals . $resultSpecializations) ?></pre>
        <a href="dashboard.php" class="btn btn-solid-nhre mt-3">Back to Dashboard</a>
      </div>
    </section>
  </main>
  <script src="assets/js/app.js?v=20260811-8"></script>
</body>
</html>
