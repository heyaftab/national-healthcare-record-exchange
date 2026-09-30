<?php
declare(strict_types=1);

require_once __DIR__ . '/auth/auth_check.php';
require_role(['Hospital Admin', 'System Admin']);

$userRole = (string)$_SESSION['role'];
$userName = (string)$_SESSION['fullname'];
$userId = (int)$_SESSION['user_id'];
$hospital = null;
$hospitalId = null;

try {
    $pdo = db();
    if ($userRole === 'Hospital Admin') {
        $hospitalStmt = $pdo->prepare('SELECT h.id, h.name FROM users u JOIN hospitals h ON h.id = u.hospital_id WHERE u.id = ? LIMIT 1');
        $hospitalStmt->execute([$userId]);
        $hospital = $hospitalStmt->fetch();
        $hospitalId = $hospital ? (int)$hospital['id'] : null;
        if ($hospitalId === null) {
            throw new RuntimeException('No hospital assignment is available for this administrator.');
        }
        $count = $pdo->prepare('SELECT COUNT(*) FROM users WHERE role = ? AND hospital_id = ?');
        $count->execute(['Doctor', $hospitalId]); $totalDoctors = (int)$count->fetchColumn();
        $count->execute(['Pharmacist', $hospitalId]); $pharmacists = (int)$count->fetchColumn();
        $count->execute(['Lab Technician', $hospitalId]); $labTechnicians = (int)$count->fetchColumn();
        $totalStaff = $pharmacists + $labTechnicians;
        $count = $pdo->prepare("SELECT COUNT(DISTINCT a.patient_id) FROM appointments a JOIN users d ON d.id = a.doctor_id WHERE d.hospital_id = ?");
        $count->execute([$hospitalId]); $totalPatients = (int)$count->fetchColumn();
        $count = $pdo->prepare("SELECT COUNT(*) FROM appointments a JOIN users d ON d.id = a.doctor_id WHERE a.status = 'Pending' AND d.hospital_id = ?");
        $count->execute([$hospitalId]); $pendingAppointments = (int)$count->fetchColumn();
        $recentStmt = $pdo->prepare(
            "SELECT a.appointment_date, a.appointment_time, a.status, patient.fullname AS patient_name, doctor.fullname AS doctor_name
             FROM appointments a JOIN users patient ON patient.id = a.patient_id JOIN users doctor ON doctor.id = a.doctor_id
             WHERE doctor.hospital_id = ? ORDER BY a.created_at DESC LIMIT 5"
        );
        $recentStmt->execute([$hospitalId]); $recentAppointments = $recentStmt->fetchAll();
    } else {
        $totalPatients = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'Patient'")->fetchColumn();
        $totalDoctors = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'Doctor'")->fetchColumn();
        $totalStaff = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role IN ('Pharmacist', 'Lab Technician')")->fetchColumn();
        $pendingAppointments = (int)$pdo->query("SELECT COUNT(*) FROM appointments WHERE status = 'Pending'")->fetchColumn();
        $recentAppointments = $pdo->query(
            "SELECT a.appointment_date, a.appointment_time, a.status, patient.fullname AS patient_name, doctor.fullname AS doctor_name
         FROM appointments a
         JOIN users patient ON patient.id = a.patient_id
         JOIN users doctor ON doctor.id = a.doctor_id
         ORDER BY a.created_at DESC LIMIT 5"
        )->fetchAll();
    }
} catch (PDOException|RuntimeException $e) {
    http_response_code(500);
    $dashboardError = 'Dashboard data is temporarily unavailable. Please try again later.';
    $totalPatients = $totalDoctors = $totalStaff = $pendingAppointments = 0;
    $recentAppointments = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration Dashboard — NHRE</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css?v=20260818-18">
<script>
  (function () {
    try {
      var theme = localStorage.getItem('nhre-theme');
      if (theme !== 'light' && theme !== 'dark') theme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
      document.documentElement.dataset.theme = theme;
      document.documentElement.style.colorScheme = theme;
    } catch (error) {}
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
            <span class="auth-kicker">Administration workspace</span>
            <h1><?= e($userRole) ?> Dashboard</h1>
            <p>Welcome, <?= e($userName) ?>.<?= $hospital ? ' Managing ' . e($hospital['name']) . '.' : ' Monitor NHRE operations.' ?></p>
          </div>
          <div class="dashboard-user-pill"><i class="fa-solid fa-hospital"></i><span><?= e($hospital['name'] ?? 'NHRE') ?></span></div>
        </div>
        <div class="row g-3 mb-4">
            <div class="col-md-4"><a href="appointments.php?status=Pending" class="btn btn-solid-nhre w-100 py-3"><i class="fa-solid fa-calendar-check me-2"></i>Review pending appointments</a></div>
            <div class="col-md-4"><a href="admin_credentials.php" class="btn btn-outline-primary w-100 py-3"><i class="fa-solid fa-users me-2"></i>Hospital account directory</a></div>
            <div class="col-md-4"><a href="medical_tests.php" class="btn btn-outline-primary w-100 py-3"><i class="fa-solid fa-flask-vial me-2"></i>Laboratory services</a></div>
        </div>
        <?php if (isset($dashboardError)): ?><div class="alert alert-danger" role="alert"><?= e($dashboardError) ?></div><?php endif; ?>
        <div class="row g-4 mb-4 dashboard-cards">
            <div class="col-md-6 col-xl-3"><article class="dashboard-card h-100"><div class="dashboard-card-icon"><i class="fa-solid fa-users"></i></div><h2><?= $totalPatients ?></h2><p>Registered patients</p></article></div>
            <div class="col-md-6 col-xl-3"><article class="dashboard-card h-100"><div class="dashboard-card-icon"><i class="fa-solid fa-user-doctor"></i></div><h2><?= $totalDoctors ?></h2><p>Registered doctors</p></article></div>
            <div class="col-md-6 col-xl-3"><article class="dashboard-card h-100"><div class="dashboard-card-icon"><i class="fa-solid fa-user-nurse"></i></div><h2><?= $totalStaff ?></h2><p>Pharmacy &amp; lab staff</p></article></div>
            <div class="col-md-6 col-xl-3"><article class="dashboard-card h-100"><div class="dashboard-card-icon"><i class="fa-solid fa-clock"></i></div><h2><?= $pendingAppointments ?></h2><p>Pending appointments</p></article></div>
        </div>
        <section class="dashboard-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h6 fw-bold m-0"><i class="fa-solid fa-calendar-check text-secondary me-2"></i>Recent appointments</h2>
                <a href="appointments.php" class="btn btn-outline-primary btn-sm">Manage appointments</a>
            </div>
            <div class="table-responsive"><table class="table table-hover align-middle m-0">
                <thead class="table-light"><tr><th>Patient</th><th>Doctor</th><th>Date &amp; time</th><th>Status</th></tr></thead><tbody>
                <?php foreach ($recentAppointments as $appointment): ?><tr>
                    <td><?= e($appointment['patient_name']) ?></td><td><?= e($appointment['doctor_name']) ?></td>
                    <td><?= e($appointment['appointment_date']) ?>, <?= e(substr((string)$appointment['appointment_time'], 0, 5)) ?></td>
                    <td><span class="badge text-bg-secondary"><?= e($appointment['status']) ?></span></td>
                </tr><?php endforeach; ?>
                <?php if ($recentAppointments === []): ?><tr><td colspan="4" class="text-center text-muted py-3">No appointments have been created yet.</td></tr><?php endif; ?>
                </tbody></table></div>
        </section>
      </section>
    </main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/app.js?v=20260811-8"></script>
</body>
</html>
