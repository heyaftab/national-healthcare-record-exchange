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
        $totalHospitals = (int)$pdo->query("SELECT COUNT(*) FROM hospitals")->fetchColumn();
        $totalPatients = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'Patient'")->fetchColumn();
        $totalDoctors = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'Doctor'")->fetchColumn();
        $totalStaff = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role IN ('Pharmacist', 'Lab Technician', 'Hospital Admin')")->fetchColumn();
        $pendingAppointments = (int)$pdo->query("SELECT COUNT(*) FROM appointments WHERE status = 'Pending'")->fetchColumn();
        $pendingAccessRequests = (int)$pdo->query("SELECT COUNT(*) FROM access_permissions WHERE status = 'Pending'")->fetchColumn();
        $recentAppointments = $pdo->query(
            "SELECT a.appointment_date, a.appointment_time, a.status, patient.fullname AS patient_name, doctor.fullname AS doctor_name
         FROM appointments a
         JOIN users patient ON patient.id = a.patient_id
         JOIN users doctor ON doctor.id = a.doctor_id
         ORDER BY a.created_at DESC LIMIT 5"
        )->fetchAll();
        $recentAudit = $pdo->query(
            "SELECT a.created_at, COALESCE(u.fullname, 'System') AS actor, a.action, COALESCE(a.details, '—') AS details
             FROM audit_logs a
             LEFT JOIN users u ON u.id = a.user_id
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
        <?php if ($userRole === 'System Admin'): ?>
        <div class="dashboard-hero glass-card">
          <div>
            <span class="auth-kicker">Platform oversight</span>
            <h1>System Admin Dashboard</h1>
            <p>Welcome, <?= e($userName) ?>. Monitoring the NHRE platform, governance activity, and healthcare network health.</p>
          </div>
          <div class="dashboard-user-pill"><i class="fa-solid fa-shield-halved"></i><span>NHRE Platform</span></div>
        </div>
        <div class="row g-3 mb-4">
            <div class="col-md-4"><a href="admin_operations.php?view=access-overview" class="btn dashboard-action-btn w-100 py-3"><i class="fa-solid fa-shield-halved me-2"></i>Review access requests</a></div>
            <div class="col-md-4"><a href="admin_operations.php?view=user-management" class="btn dashboard-action-btn w-100 py-3"><i class="fa-solid fa-users-gear me-2"></i>User management</a></div>
            <div class="col-md-4"><a href="admin_operations.php?view=settings" class="btn dashboard-action-btn w-100 py-3"><i class="fa-solid fa-sliders me-2"></i>System settings</a></div>
        </div>
        <?php if (isset($dashboardError)): ?><div class="alert alert-danger" role="alert"><?= e($dashboardError) ?></div><?php endif; ?>
        <div class="system-admin-overview mb-4">
          <div class="row g-4 align-items-stretch">
            <div class="col-xl-8">
              <div class="row g-3 dashboard-cards summary-grid">
                <div class="col-md-6 col-xl-4"><article class="dashboard-card summary-card h-100"><div class="summary-card-head"><span class="summary-kicker">Hospitals</span><div class="summary-card-icon"><i class="fa-solid fa-building"></i></div></div><div class="summary-value"><?= $totalHospitals ?? 0 ?></div><div class="summary-label">Active hospitals</div><div class="summary-foot"><span class="summary-positive">Live</span></div></article></div>
                <div class="col-md-6 col-xl-4"><article class="dashboard-card summary-card h-100"><div class="summary-card-head"><span class="summary-kicker">Patients</span><div class="summary-card-icon"><i class="fa-solid fa-users"></i></div></div><div class="summary-value"><?= $totalPatients ?></div><div class="summary-label">Registered patients</div><div class="summary-foot"><span class="summary-positive">Updated</span></div></article></div>
                <div class="col-md-6 col-xl-4"><article class="dashboard-card summary-card h-100"><div class="summary-card-head"><span class="summary-kicker">Doctors</span><div class="summary-card-icon"><i class="fa-solid fa-user-doctor"></i></div></div><div class="summary-value"><?= $totalDoctors ?></div><div class="summary-label">Registered doctors</div><div class="summary-foot"><span class="summary-positive">On call</span></div></article></div>
                <div class="col-md-6 col-xl-4"><article class="dashboard-card summary-card h-100"><div class="summary-card-head"><span class="summary-kicker">Staff</span><div class="summary-card-icon"><i class="fa-solid fa-user-nurse"></i></div></div><div class="summary-value"><?= $totalStaff ?></div><div class="summary-label">Operational staff</div><div class="summary-foot"><span class="summary-positive">Ready</span></div></article></div>
                <div class="col-md-6 col-xl-4"><article class="dashboard-card summary-card h-100"><div class="summary-card-head"><span class="summary-kicker">Appointments</span><div class="summary-card-icon"><i class="fa-solid fa-clock"></i></div></div><div class="summary-value"><?= $pendingAppointments ?></div><div class="summary-label">Pending appointments</div><div class="summary-foot"><span class="summary-positive">Queued</span></div></article></div>
                <div class="col-md-6 col-xl-4"><article class="dashboard-card summary-card h-100"><div class="summary-card-head"><span class="summary-kicker">Access</span><div class="summary-card-icon"><i class="fa-solid fa-file-shield"></i></div></div><div class="summary-value"><?= $pendingAccessRequests ?? 0 ?></div><div class="summary-label">Pending access requests</div><div class="summary-foot"><span class="summary-positive">Review</span></div></article></div>
              </div>
            </div>
            <div class="col-xl-4">
              <aside class="dashboard-card system-admin-panel h-100">
                <div class="mini-panel-header">
                  <div>
                    <span class="mini-panel-kicker">Network health</span>
                    <h2>Governance snapshot</h2>
                  </div>
                  <span class="live-badge">Live</span>
                </div>
                <ul class="mini-status-list">
                  <li><span class="status-dot status-good"></span><div><strong><?= $totalHospitals ?? 0 ?></strong> active hospitals</div></li>
                  <li><span class="status-dot status-warn"></span><div><strong><?= $pendingAppointments ?></strong> appointments awaiting review</div></li>
                  <li><span class="status-dot status-alert"></span><div><strong><?= $pendingAccessRequests ?? 0 ?></strong> access requests pending approval</div></li>
                  <li><span class="status-dot status-good"></span><div><strong><?= $totalDoctors ?></strong> clinicians registered</div></li>
                </ul>
                <div class="mini-panel-footer">
                  <span>Platform status</span>
                  <strong>Stable</strong>
                </div>
              </aside>
            </div>
          </div>
        </div>
        <div class="row g-4">
          <div class="col-xl-7">
            <section class="dashboard-card h-100">
              <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h6 fw-bold m-0"><i class="fa-solid fa-calendar-check text-secondary me-2"></i>Recent platform appointments</h2>
                <a href="appointments.php" class="btn btn-outline-primary btn-sm">Manage appointments</a>
              </div>
              <div class="table-responsive"><table class="table table-hover align-middle m-0">
                  <thead class="table-light"><tr><th>Patient</th><th>Doctor</th><th>Date &amp; time</th><th>Status</th></tr></thead><tbody>
                  <?php foreach ($recentAppointments as $appointment): ?><tr>
                      <td><?= e($appointment['patient_name']) ?></td><td><?= e($appointment['doctor_name']) ?></td>
                      <td><?= e($appointment['appointment_date']) ?>, <?= e(substr((string)$appointment['appointment_time'], 0, 5)) ?></td>
                      <td><span class="badge text-bg-secondary"><?= e($appointment['status']) ?></span></td>
                  </tr><?php endforeach; ?>
                  <?php if ($recentAppointments === []): ?><tr><td colspan="4" class="text-center text-muted py-3">No platform appointments have been created yet.</td></tr><?php endif; ?>
                  </tbody></table></div>
            </section>
          </div>
          <div class="col-xl-5">
            <section class="dashboard-card h-100">
              <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h6 fw-bold m-0"><i class="fa-solid fa-list-check text-secondary me-2"></i>Recent governance activity</h2>
              </div>
              <div class="table-responsive"><table class="table table-hover align-middle m-0">
                  <thead class="table-light"><tr><th>Actor</th><th>Action</th></tr></thead><tbody>
                  <?php foreach ($recentAudit as $audit): ?><tr>
                      <td><div class="fw-semibold"><?= e($audit['actor']) ?></div><small class="text-muted"><?= e($audit['created_at']) ?></small></td>
                      <td><?= e($audit['action']) ?><div class="small text-muted"><?= e($audit['details']) ?></div></td>
                  </tr><?php endforeach; ?>
                  <?php if (empty($recentAudit)): ?><tr><td colspan="2" class="text-center text-muted py-3">No governance activity has been logged yet.</td></tr><?php endif; ?>
                  </tbody></table></div>
            </section>
          </div>
        </div>
        <?php else: ?>
        <div class="dashboard-hero glass-card">
          <div>
            <span class="auth-kicker">Administration workspace</span>
            <h1><?= e($userRole) ?> Dashboard</h1>
            <p>Welcome, <?= e($userName) ?>.<?= $hospital ? ' Managing ' . e($hospital['name']) . '.' : ' Monitor NHRE operations.' ?></p>
          </div>
          <div class="dashboard-user-pill"><i class="fa-solid fa-hospital"></i><span><?= e($hospital['name'] ?? 'NHRE') ?></span></div>
        </div>
        <div class="row g-3 mb-4">
            <div class="col-md-4"><a href="appointments.php?status=Pending" class="btn dashboard-action-btn w-100 py-3"><i class="fa-solid fa-calendar-check me-2"></i>Review pending appointments</a></div>
            <div class="col-md-4"><a href="admin_credentials.php" class="btn dashboard-action-btn w-100 py-3"><i class="fa-solid fa-users me-2"></i>Hospital account directory</a></div>
            <div class="col-md-4"><a href="medical_tests.php" class="btn dashboard-action-btn w-100 py-3"><i class="fa-solid fa-flask-vial me-2"></i>Laboratory services</a></div>
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
        <?php endif; ?>
      </section>
    </main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/app.js?v=20260811-8"></script>
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const currentUrl = new URL(window.location.href);
    const currentPath = currentUrl.pathname.replace(/\/+$/, '') || '/';
    const currentSearch = currentUrl.search || '';

    document.querySelectorAll('.dashboard-action-btn').forEach(function (button) {
      const href = button.getAttribute('href');
      if (!href) return;

      try {
        const linkUrl = new URL(href, window.location.origin);
        const linkPath = linkUrl.pathname.replace(/\/+$/, '') || '/';
        const exactMatch = linkPath === currentPath && linkUrl.search === currentSearch;

        button.classList.toggle('is-selected', exactMatch);
        if (exactMatch) {
          button.setAttribute('aria-current', 'page');
        } else {
          button.removeAttribute('aria-current');
        }
      } catch (error) {
        button.classList.remove('is-selected');
        button.removeAttribute('aria-current');
      }
    });
  });
</script>
</body>
</html>
