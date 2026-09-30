<?php
require_once __DIR__ . '/auth/auth_check.php';
require_role(['Patient', 'Doctor', 'Lab Technician']);

$fullname = $_SESSION['fullname'] ?? 'NHRE User';
$role = $_SESSION['role'] ?? 'User';
$errors = session_pull('errors', []);
$success = session_pull('success');

$vaccines = [
    ['name' => 'BCG', 'required_doses' => 1, 'gap_days' => 0, 'description' => 'Protects against tuberculosis.'],
    ['name' => 'DPT', 'required_doses' => 3, 'gap_days' => 28, 'description' => 'Protects against diphtheria, pertussis, and tetanus.'],
    ['name' => 'Polio', 'required_doses' => 4, 'gap_days' => 28, 'description' => 'Protects against poliovirus infection.'],
    ['name' => 'Hepatitis B', 'required_doses' => 3, 'gap_days' => 28, 'description' => 'Protects against hepatitis B infection.'],
    ['name' => 'Measles', 'required_doses' => 2, 'gap_days' => 30, 'description' => 'Protects against measles infection.'],
    ['name' => 'MMR', 'required_doses' => 2, 'gap_days' => 30, 'description' => 'Protects against mumps, measles, and rubella.'],
    ['name' => 'Typhoid', 'required_doses' => 2, 'gap_days' => 14, 'description' => 'Protects against typhoid fever.'],
    ['name' => 'Rabies', 'required_doses' => 3, 'gap_days' => 3, 'description' => 'Rabies vaccine requires 3 doses with 3-day spacing.'],
    ['name' => 'COVID-19', 'required_doses' => 3, 'gap_days' => 28, 'description' => 'Recommended booster schedule for COVID-19 protection.'],
    ['name' => 'Influenza', 'required_doses' => 1, 'gap_days' => 0, 'description' => 'Annual flu protection for seasonal immunity.'],
    ['name' => 'HPV', 'required_doses' => 2, 'gap_days' => 180, 'description' => 'Protects against several human papillomavirus infections.'],
    ['name' => 'Tetanus', 'required_doses' => 3, 'gap_days' => 30, 'description' => 'Recommended booster doses for tetanus prevention.'],
];

try {
    ensure_vaccination_center_tables();
    $centers = db()->query('SELECT id, name, district, division FROM vaccination_centers WHERE is_active = 1 ORDER BY name')->fetchAll();
} catch (PDOException $e) {
    $centers = [];
    $errors[] = 'Vaccination booking services are temporarily unavailable.';
}
$centerDirectory = array_map(static fn(array $center): array => ['id' => (int)$center['id'], 'name' => $center['name'], 'district' => $center['district'], 'division' => $center['division'] ?? ''], $centers);
$divisions = array_values(array_unique(array_filter(array_column($centerDirectory, 'division'))));
sort($divisions);

$userId = (int)($_SESSION['user_id'] ?? 0);
$patientBookings = [];
$technicianBookings = [];
if ($role === 'Patient') {
    try {
        $stmt = db()->prepare(
            'SELECT DISTINCT vb.id, vb.vaccine_name, vb.dose_number, vb.booking_date, vb.booking_time, vb.status, vb.status_notes,
                    vc.name AS center_name
             FROM vaccination_bookings vb
             LEFT JOIN vaccination_centers vc ON vc.id = vb.center_id
             WHERE vb.user_id = ?
             ORDER BY vb.booking_date DESC, vb.created_at DESC'
        );
        $stmt->execute([$userId]);
        $patientBookings = $stmt->fetchAll();
    } catch (PDOException $e) {
        $errors[] = 'Unable to load your vaccination bookings.';
    }
} elseif ($role === 'Lab Technician') {
    try {
        $stmt = db()->prepare(
            'SELECT vb.id, vb.vaccine_name, vb.dose_number, vb.booking_date, vb.booking_time, vb.status, vb.status_notes,
                    u.fullname AS patient_name, u.email AS patient_email, vb.contact_phone, vc.name AS center_name
             FROM vaccination_bookings vb
             JOIN users u ON u.id = vb.user_id
             LEFT JOIN vaccination_centers vc ON vc.id = vb.center_id
             JOIN lab_technician_assignments lta ON lta.center_id = vb.center_id AND lta.technician_id = ? AND lta.section_name IS NULL
             ORDER BY vb.booking_date ASC, vb.created_at DESC'
        );
        $stmt->execute([$userId]);
        $technicianBookings = $stmt->fetchAll();
    } catch (PDOException $e) {
        $errors[] = 'Unable to load vaccination bookings.';
    }
}

try {
    $stmt = db()->prepare('SELECT id, report_type, title, details, uploaded_by, uploaded_at, is_viewed FROM doctor_reports WHERE user_id = ? ORDER BY uploaded_at DESC LIMIT 10');
    $stmt->execute([(int)($_SESSION['user_id'] ?? 0)]);
    $reports = $stmt->fetchAll();
} catch (PDOException $e) {
    $reports = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Vaccination - NHRE</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="assets/css/styles.css?v=20260818-17">
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
  <nav class="dashboard-nav">
    <div class="container d-flex align-items-center justify-content-between gap-3">
      <a class="navbar-brand d-flex align-items-center gap-2" href="dashboard.php">
        <img src="assets/images/nhre-logo.svg" alt="NHRE" class="nhre-logo-img">
      </a>
      <div class="d-flex gap-2">
        <a href="dashboard.php" class="btn btn-dashboard-logout ripple"><i class="fa-solid fa-house"></i> <span>Dashboard</span></a>
        <a href="notifications.php" class="btn btn-dashboard-logout ripple"><i class="fa-solid fa-bell"></i> <span>Notifications</span></a>
      </div>
    </div>
  </nav>

  <main class="dashboard-main">
    <section class="container">
      <div class="dashboard-hero glass-card">
        <div>
          <span class="auth-kicker"><?= $role === 'Lab Technician' ? 'Lab Technician Workspace' : 'Vaccination Center' ?></span>
          <h1><?= $role === 'Lab Technician' ? 'Vaccination Schedule' : 'Vaccination schedule for ' . e($fullname) ?></h1>
          <p><?= $role === 'Lab Technician' ? 'Review and manage patient vaccination booking requests.' : 'Track routine immunizations, required doses, and dose gaps in one place.' ?></p>
        </div>
        <div class="dashboard-user-pill">
          <i class="fa-solid fa-syringe"></i>
          <span><?= e($role) ?></span>
        </div>
      </div>

      <?php if ($errors): ?>
        <div class="alert alert-danger auth-alert" role="alert">
          <i class="fa-solid fa-circle-exclamation"></i>
          <div>
            <?php foreach ($errors as $message): ?>
              <div><?= e($message) ?></div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <?php if ($success): ?>
        <div class="alert alert-success auth-alert" role="alert">
          <i class="fa-solid fa-circle-check"></i>
          <span><?= e($success) ?></span>
        </div>
      <?php endif; ?>

      <?php if ($role !== 'Lab Technician'): ?>
      <div class="row g-4">
        <?php foreach ($vaccines as $vaccine): ?>
          <div class="col-md-6 col-xl-4">
            <article class="dashboard-card">
              <div class="dashboard-card-icon"><i class="fa-solid fa-shield-virus"></i></div>
              <h2><?= e($vaccine['name']) ?></h2>
              <p><?= e($vaccine['description']) ?></p>
              <div class="mt-3">
                <span class="badge bg-info-subtle text-info-emphasis me-2">Required doses: <?= (int)$vaccine['required_doses'] ?></span>
                <span class="badge bg-secondary-subtle text-secondary-emphasis">Dose gap: <?= (int)$vaccine['gap_days'] ?> day(s)</span>
              </div>
              <?php if ($role === 'Patient'): ?>
                <button class="btn btn-solid-nhre w-100 mt-3" type="button" data-bs-toggle="modal" data-bs-target="#bookVaccineModal<?= e(str_replace([' ', '-'], '', $vaccine['name'])) ?>">
                  <i class="fa-solid fa-calendar-plus"></i> Book
                </button>
              <?php else: ?>
                <a class="btn btn-solid-nhre w-100 mt-3" href="vaccination_centers.php?vaccine=<?= urlencode($vaccine['name']) ?>">
                  <i class="fa-solid fa-location-dot"></i> Choose Vaccination Center
                </a>
              <?php endif; ?>
            </article>
          </div>

          <?php if ($role === 'Patient'): ?>
            <div class="modal fade vaccination-modal" id="bookVaccineModal<?= e(str_replace([' ', '-'], '', $vaccine['name'])) ?>" tabindex="-1" aria-hidden="true">
              <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                  <form method="post" action="auth/vaccination_process.php">
                    <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                    <input type="hidden" name="action" value="book_vaccination">
                    <input type="hidden" name="vaccine_name" value="<?= e($vaccine['name']) ?>">
                    <div class="modal-header">
                      <h5 class="modal-title">Book <?= e($vaccine['name']) ?> vaccination</h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                      <div class="mb-3">
                        <label class="form-label">Dose number</label>
                        <select class="form-select" name="dose_number" required>
                          <?php for ($dose = 1; $dose <= $vaccine['required_doses']; $dose++): ?>
                            <option value="<?= $dose ?>">Dose <?= $dose ?></option>
                          <?php endfor; ?>
                        </select>
                      </div>
                      <div class="row g-3 mb-3 vaccination-location-picker">
                        <div class="col-md-6"><label class="form-label">Division</label><select class="form-select js-division" required><option value="" selected disabled>Select division</option><?php foreach ($divisions as $division): ?><option value="<?= e($division) ?>"><?= e($division) ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-6"><label class="form-label">City / district</label><select class="form-select js-district" disabled required><option value="" selected disabled>Select city / district</option></select></div>
                        <div class="col-12"><label class="form-label">Preferred hospital / vaccination center</label><select class="form-select js-hospital" name="center_id" disabled required><option value="" selected disabled>Select hospital</option></select><div class="form-text">Hospitals appear after you choose a division and city/district.</div></div>
                      </div>
                      <div class="row g-3">
                        <div class="col-sm-6"><label class="form-label">Preferred date</label><input type="date" class="form-control" name="booking_date" min="<?= date('Y-m-d') ?>" required></div>
                        <div class="col-sm-6"><label class="form-label">Preferred time</label><input type="time" class="form-control" name="booking_time"></div>
                      </div>
                      <div class="mb-3 mt-3"><label class="form-label">Contact phone</label><input type="tel" class="form-control" name="contact_phone" maxlength="30" required></div>
                      <div class="mb-0"><label class="form-label">Notes <span class="text-muted">(optional)</span></label><textarea class="form-control" name="notes" rows="3" maxlength="1000" placeholder="Any relevant information for the vaccination team"></textarea></div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-solid-nhre">Submit booking</button></div>
                  </form>
                </div>
              </div>
            </div>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <?php if ($role === 'Patient'): ?>
        <section class="mt-5">
          <div class="dashboard-hero glass-card"><div><span class="auth-kicker">My bookings</span><h1>Vaccination appointments</h1><p>Follow the review and completion status of your booking requests.</p></div></div>
          <div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><th>Vaccine</th><th>Appointment</th><th>Center</th><th>Status</th><th>Notes</th></tr></thead><tbody>
            <?php if ($patientBookings): foreach ($patientBookings as $booking): ?><tr><td><?= e($booking['vaccine_name']) ?>, dose <?= (int)$booking['dose_number'] ?></td><td><?= e($booking['booking_date']) ?><?= !empty($booking['booking_time']) ? '<br><small>' . e($booking['booking_time']) . '</small>' : '' ?></td><td><?= e($booking['center_name'] ?: 'To be assigned') ?></td><td><span class="badge bg-light text-dark"><?= e($booking['status']) ?></span></td><td><?= e($booking['status_notes'] ?: '—') ?></td></tr><?php endforeach; else: ?><tr><td colspan="5" class="text-muted">You do not have any vaccination bookings yet.</td></tr><?php endif; ?>
          </tbody></table></div>
        </section>
      <?php endif; ?>

      <?php if ($role === 'Lab Technician'): ?>
        <section class="mt-5">
          <div class="dashboard-hero glass-card"><div><span class="auth-kicker">Lab Technician Management</span><h1>Manage vaccination bookings</h1><p>Review patient requests and keep each appointment status up to date.</p></div></div>
          <div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><th>Patient</th><th>Vaccination</th><th>Appointment</th><th>Preferred Hospital</th><th>Status</th><th>Action</th></tr></thead><tbody>
            <?php if ($technicianBookings): foreach ($technicianBookings as $booking): ?><tr><td><?= e($booking['patient_name']) ?><br><small class="text-muted"><?= e($booking['contact_phone']) ?></small></td><td><?= e($booking['vaccine_name']) ?>, dose <?= (int)$booking['dose_number'] ?></td><td><?= e($booking['booking_date']) ?><?= !empty($booking['booking_time']) ? '<br><small>' . e($booking['booking_time']) . '</small>' : '' ?></td><td><?= e($booking['center_name'] ?: 'Not selected') ?></td><td><span class="badge bg-light text-dark"><?= e($booking['status']) ?></span></td><td><button class="btn btn-manage-booking btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#manageVaccine<?= (int)$booking['id'] ?>">Manage</button></td></tr><?php endforeach; else: ?><tr><td colspan="6" class="text-muted">There are no vaccination bookings to manage.</td></tr><?php endif; ?>
          </tbody></table></div>
          <?php foreach ($technicianBookings as $booking): ?>
            <div class="modal fade vaccination-modal" id="manageVaccine<?= (int)$booking['id'] ?>" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form method="post" action="auth/vaccination_process.php"><input type="hidden" name="_csrf" value="<?= csrf_token() ?>"><input type="hidden" name="action" value="update_booking"><input type="hidden" name="booking_id" value="<?= (int)$booking['id'] ?>"><div class="modal-header"><h5 class="modal-title">Manage vaccination booking</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><div class="mb-3"><label class="form-label" for="vaccinationStatus<?= (int)$booking['id'] ?>">Status</label><select class="form-select" id="vaccinationStatus<?= (int)$booking['id'] ?>" name="status"><?php foreach (['Pending', 'Confirmed', 'Ongoing', 'Completed', 'Cancelled'] as $status): ?><option value="<?= $status ?>" <?= $booking['status'] === $status ? 'selected' : '' ?>><?= $status ?></option><?php endforeach; ?></select></div><div class="mb-0"><label class="form-label" for="vaccinationNotes<?= (int)$booking['id'] ?>">Status notes</label><textarea class="form-control" id="vaccinationNotes<?= (int)$booking['id'] ?>" name="status_notes" rows="3" maxlength="2000" placeholder="Add an update for the patient"><?= e($booking['status_notes']) ?></textarea></div></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-solid-nhre">Save</button></div></form></div></div></div>
          <?php endforeach; ?>
        </section>
      <?php endif; ?>

      <div class="row g-4 mt-2">
        <div class="col-12">
          <article class="dashboard-card">
            <div class="dashboard-card-icon"><i class="fa-solid fa-file-medical"></i></div>
            <h2>Doctor Uploaded Reports</h2>
            <?php if ($reports): ?>
              <div class="table-responsive mt-3">
                <table class="table table-hover align-middle">
                  <thead>
                    <tr>
                      <th>Report</th>
                      <th>Type</th>
                      <th>Doctor</th>
                      <th>Date</th>
                      <th>Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($reports as $report): ?>
                      <tr>
                        <td><?= e($report['title']) ?></td>
                        <td><?= e($report['report_type']) ?></td>
                        <td><?= e($report['uploaded_by']) ?></td>
                        <td><?= e($report['uploaded_at']) ?></td>
                        <td><?= (int)$report['is_viewed'] === 1 ? 'Viewed' : 'New' ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php else: ?>
              <p class="text-muted mt-3">No doctor reports have been uploaded yet.</p>
            <?php endif; ?>
          </article>
        </div>
      </div>
    </section>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    (() => {
      const centers = <?= json_encode($centerDirectory, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
      const reset = (select, label) => {
        select.replaceChildren(new Option(`Select ${label}`, '', true, true));
        select.disabled = true;
      };
      const addOptions = (select, values, label, key = null) => {
        reset(select, label);
        values.forEach((value) => select.add(new Option(key ? value[key] : value, key ? value.id : value)));
        select.disabled = values.length === 0;
      };
      document.querySelectorAll('.vaccination-location-picker').forEach((picker) => {
        const division = picker.querySelector('.js-division'), district = picker.querySelector('.js-district'), hospital = picker.querySelector('.js-hospital');
        reset(district, 'city / district');
        reset(hospital, 'hospital');
        division.addEventListener('change', () => {
          const districts = [...new Set(centers.filter(c => c.division === division.value).map(c => c.district))].sort();
          addOptions(district, districts, 'city / district');
          reset(hospital, 'hospital');
        });
        district.addEventListener('change', () => addOptions(hospital, centers.filter(c => c.division === division.value && c.district === district.value), 'hospital', 'name'));
      });
    })();
  </script>
  <script src="assets/js/app.js?v=20260818-10"></script>
</body>
</html>
