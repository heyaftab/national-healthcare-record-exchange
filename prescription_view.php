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

$rx_id = (int)($_GET['id'] ?? 0);
$rx = get_prescription($rx_id);

if (!$rx) {
    $_SESSION['errors'] = ['Prescription not found.'];
    redirect('prescriptions.php');
}

if ($role === 'Patient' && (int)$rx['patient_id'] !== (int)$_SESSION['user_id']) {
    $_SESSION['errors'] = ['You do not have permission to view that prescription.'];
    redirect('prescriptions.php');
}
if ($role === 'Doctor' && (int)$rx['doctor_id'] !== (int)$_SESSION['user_id']) {
    $_SESSION['errors'] = ['You do not have permission to view that prescription.'];
    redirect('prescriptions.php');
}

$items = get_prescription_items($rx_id);
foreach ($items as &$item) {
    $item['given'] = (float)$item['given'];
    $item['remaining'] = (float)$item['quantity_prescribed'] - $item['given'];
    $item['available'] = $role === 'Pharmacist' ? available_stock((int)$item['medicine_id']) : null;
    $item['satisfiable'] = $item['available'] !== null && $item['available'] >= $item['remaining'] - 1e-9;
}
unset($item);

if ($role === 'Pharmacist' || $role === 'Doctor') {
    log_audit('VIEW_PRESCRIPTION', 'prescription', $rx_id, 'Viewed prescription ' . $rx['prescription_no']);
}

$dispensable = in_array((string)$rx['status'], prescription_dispensable_statuses(), true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Prescription <?= e($rx['prescription_no']) ?> - NHRE</title>
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
          <span class="auth-kicker">Prescription Detail</span>
          <h1><?= e($rx['prescription_no']) ?></h1>
          <p>Issued <?= e(date('j M Y, g:i a', strtotime($rx['created_at']))) ?> • Valid until <?= e(date('j M Y', strtotime($rx['expires_at']))) ?></p>
        </div>
        <div class="text-end">
          <?= pharmacy_status_badge((string)$rx['status']) ?>
          <div class="mt-2">
            <a href="prescriptions.php" class="btn btn-dashboard-logout ripple"><i class="fa-solid fa-arrow-left"></i> <span>Back</span></a>
          </div>
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

      <?php if ((string)$rx['status'] === 'REJECTED' && !empty($rx['rejection_reason'])): ?>
        <div class="alert alert-danger auth-alert mt-4" role="alert">
          <i class="fa-solid fa-ban"></i>
          <div>
            <strong>Rejected by the pharmacy.</strong>
            <div><?= e($rx['rejection_reason']) ?></div>
          </div>
        </div>
      <?php endif; ?>

      <?php if ((string)$rx['status'] === 'EXPIRED'): ?>
        <div class="alert alert-warning auth-alert mt-4" role="alert">
          <i class="fa-solid fa-clock"></i>
          <div>This prescription has expired and can no longer be dispensed.</div>
        </div>
      <?php endif; ?>

      <div class="row g-4 mt-1">
        <div class="col-lg-8">
          <article class="dashboard-card">
            <div class="dashboard-card-icon"><i class="fa-solid fa-list-check"></i></div>
            <h2>Prescribed medicines</h2>
            <div class="table-responsive mt-3">
              <table class="table table-hover align-middle">
                <thead>
                  <tr>
                    <th>Medicine</th>
                    <th>Dosage</th>
                    <th>Frequency</th>
                    <th>Duration</th>
                    <th class="text-end">Prescribed</th>
                    <th class="text-end">Given</th>
                    <th class="text-end">Remaining</th>
                    <?php if ($role === 'Pharmacist'): ?><th class="text-end">Stock</th><?php endif; ?>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($items as $item): ?>
                    <tr>
                      <td>
                        <strong><?= e($item['medicine_name']) ?></strong>
                        <?php if (!empty($item['instructions'])): ?><div class="small text-muted"><?= e($item['instructions']) ?></div><?php endif; ?>
                      </td>
                      <td><?= e($item['dosage']) ?></td>
                      <td><?= e($item['frequency']) ?></td>
                      <td><?= $item['duration_days'] ? (int)$item['duration_days'] . ' days' : '—' ?></td>
                      <td class="text-end"><?= pharmacy_qty((float)$item['quantity_prescribed']) ?> <?= e($item['unit']) ?></td>
                      <td class="text-end"><?= pharmacy_qty($item['given']) ?> <?= e($item['unit']) ?></td>
                      <td class="text-end">
                        <?= $item['remaining'] > 1e-9 ? pharmacy_qty($item['remaining']) : '<span class="text-success">—</span>' ?>
                      </td>
                      <?php if ($role === 'Pharmacist'): ?>
                        <td class="text-end">
                          <?php if ($item['remaining'] > 1e-9): ?>
                            <span class="badge rounded-pill <?= $item['satisfiable'] ? 'bg-success-subtle text-success-emphasis' : 'bg-danger-subtle text-danger-emphasis' ?>">
                              <?= pharmacy_qty((float)$item['available']) ?> <?= e($item['unit']) ?>
                            </span>
                          <?php else: ?>
                            —
                          <?php endif; ?>
                        </td>
                      <?php endif; ?>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>

            <?php if (!empty($rx['notes'])): ?>
              <hr>
              <p class="mb-0"><strong>Doctor notes:</strong> <?= e($rx['notes']) ?></p>
            <?php endif; ?>
          </article>

          <?php if ($role === 'Pharmacist' && $dispensable): ?>
            <article class="dashboard-card mt-4">
              <div class="dashboard-card-icon"><i class="fa-solid fa-hand-holding-medical"></i></div>
              <h2>Dispense medicines</h2>
              <p>Enter how much of each medicine is being handed over. Stock is deducted first-expiry-first-out.</p>
              <form action="auth/prescription_dispense_process.php" method="POST">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="prescription_id" value="<?= $rx_id ?>">
                <div class="table-responsive mt-3">
                  <table class="table align-middle">
                    <thead>
                      <tr>
                        <th>Medicine</th>
                        <th class="text-end">Remaining</th>
                        <th class="text-end">Available</th>
                        <th class="text-end" style="width:140px">Qty to dispense</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($items as $item): ?>
                        <?php if ($item['remaining'] <= 1e-9) { continue; } ?>
                        <tr>
                          <td><?= e($item['medicine_name']) ?></td>
                          <td class="text-end"><?= pharmacy_qty($item['remaining']) ?> <?= e($item['unit']) ?></td>
                          <td class="text-end">
                            <span class="badge rounded-pill <?= $item['satisfiable'] ? 'bg-success-subtle text-success-emphasis' : 'bg-danger-subtle text-danger-emphasis' ?>">
                              <?= pharmacy_qty((float)$item['available']) ?> <?= e($item['unit']) ?>
                            </span>
                          </td>
                          <td class="text-end">
                            <input type="number" class="form-control text-end" name="quantity_given[<?= (int)$item['id'] ?>]"
                              value="<?= pharmacy_qty($item['remaining']) ?>" min="0" step="any" aria-label="Quantity to dispense">
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
                <div class="mb-3">
                  <label class="form-label" for="dispense_notes">Dispensing notes (optional)</label>
                  <textarea class="form-control" id="dispense_notes" name="notes" rows="2" maxlength="500"></textarea>
                </div>
                <button type="submit" class="btn btn-solid-nhre">
                  <i class="fa-solid fa-hand-holding-medical"></i> Dispense
                </button>
              </form>
            </article>
          <?php endif; ?>
        </div>

        <div class="col-lg-4">
          <article class="dashboard-card">
            <div class="dashboard-card-icon"><i class="fa-solid fa-user"></i></div>
            <h2>Patient</h2>
            <p class="mb-1"><strong><?= e($rx['patient_name']) ?></strong></p>
            <p class="mb-1 text-muted">NHRE ID: <?= e($rx['account_number']) ?></p>
            <?php if (!empty($rx['date_of_birth'])): ?>
              <p class="mb-1">Age: <?= age_from_dob((string)$rx['date_of_birth']) ?> years</p>
            <?php endif; ?>
            <p class="mb-0">Gender: <?= $rx['gender'] ? e($rx['gender']) : '—' ?></p>
          </article>

          <article class="dashboard-card mt-4">
            <div class="dashboard-card-icon"><i class="fa-solid fa-user-doctor"></i></div>
            <h2>Prescribing doctor</h2>
            <p class="mb-0"><strong><?= e($rx['doctor_name']) ?></strong></p>
          </article>

          <?php if ($role === 'Pharmacist'): ?>
            <?php if ((string)$rx['status'] === 'PENDING'): ?>
              <article class="dashboard-card mt-4">
                <div class="dashboard-card-icon"><i class="fa-solid fa-clipboard-check"></i></div>
                <h2>Verification</h2>
                <form action="auth/prescription_verify_process.php" method="POST" class="mb-3">
                  <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                  <input type="hidden" name="prescription_id" value="<?= $rx_id ?>">
                  <button type="submit" class="btn btn-solid-nhre w-100">
                    <i class="fa-solid fa-check"></i> Verify prescription
                  </button>
                </form>
                <form action="auth/prescription_reject_process.php" method="POST">
                  <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                  <input type="hidden" name="prescription_id" value="<?= $rx_id ?>">
                  <label class="form-label" for="reject_reason">Reject with reason</label>
                  <textarea class="form-control mb-2" id="reject_reason" name="reason" rows="3" maxlength="1000" placeholder="Required reason for rejection" required></textarea>
                  <button type="submit" class="btn btn-outline-danger w-100">
                    <i class="fa-solid fa-ban"></i> Reject
                  </button>
                </form>
              </article>
            <?php elseif ((string)$rx['status'] === 'VERIFIED'): ?>
              <article class="dashboard-card mt-4">
                <div class="dashboard-card-icon"><i class="fa-solid fa-box-open"></i></div>
                <h2>Preparation</h2>
                <form action="auth/prescription_ready_process.php" method="POST" class="mb-3">
                  <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                  <input type="hidden" name="prescription_id" value="<?= $rx_id ?>">
                  <button type="submit" class="btn btn-solid-nhre w-100">
                    <i class="fa-solid fa-boxes-stacked"></i> Mark ready for pickup
                  </button>
                </form>
                <form action="auth/prescription_reject_process.php" method="POST">
                  <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                  <input type="hidden" name="prescription_id" value="<?= $rx_id ?>">
                  <label class="form-label" for="reject_reason2">Reject with reason</label>
                  <textarea class="form-control mb-2" id="reject_reason2" name="reason" rows="3" maxlength="1000" placeholder="Required reason for rejection" required></textarea>
                  <button type="submit" class="btn btn-outline-danger w-100">
                    <i class="fa-solid fa-ban"></i> Reject
                  </button>
                </form>
              </article>
            <?php endif; ?>

            <?php if (!empty($rx['verified_name'])): ?>
              <article class="dashboard-card mt-4">
                <div class="dashboard-card-icon"><i class="fa-solid fa-user-shield"></i></div>
                <h2>Pharmacy</h2>
                <p class="mb-1"><strong><?= e($rx['verified_name']) ?></strong></p>
                <?php if (!empty($rx['verified_at'])): ?>
                  <p class="mb-0 text-muted">Verified <?= e(date('j M Y, g:i a', strtotime($rx['verified_at']))) ?></p>
                <?php endif; ?>
              </article>
            <?php endif; ?>
          <?php endif; ?>

          <article class="dashboard-card mt-4">
            <div class="dashboard-card-icon"><i class="fa-solid fa-circle-info"></i></div>
            <h2>Workflow</h2>
            <p class="mb-2 text-muted">PENDING → VERIFIED → READY → DISPENSED</p>
            <p class="mb-0 small text-muted">Medicines may be partially dispensed when stock is short. Rejected and expired prescriptions cannot be dispensed.</p>
          </article>
        </div>
      </div>
    </section>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/app.js?v=20260811-8"></script>
</body>
</html>
