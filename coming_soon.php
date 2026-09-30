<?php
require_once __DIR__ . '/auth/auth_check.php';
require_auth();

$feature = (string)($_GET['feature'] ?? '');
$features = [
    'medical-records' => ['Medical Records', ['Patient', 'Doctor', 'Hospital Admin', 'System Admin']],
    'prescriptions' => ['Prescriptions', ['Patient', 'Doctor', 'Pharmacist', 'Hospital Admin', 'System Admin']],
    'allergies' => ['Allergies', ['Patient']],
    'medical-documents' => ['Medical Documents', ['Patient', 'Doctor']],
    'data-access' => ['Data Access', ['Patient']],
    'patients' => ['My Patients', ['Doctor', 'Hospital Admin']],
    'patient-search' => ['Patient Search', ['Doctor', 'Lab Technician', 'Pharmacist']],
    'access-requests' => ['Access Requests', ['Doctor', 'Hospital Admin']],
    'test-history' => ['Test History', ['Lab Technician']],
    'laboratory-reports' => ['Laboratory Reports', ['Lab Technician', 'System Admin']],
    'inventory' => ['Medicine Inventory', ['Pharmacist']],
    'stock-management' => ['Stock Management', ['Pharmacist']],
    'dispensing-history' => ['Dispensing History', ['Pharmacist']],
    'hospital-profile' => ['Hospital Profile', ['Hospital Admin']],
    'doctors' => ['Doctors', ['Hospital Admin']],
    'hospital-staff' => ['Hospital Staff', ['Hospital Admin']],
    'departments' => ['Departments', ['Hospital Admin']],
    'user-management' => ['User Management', ['System Admin']],
    'organizations' => ['Healthcare Organizations', ['System Admin']],
    'access-overview' => ['Access Permissions', ['System Admin']],
    'system-statistics' => ['System Statistics', ['System Admin']],
    'reports' => ['Reports & Analytics', ['Hospital Admin', 'System Admin']],
    'audit-logs' => ['Audit Logs', ['Hospital Admin', 'System Admin']],
    'settings' => ['Settings', valid_roles()],
];
if (!isset($features[$feature]) || !in_array((string)($_SESSION['role'] ?? ''), $features[$feature][1], true)) {
    $_SESSION['errors'] = ['You do not have permission to access that workspace.'];
    redirect('dashboard.php');
}
$title = $features[$feature][0];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($title) ?> - NHRE</title>
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
          <span class="auth-kicker">NHRE workspace</span>
          <h1><?= e($title) ?></h1>
          <p>This secure workspace is planned but is not available in the current prototype.</p>
        </div>
      </div>
      <article class="dashboard-card">
        <div class="dashboard-card-icon"><i class="fa-solid fa-clock"></i></div>
        <h2>Planned feature</h2>
        <p>It will be added after the required data model, patient-consent controls, and server-side authorization rules are implemented.</p>
        <a class="btn btn-solid-nhre" href="dashboard.php">Return to dashboard</a>
      </article>
    </section>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/app.js?v=20260811-8"></script>
</body>
</html>
