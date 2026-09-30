<?php
require_once __DIR__ . '/auth/auth_check.php';
require_once __DIR__ . '/includes/pharmacy_functions.php';
require_role(['Patient', 'Doctor', 'Pharmacist']);

ensure_pharmacy_tables();
expire_stale_prescriptions();

$fullname = $_SESSION['fullname'] ?? 'NHRE User';
$role = $_SESSION['role'] ?? 'User';
$errors = session_pull('errors', []);
$success = session_pull('success');
$old = session_pull('old', []);

$statusFilter = trim((string)($_GET['status'] ?? ''));
$prescriptions = [];

try {
    if ($role === 'Patient') {
        $stmt = db()->prepare(
            'SELECT p.id, p.prescription_no, p.status, p.created_at, p.expires_at, doc.fullname AS doctor_name
               FROM prescriptions p
               JOIN users doc ON doc.id = p.doctor_id
              WHERE p.patient_id = ?
              ORDER BY p.created_at DESC
              LIMIT 30'
        );
        $stmt->execute([(int)$_SESSION['user_id']]);
        $prescriptions = $stmt->fetchAll();
    } elseif ($role === 'Doctor') {
        $stmt = db()->prepare(
            'SELECT p.id, p.prescription_no, p.status, p.created_at, p.expires_at, pat.fullname AS patient_name, pat.account_number
               FROM prescriptions p
               JOIN users pat ON pat.id = p.patient_id
              WHERE p.doctor_id = ?
              ORDER BY p.created_at DESC
              LIMIT 30'
        );
        $stmt->execute([(int)$_SESSION['user_id']]);
        $prescriptions = $stmt->fetchAll();
    } else {
        $sql = 'SELECT p.id, p.prescription_no, p.status, p.created_at, p.expires_at,
                       pat.fullname AS patient_name, pat.account_number, doc.fullname AS doctor_name
                  FROM prescriptions p
                  JOIN users pat ON pat.id = p.patient_id
                  JOIN users doc ON doc.id = p.doctor_id';
        $params = [];
        if (in_array($statusFilter, array_keys(prescription_status_transitions()), true)) {
            $sql .= ' WHERE p.status = ?';
            $params[] = $statusFilter;
        }
        $sql .= ' ORDER BY p.created_at DESC LIMIT 50';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        $prescriptions = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    $prescriptions = [];
}

$patients = [];
$medicines = [];
if ($role === 'Doctor') {
    try {
        $patients = db()->query("SELECT id, fullname, account_number FROM users WHERE role = 'Patient' ORDER BY fullname ASC")->fetchAll();
        $medicines = db()->query("SELECT id, name, unit FROM medicines WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
    } catch (PDOException $e) {
        $patients = [];
        $medicines = [];
    }
}

$statusCounts = [];
if ($role === 'Pharmacist') {
    try {
        foreach (db()->query("SELECT status, COUNT(*) AS c FROM prescriptions GROUP BY status")->fetchAll() as $row) {
            $statusCounts[(string)$row['status']] = (int)$row['c'];
        }
    } catch (PDOException $e) {
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Prescriptions - NHRE</title>
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
<?php if ($role === 'Doctor'): ?>
<script>
  document.addEventListener('DOMContentLoaded', function () {
    var rows = document.querySelectorAll('.rx-item-row');
    var addBtn = document.getElementById('addRxItem');
    if (!addBtn) return;

    function makeRow(index) {
      var row = document.createElement('div');
      row.className = 'row g-2 rx-item-row mb-3 p-2 border rounded';
      row.innerHTML =
        '<div class="col-md-4"><label class="form-label small">Medicine</label>' +
          '<select class="form-select" name="medicine_id[]" required>' +
            '<option value="">Select medicine</option>' +
            '<?php foreach ($medicines as $m): ?>' +
              '<option value="<?= (int)$m['id'] ?>"><?= e($m['name']) ?> (<?= e($m['unit']) ?>)</option>' +
            '<?php endforeach; ?>' +
          '</select>' +
        '</div>' +
        '<div class="col-md-2"><label class="form-label small">Quantity</label><input type="number" class="form-control" name="quantity[]" placeholder="Qty" min="0.01" max="10000" step="any" required></div>' +
        '<div class="col-md-2"><label class="form-label small">Dosage</label><input type="text" class="form-control" name="dosage[]" placeholder="e.g. 500 mg" maxlength="100" required></div>' +
        '<div class="col-md-2"><label class="form-label small">Frequency</label><input type="text" class="form-control" name="frequency[]" placeholder="e.g. twice daily" maxlength="100" required></div>' +
        '<div class="col-md-2"><label class="form-label small">Duration</label><input type="number" class="form-control" name="duration_days[]" placeholder="Days" min="1" max="365"></div>' +
        '<div class="col-md-12"><label class="form-label small">Instructions</label><div class="input-group"><input type="text" class="form-control" name="instructions[]" placeholder="Before/after food or other instructions" maxlength="500"><button type="button" class="btn btn-outline-danger remove-rx-item">Remove medicine</button></div></div>';
      return row;
    }

    var existing = Array.prototype.slice.call(rows);
    var first = existing.length ? existing[0] : makeRow(0);
    if (!existing.length) {
      document.getElementById('rxItems').appendChild(first);
    }
    existing.forEach(function (r, i) { if (i > 0) r.remove(); });

    addBtn.addEventListener('click', function (e) {
      e.preventDefault();
      document.getElementById('rxItems').appendChild(makeRow(Date.now()));
    });
    document.getElementById('rxItems').addEventListener('click', function (e) {
      if (!e.target.closest('.remove-rx-item')) return;
      var item = e.target.closest('.rx-item-row');
      if (document.querySelectorAll('.rx-item-row').length > 1) item.remove();
      else item.querySelectorAll('input, select').forEach(function (field) { field.value = ''; });
    });
  });
</script>
<?php endif; ?>
</head>
<body class="dashboard-body">
  <?php require __DIR__ . '/includes/sidebar.php'; ?>
  <?php require __DIR__ . '/includes/topnav.php'; ?>
  <main class="dashboard-main">
    <section class="container">
      <div class="dashboard-hero glass-card">
        <div>
          <span class="auth-kicker">Prescription Workspace</span>
          <h1><?= $role === 'Pharmacist' ? 'Pharmacy prescriptions' : ($role === 'Doctor' ? 'Prescriptions you issued' : 'My prescriptions') ?></h1>
          <p><?= $role === 'Pharmacist' ? 'Verify, prepare, and dispense prescriptions for patients.' : 'Review prescription status and medicine instructions.' ?></p>
        </div>
        <div class="dashboard-user-pill">
          <i class="fa-solid fa-prescription"></i>
          <span><?= e($role) ?></span>
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

      <?php if ($role === 'Pharmacist'): ?>
        <div class="row g-2 mt-3 mb-3">
          <div class="col-12">
            <div class="d-flex flex-wrap gap-2">
              <a href="prescriptions.php" class="btn btn-sm <?= $statusFilter === '' ? 'btn-solid-nhre' : 'btn-outline-nhre' ?>">All</a>
              <?php foreach (prescription_status_transitions() as $status => $unused): ?>
                <a href="prescriptions.php?status=<?= e($status) ?>" class="btn btn-sm <?= $statusFilter === $status ? 'btn-solid-nhre' : 'btn-outline-nhre' ?>">
                  <?= e(ucwords(strtolower(str_replace('_', ' ', $status)))) ?> (<?= $statusCounts[$status] ?? 0 ?>)
                </a>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <div class="row g-4 mt-1">
        <?php if ($role === 'Doctor'): ?>
          <div class="col-lg-4">
            <article class="dashboard-card" id="new-prescription">
              <div class="dashboard-card-icon"><i class="fa-solid fa-file-medical"></i></div>
              <h2>New Prescription</h2>
              <p>Issue a prescription to a patient. The pharmacy will verify and dispense it.</p>

              <form action="auth/prescription_create_process.php" method="POST">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <div class="mb-3">
                  <label class="form-label" for="patient_id">Patient</label>
                  <select class="form-select" id="patient_id" name="patient_id" required>
                    <option value="">Select patient</option>
                    <?php foreach ($patients as $patient): ?>
                      <option value="<?= (int)$patient['id'] ?>" <?= (int)($old['patient_id'] ?? 0) === (int)$patient['id'] ? 'selected' : '' ?>>
                        <?= e($patient['fullname']) ?> (<?= e($patient['account_number']) ?>)
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div class="mb-3">
                  <label class="form-label">Medicines</label>
                  <div id="rxItems"></div>
                  <button type="button" class="btn btn-outline-nhre btn-sm mt-1" id="addRxItem">
                    <i class="fa-solid fa-plus"></i> Add medicine
                  </button>
                </div>

                <div class="mb-3">
                  <label class="form-label" for="notes">Doctor notes</label>
                  <textarea class="form-control" id="notes" name="notes" rows="3" maxlength="1000" placeholder="Diagnosis, special instructions, urgency…"><?= e((string)($old['notes'] ?? '')) ?></textarea>
                </div>

                <button type="submit" class="btn btn-solid-nhre w-100">
                  <i class="fa-solid fa-paper-plane"></i> Submit Prescription
                </button>
              </form>
            </article>
          </div>
        <?php endif; ?>

        <div class="<?= $role === 'Doctor' ? 'col-lg-8' : 'col-12' ?>">
          <article class="dashboard-card">
            <div class="dashboard-card-icon"><i class="fa-solid fa-prescription-bottle-medical"></i></div>
            <h2><?= $role === 'Patient' ? 'Your prescriptions' : 'Prescription list' ?></h2>

            <?php if ($prescriptions): ?>
              <div class="table-responsive mt-3">
                <table class="table table-hover align-middle">
                  <thead>
                    <tr>
                      <th>No.</th>
                      <?php if ($role !== 'Patient'): ?><th>Patient</th><?php endif; ?>
                      <?php if ($role === 'Pharmacist'): ?><th>Doctor</th><?php endif; ?>
                      <th>Issued</th>
                      <th>Valid until</th>
                      <?php if ($role !== 'Patient'): ?><th>Status</th><?php endif; ?>
                      <th></th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($prescriptions as $prescription): ?>
                      <tr>
                        <td><?= e($prescription['prescription_no']) ?></td>
                        <?php if ($role !== 'Patient'): ?><td><?= e($prescription['patient_name']) ?><?php if (!empty($prescription['account_number'])): ?> <small class="text-muted">(<?= e($prescription['account_number']) ?>)</small><?php endif; ?></td><?php endif; ?>
                        <?php if ($role === 'Pharmacist'): ?><td><?= e($prescription['doctor_name']) ?></td><?php endif; ?>
                        <td><?= e(date('j M Y', strtotime($prescription['created_at']))) ?></td>
                        <td><?= e(date('j M Y', strtotime($prescription['expires_at']))) ?></td>
                        <?php if ($role !== 'Patient'): ?><td><?= pharmacy_status_badge((string)$prescription['status']) ?></td><?php endif; ?>
                        <td class="text-end">
                          <a href="prescription_view.php?id=<?= (int)$prescription['id'] ?>" class="btn btn-sm btn-solid-nhre">
                            <i class="fa-solid fa-eye"></i> View
                          </a>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php else: ?>
              <p class="text-muted mt-3">
                <?= $role === 'Doctor' ? 'No prescriptions issued yet. Use the form to create the first one.' : ($role === 'Pharmacist' ? 'No prescriptions match this view yet.' : 'You do not have any prescriptions yet.') ?>
              </p>
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
