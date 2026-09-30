<?php
require_once __DIR__ . '/auth/auth_check.php';
require_role(['Patient']);
ensure_access_tables_exists();
ensure_doctor_profile_columns();
ensure_doctor_catalog_tables();

$fullname = $_SESSION['fullname'] ?? 'NHRE User';
$email = $_SESSION['email'] ?? '';
$role = $_SESSION['role'] ?? 'User';
$errors = session_pull('errors', []);
$old = session_pull('old', []);
$success = session_pull('success');

$patientId = (int)($_SESSION['user_id'] ?? 0);

try {
    $stmt = db()->prepare(
        'SELECT ap.id, ap.provider_id, ap.provider_role, ap.record_types, ap.granted_at, ap.expires_at, ap.status,
                CASE WHEN ap.provider_role = \'Hospital\' THEN h2.name ELSE u.fullname END AS provider_name,
                CASE WHEN ap.provider_role = \'Hospital\' THEN h2.name
                     ELSE COALESCE(NULLIF(u.hospital_name, ""), h.name, "Independent practice") END AS organization
         FROM access_permissions ap
         LEFT JOIN users u ON u.id = ap.provider_id
         LEFT JOIN hospitals h ON h.id = u.hospital_id
         LEFT JOIN hospitals h2 ON h2.id = ap.provider_id
         WHERE ap.patient_id = ?
         ORDER BY ap.created_at DESC'
    );
    $stmt->execute([$patientId]);
    $permissions = $stmt->fetchAll();
} catch (PDOException $e) {
    $permissions = [];
}

try {
    $stmt = db()->prepare(
        'SELECT al.id, al.record_type, al.action, al.accessed_at,
                CASE WHEN ap.provider_role = \'Hospital\' THEN (SELECT h.name FROM hospitals h WHERE h.id = al.provider_id)
                     ELSE u.fullname END AS provider_name,
                CASE WHEN ap.provider_role = \'Hospital\' THEN (SELECT h.name FROM hospitals h WHERE h.id = al.provider_id)
                     ELSE COALESCE(NULLIF(u.hospital_name, ""), h.name, "Independent practice") END AS organization
         FROM access_logs al
         LEFT JOIN users u ON u.id = al.provider_id
         LEFT JOIN hospitals h ON h.id = u.hospital_id
         LEFT JOIN access_permissions ap ON ap.id = al.permission_id
         WHERE al.patient_id = ?
         ORDER BY al.accessed_at DESC
         LIMIT 30'
    );
    $stmt->execute([$patientId]);
    $accessLog = $stmt->fetchAll();
} catch (PDOException $e) {
    $accessLog = [];
}

$recordTypes = access_record_types();

try {
    $stmt = db()->prepare(
        'SELECT ap.id, ap.provider_id, u.fullname AS provider_name,
                COALESCE(NULLIF(u.hospital_name, ""), h.name, "Independent practice") AS organization
         FROM access_permissions ap
         LEFT JOIN users u ON u.id = ap.provider_id
         LEFT JOIN hospitals h ON h.id = u.hospital_id
         WHERE ap.patient_id = ? AND ap.status = \'Requested\'
         ORDER BY ap.created_at DESC'
    );
    $stmt->execute([$patientId]);
    $pendingRequests = $stmt->fetchAll();
} catch (PDOException $e) {
    $pendingRequests = [];
}

$preselectProvider = 0;
$approveNotice = '';
if (isset($_GET['approve']) && ctype_digit((string)$_GET['approve'])) {
    foreach ($pendingRequests as $pending) {
        if ((int)$pending['id'] === (int)$_GET['approve']) {
            $preselectProvider = (int)$pending['provider_id'];
            $approveNotice = 'Approving access for ' . $pending['provider_name'] . ' — select the record types and expiration date below, then press Grant Access.';
            break;
        }
    }
}

$doctors = [];
try {
    $stmt = db()->prepare(
        'SELECT u.id, u.fullname, COALESCE(NULLIF(u.specialization, ""), s.name, "General Medicine") AS specialization,
                COALESCE(NULLIF(u.hospital_name, ""), h.name, "Independent practice") AS organization
         FROM users u
         LEFT JOIN hospitals h ON h.id = u.hospital_id
         LEFT JOIN specializations s ON s.id = u.specialization_id
         WHERE u.role = ? AND u.id != ?
         ORDER BY u.fullname ASC'
    );
    $stmt->execute(['Doctor', $patientId]);
    $doctors = $stmt->fetchAll();
} catch (PDOException $e) {
    $doctors = [];
}

$hospitals = [];
try {
    $stmt = db()->query('SELECT id, name FROM hospitals WHERE is_active = 1 ORDER BY name ASC');
    $hospitals = $stmt->fetchAll();
} catch (PDOException $e) {
    $hospitals = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Data Access - NHRE</title>
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
          <span class="auth-kicker">Consent & Access</span>
          <h1>Data Access</h1>
          <p>You control which healthcare providers can read your records, and every access is logged.</p>
        </div>
        <div class="dashboard-user-pill">
          <i class="fa-solid fa-user-shield"></i>
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

      <?php if ($approveNotice): ?>
        <div class="alert alert-info auth-alert mt-4" role="alert">
          <i class="fa-solid fa-circle-info"></i>
          <span><?= e($approveNotice) ?></span>
        </div>
      <?php endif; ?>

      <?php if ($pendingRequests): ?>
        <div class="row g-4 mt-1">
          <div class="col-12">
            <article class="dashboard-card">
              <div class="dashboard-card-icon"><i class="fa-solid fa-envelope-open-text"></i></div>
              <h2>Pending Access Requests</h2>
              <p>Providers who have asked to view your records. Approve to continue to the grant form, or reject the request.</p>
              <div class="table-responsive mt-3">
                <table class="table table-hover align-middle">
                  <thead>
                    <tr>
                      <th>Provider</th>
                      <th>Organization</th>
                      <th></th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($pendingRequests as $pending): ?>
                      <tr>
                        <td>
                          <?= e($pending['provider_name'] ?: 'Unknown provider') ?>
                          <small class="d-block text-muted">Doctor</small>
                        </td>
                        <td><?= e($pending['organization']) ?></td>
                        <td class="text-end">
                          <a class="btn btn-sm btn-solid-nhre" href="data_access.php?approve=<?= (int)$pending['id'] ?>">Approve</a>
                          <form action="auth/access_response_process.php" method="POST" class="d-inline" onsubmit="return confirm('Reject this access request?');">
                            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                            <input type="hidden" name="permission_id" value="<?= (int)$pending['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger">Reject</button>
                          </form>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </article>
          </div>
        </div>
      <?php endif; ?>

      <div class="row g-4 mt-1">
        <div class="col-lg-7">
          <article class="dashboard-card">
            <div class="dashboard-card-icon"><i class="fa-solid fa-hand-holding-medical"></i></div>
            <h2>Who Can Access Your Records</h2>
            <?php if ($permissions): ?>
              <div class="table-responsive mt-3">
                <table class="table table-hover align-middle">
                  <thead>
                    <tr>
                      <th>Provider</th>
                      <th>Organization</th>
                      <th>Permissions</th>
                      <th>Expires</th>
                      <th>Status</th>
                      <th></th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($permissions as $permission): ?>
                      <?php
                      $isActive = $permission['status'] === 'Active'
                        && strtotime($permission['expires_at']) > time();
                      ?>
                      <tr>
                        <td>
                          <?= e($permission['provider_name'] ?: 'Unknown provider') ?>
                          <small class="d-block text-muted"><?= e($permission['provider_role']) ?></small>
                        </td>
                        <td><?= e($permission['organization']) ?></td>
                        <td>
                          <?php
                          $types = array_filter(array_map('trim', explode(',', $permission['record_types'])));
                          foreach ($types as $type): ?>
                            <span class="badge bg-light text-dark"><?= e($type) ?></span>
                          <?php endforeach; ?>
                        </td>
                        <td><?= e(date('j M Y', strtotime($permission['expires_at']))) ?></td>
                        <td>
                          <?php if ($isActive): ?>
                            <span class="badge bg-success">Active</span>
                          <?php elseif ($permission['status'] === 'Revoked'): ?>
                            <span class="badge bg-secondary">Revoked</span>
                          <?php else: ?>
                            <span class="badge bg-warning text-dark">Expired</span>
                          <?php endif; ?>
                        </td>
                        <td>
                          <?php if ($isActive): ?>
                            <form action="auth/access_revoke_process.php" method="POST" class="d-inline" onsubmit="return confirm('Revoke this provider\u2019s access to your records?');">
                              <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                              <input type="hidden" name="permission_id" value="<?= (int)$permission['id'] ?>">
                              <button type="submit" class="btn btn-sm btn-outline-danger">Revoke</button>
                            </form>
                          <?php endif; ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php else: ?>
              <p class="text-muted mt-3">No healthcare provider currently has access to your records.</p>
            <?php endif; ?>
          </article>
        </div>

        <div class="col-lg-5">
          <article class="dashboard-card">
            <div class="dashboard-card-icon"><i class="fa-solid fa-user-plus"></i></div>
            <h2>Grant Access</h2>
            <p>Select a provider, choose record types, and set how long access should last.</p>
            <form action="auth/access_grant_process.php" method="POST" class="mt-3">
              <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
              <div class="mb-3">
                <label class="form-label">Provider Type</label>
                <select class="form-select" name="provider_type" id="providerType">
                  <option value="Doctor">Doctor</option>
                  <option value="Hospital">Hospital / Organization</option>
                </select>
              </div>
              <div class="mb-3" id="doctorField">
                <label class="form-label">Doctor</label>
                <select class="form-select" name="provider_id" id="doctorSelect">
                  <?php if ($doctors): ?>
                    <?php foreach ($doctors as $doctor): ?>
                      <option value="<?= (int)$doctor['id'] ?>" <?= (($old['provider_id'] ?? $preselectProvider) == $doctor['id']) ? 'selected' : '' ?>>
                        <?= e($doctor['fullname']) ?> — <?= e($doctor['organization']) ?>
                      </option>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <option value="">No doctors available</option>
                  <?php endif; ?>
                </select>
              </div>
              <div class="mb-3" id="hospitalField" hidden>
                <label class="form-label">Hospital / Organization</label>
                <select class="form-select" name="hospital_id" id="hospitalSelect">
                  <?php if ($hospitals): ?>
                    <?php foreach ($hospitals as $hospital): ?>
                      <option value="<?= (int)$hospital['id'] ?>"><?= e($hospital['name']) ?></option>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <option value="">No hospitals available</option>
                  <?php endif; ?>
                </select>
              </div>
              <div class="mb-3">
                <label class="form-label">Record Types</label>
                <?php foreach ($recordTypes as $type): ?>
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="record_types[]" value="<?= e($type) ?>"
                      id="rt-<?= e(strtolower(str_replace(' ', '-', $type))) ?>">
                    <label class="form-check-label" for="rt-<?= e(strtolower(str_replace(' ', '-', $type))) ?>">
                      <?= e($type) ?>
                    </label>
                  </div>
                <?php endforeach; ?>
              </div>
              <div class="row g-3">
                <div class="col-6">
                  <label class="form-label">Start Date</label>
                  <input type="date" class="form-control" name="granted_at" value="<?= e($old['granted_at'] ?? date('Y-m-d')) ?>" required>
                </div>
                <div class="col-6">
                  <label class="form-label">Expires</label>
                  <input type="date" class="form-control" name="expires_at" value="<?= e($old['expires_at'] ?? date('Y-m-d', strtotime('+7 days'))) ?>" required>
                </div>
              </div>
              <button type="submit" class="btn btn-solid-nhre w-100 mt-3">Grant Access</button>
            </form>
          </article>
        </div>
      </div>

      <div class="row g-4 mt-1">
        <div class="col-12">
          <article class="dashboard-card">
            <div class="dashboard-card-icon"><i class="fa-solid fa-clock-rotate-left"></i></div>
            <h2>Access History</h2>
            <?php if ($accessLog): ?>
              <div class="table-responsive mt-3">
                <table class="table table-hover align-middle">
                  <thead>
                    <tr>
                      <th>Provider</th>
                      <th>Organization</th>
                      <th>Record Accessed</th>
                      <th>Action</th>
                      <th>Date & Time</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($accessLog as $log): ?>
                      <tr>
                        <td><?= e($log['provider_name'] ?: 'Unknown provider') ?></td>
                        <td><?= e($log['organization']) ?></td>
                        <td><?= e($log['record_type']) ?></td>
                        <td><span class="badge bg-info text-dark text-capitalize"><?= e($log['action']) ?></span></td>
                        <td><?= e(date('j M Y, g:i a', strtotime($log['accessed_at']))) ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php else: ?>
              <p class="text-muted mt-3">No provider has viewed your records yet.</p>
            <?php endif; ?>
          </article>
        </div>
      </div>
    </section>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/app.js?v=20260818-10"></script>
  <script>
    (function () {
      const type = document.getElementById('providerType');
      const doctorField = document.getElementById('doctorField');
      const hospitalField = document.getElementById('hospitalField');
      const doctorSelect = document.getElementById('doctorSelect');
      const hospitalSelect = document.getElementById('hospitalSelect');
      if (!type) return;
      const sync = () => {
        const hospital = type.value === 'Hospital';
        hospitalField.hidden = !hospital;
        doctorField.hidden = hospital;
        doctorSelect.disabled = hospital;
        hospitalSelect.disabled = !hospital;
      };
      type.addEventListener('change', sync);
      sync();
    })();
  </script>
</body>
</html>
