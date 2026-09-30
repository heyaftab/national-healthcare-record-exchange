<?php
require_once __DIR__ . '/auth/auth_check.php';
require_role(['Doctor', 'Lab Technician', 'Pharmacist']);
ensure_access_tables_exists();
ensure_clinical_tables();
require_once __DIR__ . '/includes/pharmacy_functions.php';
ensure_pharmacy_tables();

$providerId = (int)($_SESSION['user_id'] ?? 0);
$patientId = (int)($_GET['patient'] ?? 0);

$access = $patientId > 0 ? active_access($patientId, $providerId) : null;
if ($patientId <= 0 || $access === null) {
    $_SESSION['errors'] = ['You do not have an active authorization to view this patient\u2019s records.'];
    redirect('patient_search.php');
}

try {
    $stmt = db()->prepare(
        'SELECT id, fullname, nid, email, phone, date_of_birth, gender, blood_group, account_number
         FROM users WHERE id = ?'
    );
    $stmt->execute([$patientId]);
    $patient = $stmt->fetch();
} catch (PDOException $e) {
    $patient = false;
}

if (!$patient) {
    $_SESSION['errors'] = ['Patient could not be found.'];
    redirect('patient_search.php');
}

$recordTypes = array_values(array_filter(array_map('trim', explode(',', $access['record_types']))));
$canSee = static fn(string $type): bool => in_array($type, $recordTypes, true);
$history = $allergies = $prescriptions = $documents = $labs = [];
$pharmacistMedications = $pharmacistDispensing = [];
try {
    if ($canSee('Medical History')) { $s=db()->prepare('SELECT a.appointment_id,a.appointment_date,a.appointment_time,a.reason,a.status,a.doctor_notes,d.fullname doctor_name FROM appointments a JOIN users d ON d.id=a.doctor_id WHERE a.patient_id=? AND a.status="Completed" ORDER BY a.appointment_date DESC');$s->execute([$patientId]);$history=$s->fetchAll(); }
    if ($canSee('Allergies')) { $s=db()->prepare('SELECT name,allergy_type,reaction_text,severity FROM allergies WHERE patient_id=? AND is_active=1 ORDER BY severity DESC');$s->execute([$patientId]);$allergies=$s->fetchAll(); }
    if ($canSee('Prescriptions')) { $s=db()->prepare('SELECT p.id,p.prescription_no,p.created_at,p.status,d.fullname doctor_name FROM prescriptions p JOIN users d ON d.id=p.doctor_id WHERE p.patient_id=? ORDER BY p.created_at DESC');$s->execute([$patientId]);$prescriptions=$s->fetchAll(); }
    if ($canSee('Medical Documents')) { $s=db()->prepare('SELECT id,original_name,category,verification_status,created_at FROM medical_documents WHERE patient_id=? ORDER BY created_at DESC');$s->execute([$patientId]);$documents=$s->fetchAll(); }
    if ($canSee('Lab Reports')) { $s=db()->prepare('SELECT b.id,b.booking_date,b.status,t.name test_name,t.place FROM medical_test_bookings b JOIN medical_tests t ON t.id=b.test_id WHERE b.user_id=? ORDER BY b.booking_date DESC');$s->execute([$patientId]);$labs=$s->fetchAll(); }
    if ((string)($_SESSION['role'] ?? '') === 'Pharmacist') {
        $s = db()->prepare(
            'SELECT p.id AS prescription_id, p.prescription_no, p.status, pi.medicine_id, m.name AS medicine_name, pi.quantity_prescribed, pi.dosage, pi.frequency
               FROM prescriptions p
               JOIN prescription_items pi ON pi.prescription_id = p.id
               JOIN medicines m ON m.id = pi.medicine_id
              WHERE p.patient_id = ?
              ORDER BY p.created_at DESC, pi.id ASC'
        );
        $s->execute([$patientId]);
        $pharmacistMedications = $s->fetchAll();

        $s = db()->prepare(
            'SELECT d.id, d.dispensing_no, d.created_at, d.status, di.medicine_id, m.name AS medicine_name, di.quantity_given
               FROM dispensings d
               JOIN dispensing_items di ON di.dispensing_id = d.id
               JOIN medicines m ON m.id = di.medicine_id
              WHERE d.patient_id = ?
              ORDER BY d.created_at DESC, di.id ASC'
        );
        $s->execute([$patientId]);
        $pharmacistDispensing = $s->fetchAll();
    }
} catch (PDOException $e) {}
$loggedAccess = $_SESSION['logged_access'][$patientId] ?? false;

if (!$loggedAccess) {
    foreach ($recordTypes as $index => $type) {
        log_record_access($access['id'], $patientId, $providerId, $type, $index === 0);
    }
    $_SESSION['logged_access'][$patientId] = true;
}

$fullname = $_SESSION['fullname'] ?? 'NHRE User';
$errors = session_pull('errors', []);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Authorized Records - NHRE</title>
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
          <span class="auth-kicker">Authorized Records</span>
          <h1><?= e($patient['fullname']) ?></h1>
          <p>Access granted by the patient until <?= e(date('j M Y', strtotime($access['expires_at']))) ?>. All views are logged and visible to the patient.</p>
        </div>
        <div class="dashboard-user-pill">
          <i class="fa-solid fa-file-shield"></i>
          <span>Patient ID #<?= (int)$patient['id'] ?></span>
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

      <div class="row g-4 mt-1">
        <div class="col-lg-4">
          <article class="dashboard-card">
            <div class="dashboard-card-icon"><i class="fa-solid fa-address-card"></i></div>
            <h2>Basic Information</h2>
            <p class="mb-1"><strong>Name:</strong> <?= e($patient['fullname']) ?></p>
            <p class="mb-1"><strong>Age:</strong> <?= $patient['date_of_birth'] ? e((string)age_from_dob($patient['date_of_birth'])) . ' years' : '—' ?></p>
            <p class="mb-1"><strong>Gender:</strong> <?= $patient['gender'] ? e($patient['gender']) : '—' ?></p>
            <p class="mb-0"><strong>Blood Group:</strong> <?= $patient['blood_group'] ? e($patient['blood_group']) : '—' ?></p>
          </article>
        </div>

        <div class="col-lg-8">
          <article class="dashboard-card">
            <div class="dashboard-card-icon"><i class="fa-solid fa-folder-open"></i></div>
            <h2>Authorized Record Types</h2>
            <?php if ($recordTypes): ?>
              <div class="row g-3 mt-1">
                <?php foreach ($recordTypes as $type): ?>
                  <div class="col-md-6">
                    <div class="border rounded p-3 h-100">
                      <div class="d-flex align-items-center gap-2 mb-2">
                        <i class="fa-solid fa-file-lines text-teal"></i>
                        <strong><?= e($type) ?></strong>
                      </div>
                      <p class="mb-0 text-muted small">
                        <?php if ((string)($_SESSION['role'] ?? '') === 'Pharmacist' && ($type === 'Prescriptions' || $type === 'Allergies' || $type === 'Medical History')): ?>
                          Available in the current patient pharmacy and clinical record set.
                        <?php else: ?>
                          Record access is active and the view is logged for this patient.
                        <?php endif; ?>
                      </p>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <p class="text-muted mt-2">The patient has not authorized any specific record types yet.</p>
            <?php endif; ?>
            <p class="small text-muted mt-3 mb-0">
              <i class="fa-solid fa-circle-info"></i>
              Your access to this record was logged and the patient was notified.
            </p>
          </article>
        </div>
      </div>

      <div class="row g-4 mt-1">
        <?php if ((string)($_SESSION['role'] ?? '') === 'Pharmacist'): ?>
          <div class="col-md-6">
            <article class="dashboard-card">
              <div class="dashboard-card-icon"><i class="fa-solid fa-pills"></i></div>
              <h2 class="fs-5">Current Medication Review</h2>
              <?php if ($pharmacistMedications): ?>
                <div class="list-group list-group-flush mt-2">
                  <?php foreach ($pharmacistMedications as $item): ?>
                    <div class="list-group-item px-0">
                      <div class="d-flex justify-content-between gap-3">
                        <div>
                          <strong><?= e((string)$item['medicine_name']) ?></strong><br>
                          <small class="text-muted"><?= e((string)$item['dosage']) ?> • <?= e((string)$item['frequency']) ?></small>
                        </div>
                        <span class="badge rounded-pill bg-primary-subtle text-primary-emphasis"><?= e((string)$item['status']) ?></span>
                      </div>
                      <small class="text-muted">Rx <?= e((string)$item['prescription_no']) ?> • <?= 0 + (int)$item['quantity_prescribed'] ?> units</small>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php else: ?>
                <p class="text-muted mb-0">No active medicine list is available for this patient.</p>
              <?php endif; ?>
            </article>
          </div>

          <div class="col-md-6">
            <article class="dashboard-card">
              <div class="dashboard-card-icon"><i class="fa-solid fa-clock-rotate-left"></i></div>
              <h2 class="fs-5">Dispensing History</h2>
              <?php if ($pharmacistDispensing): ?>
                <div class="list-group list-group-flush mt-2">
                  <?php foreach ($pharmacistDispensing as $item): ?>
                    <div class="list-group-item px-0">
                      <div class="d-flex justify-content-between gap-3">
                        <div>
                          <strong><?= e((string)$item['medicine_name']) ?></strong><br>
                          <small class="text-muted"><?= e((string)$item['dispensing_no']) ?> • <?= e((string)$item['created_at']) ?></small>
                        </div>
                        <span class="badge rounded-pill bg-success-subtle text-success-emphasis"><?= e((string)$item['status']) ?></span>
                      </div>
                      <small class="text-muted">Supplied: <?= (int)$item['quantity_given'] ?> units</small>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php else: ?>
                <p class="text-muted mb-0">No dispensing history is recorded for this patient yet.</p>
              <?php endif; ?>
            </article>
          </div>
        <?php endif; ?>

        <?php if ($canSee('Medical History')): ?><div class="col-12"><article class="dashboard-card"><h2 class="fs-5">Medical History — Completed Consultations</h2><?php if($history): ?><div class="table-responsive"><table class="table"><thead><tr><th>Date</th><th>Doctor</th><th>Reason</th><th>Clinical note</th></tr></thead><tbody><?php foreach($history as $item): ?><tr><td><?=e($item['appointment_date'])?></td><td><?=e($item['doctor_name'])?></td><td><?=e($item['reason'])?></td><td><?=e($item['doctor_notes']?:'—')?></td></tr><?php endforeach;?></tbody></table></div><?php else:?><p class="text-muted mb-0">No completed consultations recorded.</p><?php endif;?></article></div><?php endif;?>
        <?php if ($canSee('Allergies')): ?><div class="col-md-6"><article class="dashboard-card"><h2 class="fs-5">Allergies</h2><?php foreach($allergies as $item): ?><p><strong><?=e($item['name'])?></strong> — <?=e($item['severity'])?><?= $item['reaction_text']?' ('.e($item['reaction_text']).')':''?></p><?php endforeach;?><?php if(!$allergies):?><p class="text-muted mb-0">No active allergies recorded.</p><?php endif;?></article></div><?php endif;?>
        <?php if ($canSee('Prescriptions')): ?><div class="col-md-6"><article class="dashboard-card"><h2 class="fs-5">Prescriptions</h2><?php foreach($prescriptions as $item): ?><p><a href="prescription_view.php?id=<?=(int)$item['id']?>"><?=e($item['prescription_no'])?></a> — <?=e($item['doctor_name'])?> (<?=e((string)$item['status'])?>)</p><?php endforeach;?><?php if(!$prescriptions):?><p class="text-muted mb-0">No prescriptions recorded.</p><?php endif;?></article></div><?php endif;?>
        <?php if ($canSee('Lab Reports')): ?><div class="col-md-6"><article class="dashboard-card"><h2 class="fs-5">Lab Reports</h2><?php foreach($labs as $item): ?><p><strong><?=e($item['test_name'])?></strong> — <?=e($item['status'])?> (<?=e($item['booking_date'])?>)</p><?php endforeach;?><?php if(!$labs):?><p class="text-muted mb-0">No lab reports recorded.</p><?php endif;?></article></div><?php endif;?>
        <?php if ($canSee('Medical Documents')): ?><div class="col-md-6"><article class="dashboard-card"><h2 class="fs-5">Medical Documents</h2><?php foreach($documents as $item): ?><p><a href="auth/document_download.php?id=<?=(int)$item['id']?>"><?=e($item['original_name'])?></a> — <?=e($item['verification_status'])?></p><?php endforeach;?><?php if(!$documents):?><p class="text-muted mb-0">No documents recorded.</p><?php endif;?></article></div><?php endif;?>
      </div>
    </section>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/app.js?v=20260811-8"></script>
</body>
</html>
