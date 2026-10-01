<?php
require_once __DIR__ . '/auth/auth_check.php';

require_auth();
ensure_clinical_tables();

$role = (string)$_SESSION['role'];
$viewer = (int)$_SESSION['user_id'];
$patientId = $role === 'Patient' ? $viewer : (int)($_GET['patient'] ?? 0);
$access = null;

if ($role !== 'Patient') {
    $access = $patientId > 0 ? active_access($patientId, $viewer) : null;
    $allowedTypes = ['Medical History', 'Allergies', 'Prescriptions', 'Medical Documents'];
    $hasAllowedType = false;
    foreach ($allowedTypes as $type) {
        if (access_allows_record_type($access, $type)) {
            $hasAllowedType = true;
            break;
        }
    }
    if (!$hasAllowedType) {
        $_SESSION['errors'] = ['Active authorization for this record type is required.'];
        redirect('patient_search.php');
    }
}

$canSee = static fn (string $type): bool => $role === 'Patient' || access_allows_record_type($access, $type);

$stmt = db()->prepare("SELECT id, fullname, account_number, date_of_birth, gender, blood_group, emergency_contact FROM users WHERE id = ? AND role = 'Patient'");
$stmt->execute([$patientId]);
$patient = $stmt->fetch();
if (!$patient) {
    $_SESSION['errors'] = ['Patient record not found.'];
    redirect('dashboard.php');
}

$allergies = $prescriptions = $documents = [];
if ($canSee('Allergies')) {
    $stmt = db()->prepare('SELECT * FROM allergies WHERE patient_id = ? AND is_active = 1 ORDER BY severity DESC, name');
    $stmt->execute([$patientId]);
    $allergies = $stmt->fetchAll();
}

if ($canSee('Prescriptions')) {
    try {
        require_once __DIR__ . '/includes/pharmacy_functions.php';
        ensure_pharmacy_tables();
        $stmt = db()->prepare('SELECT id, prescription_no, status, created_at FROM prescriptions WHERE patient_id = ? ORDER BY created_at DESC LIMIT 10');
        $stmt->execute([$patientId]);
        $prescriptions = $stmt->fetchAll();
    } catch (PDOException $e) {
        $prescriptions = [];
    }
}

if ($canSee('Medical Documents')) {
    $stmt = db()->prepare('SELECT id, category, original_name, verification_status, created_at FROM medical_documents WHERE patient_id = ? ORDER BY created_at DESC LIMIT 10');
    $stmt->execute([$patientId]);
    $documents = $stmt->fetchAll();
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Medical Records - NHRE</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body class="dashboard-body">
<?php require __DIR__ . '/includes/sidebar.php'; require __DIR__ . '/includes/topnav.php'; ?>
<main class="dashboard-main">
  <section class="container">
    <div class="dashboard-hero glass-card">
      <div>
        <span class="auth-kicker">Patient health record</span>
        <h1><?= e($patient['fullname']) ?></h1>
        <p>NHRE Account Number: <strong><?= e($patient['account_number'] ?: 'Pending assignment') ?></strong></p>
      </div>
    </div>

    <div class="row g-4 mt-1">
      <div class="col-lg-4">
        <article class="dashboard-card">
          <h2 class="fs-5">Identity<?= $canSee('Medical History') ? ' & emergency' : '' ?></h2>
          <p><strong>Blood group:</strong> <?= e($patient['blood_group'] ?: '-') ?></p>
          <p><strong>Date of birth:</strong> <?= e($patient['date_of_birth'] ?: '-') ?></p>
          <?php if ($canSee('Medical History')): ?>
            <p class="mb-0"><strong>Emergency contact:</strong> <?= e($patient['emergency_contact'] ?: '-') ?></p>
          <?php endif; ?>
        </article>
      </div>

      <?php if ($canSee('Allergies')): ?>
        <div class="col-lg-8">
          <article class="dashboard-card">
            <div class="d-flex justify-content-between">
              <h2 class="fs-5">Active allergies</h2>
              <?php if ($role === 'Patient'): ?><a href="allergies.php">Manage</a><?php endif; ?>
            </div>
            <?php if (!$allergies): ?>
              <p class="text-muted mb-0">No active allergies recorded.</p>
            <?php else: ?>
              <ul class="mb-0">
                <?php foreach ($allergies as $item): ?>
                  <li><strong><?= e($item['name']) ?></strong> - <?= e($item['severity']) ?><?= $item['reaction_text'] ? ' (' . e($item['reaction_text']) . ')' : '' ?></li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </article>
        </div>
      <?php endif; ?>

      <?php if ($canSee('Prescriptions')): ?>
        <div class="col-md-6">
          <article class="dashboard-card">
            <h2 class="fs-5">Prescriptions</h2>
            <?php foreach ($prescriptions as $item): ?>
              <p><a href="prescription_view.php?id=<?= (int)$item['id'] ?>"><?= e($item['prescription_no']) ?></a> <span class="text-muted"><?= e($item['status']) ?></span></p>
            <?php endforeach; ?>
            <?php if (!$prescriptions): ?><p class="text-muted mb-0">No prescriptions recorded.</p><?php endif; ?>
          </article>
        </div>
      <?php endif; ?>

      <?php if ($canSee('Medical Documents')): ?>
        <div class="col-md-6">
          <article class="dashboard-card">
            <h2 class="fs-5">Medical documents</h2>
            <?php foreach ($documents as $item): ?>
              <p><a href="auth/document_download.php?id=<?= (int)$item['id'] ?>"><?= e($item['original_name']) ?></a> <span class="text-muted"><?= e($item['verification_status']) ?></span></p>
            <?php endforeach; ?>
            <?php if (!$documents): ?><p class="text-muted mb-0">No documents uploaded.</p><?php endif; ?>
          </article>
        </div>
      <?php endif; ?>
    </div>
  </section>
</main>
<script src="assets/js/app.js?v=20260818-10"></script>
</body>
</html>
