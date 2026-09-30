<?php
require_once __DIR__ . '/auth/auth_check.php';
require_role(['Patient', 'Doctor', 'Lab Technician', 'Hospital Admin', 'System Admin']);

$fullname = $_SESSION['fullname'] ?? 'NHRE User';
$email = $_SESSION['email'] ?? '';
$role = $_SESSION['role'] ?? 'User';
$errors = session_pull('errors', []);
$success = session_pull('success');

if ($role === 'Lab Technician' && basename((string)($_SERVER['PHP_SELF'] ?? '')) === 'medical_tests.php') {
    redirect('lab_test_requests.php');
}

try {
    ensure_medical_test_tables_exists();
} catch (PDOException $e) {
    $errors[] = 'Unable to initialize the medical tests module.';
}

$user_id = (int)($_SESSION['user_id'] ?? 0);
$labView = in_array((string)($_GET['view'] ?? ''), ['reports', 'history'], true) ? (string)$_GET['view'] : 'requests';

$min_price = trim((string)($_GET['min_price'] ?? ''));
$max_price = trim((string)($_GET['max_price'] ?? ''));
$place = trim((string)($_GET['place'] ?? ''));
$division = trim((string)($_GET['division'] ?? ''));
$district = trim((string)($_GET['district'] ?? ''));
$hospitalId = (int)($_GET['hospital_id'] ?? 0);
$test_type = trim((string)($_GET['test_type'] ?? ''));
$result_time = trim((string)($_GET['result_time'] ?? ''));
$availability = trim((string)($_GET['availability'] ?? ''));
$home_collection = trim((string)($_GET['home_collection'] ?? ''));

$where = [];
$params = [];

if ($min_price !== '') {
    $min = (float)$min_price;
    if ($min >= 0) {
        $where[] = 'price >= ?';
        $params[] = $min;
    }
}

if ($max_price !== '') {
    $max = (float)$max_price;
    if ($max >= 0) {
        $where[] = 'price <= ?';
        $params[] = $max;
    }
}

if ($hospitalId > 0) {
    $where[] = 'center_id = ?';
    $params[] = $hospitalId;
} elseif ($district !== '') {
    $where[] = 'center_id IN (SELECT id FROM vaccination_centers WHERE division = ? AND district = ?)';
    $params[] = $division;
    $params[] = $district;
} elseif ($division !== '') {
    $where[] = 'center_id IN (SELECT id FROM vaccination_centers WHERE division = ?)';
    $params[] = $division;
}

if ($test_type !== '') {
    $where[] = 'test_type = ?';
    $params[] = $test_type;
}

if ($result_time !== '') {
    $where[] = 'result_time = ?';
    $params[] = $result_time;
}

if ($availability !== '') {
    $where[] = 'availability = ?';
    $params[] = ($availability === 'available') ? 1 : 0;
}

if ($home_collection !== '') {
    $where[] = 'home_collection = ?';
    $params[] = ($home_collection === 'yes') ? 1 : 0;
}

$sql = 'SELECT id, name, description, test_type, price, place, department, result_time, availability, home_collection FROM medical_tests';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY price ASC, name ASC';

$stmt = db()->prepare($sql);
$stmt->execute($params);
$tests = $stmt->fetchAll();

$hospitalDirectory = db()->query('SELECT id, name, district, division FROM vaccination_centers WHERE is_active = 1 ORDER BY division, district, name')->fetchAll();
$labDivisions = array_values(array_unique(array_column($hospitalDirectory, 'division')));
sort($labDivisions);

$places = [];
$types = [];
$result_times = [];
$stmt = db()->prepare('SELECT DISTINCT place FROM medical_tests WHERE place IS NOT NULL AND place <> "" ORDER BY place ASC');
$stmt->execute();
$places = $stmt->fetchAll(PDO::FETCH_COLUMN);

$stmt = db()->prepare('SELECT DISTINCT test_type FROM medical_tests WHERE test_type IS NOT NULL AND test_type <> "" ORDER BY test_type ASC');
$stmt->execute();
$types = $stmt->fetchAll(PDO::FETCH_COLUMN);

$stmt = db()->prepare('SELECT DISTINCT result_time FROM medical_tests WHERE result_time IS NOT NULL AND result_time <> "" ORDER BY result_time ASC');
$stmt->execute();
$result_times = $stmt->fetchAll(PDO::FETCH_COLUMN);

$stmt = db()->prepare(
    'SELECT mtb.id, mt.name AS test_name, mt.place, mtb.booking_date, mtb.booking_time, mtb.status, mtb.result_file, mtb.result_notes
     FROM medical_test_bookings mtb
     JOIN medical_tests mt ON mt.id = mtb.test_id
     WHERE mtb.user_id = ?
     ORDER BY mtb.booking_date DESC, mtb.created_at DESC'
);
$stmt->execute([$user_id]);
$bookings = $stmt->fetchAll();

$technician_view = ($role === 'Lab Technician');
if ($technician_view) {
    $labStatusClause = $labView === 'reports' ? " AND mtb.status = 'Completed'" : ($labView === 'history' ? " AND mtb.status IN ('Completed', 'Cancelled')" : '');
    $stmt = db()->prepare(
        'SELECT DISTINCT mtb.id, mt.name AS test_name, mt.department, mtb.booking_date, mtb.booking_time, mtb.status, u.fullname AS patient_name, mtb.result_file, mtb.result_notes, vc.name AS hospital_name
         FROM medical_test_bookings mtb
         JOIN medical_tests mt ON mt.id = mtb.test_id
         JOIN users u ON u.id = mtb.user_id
         JOIN lab_technician_assignments lta ON lta.center_id = mt.center_id AND lta.technician_id = ? AND (lta.section_name IS NULL OR lta.section_name = mt.department)
         LEFT JOIN vaccination_centers vc ON vc.id = mt.center_id
         WHERE 1 = 1' . $labStatusClause . '
         ORDER BY mtb.booking_date DESC, mtb.created_at DESC'
    );
    $stmt->execute([$user_id]);
    $technician_bookings = $stmt->fetchAll();
} else {
    $technician_bookings = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Medical Tests - NHRE</title>
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
  <nav class="dashboard-nav">
    <div class="container d-flex align-items-center justify-content-between gap-3">
      <a class="navbar-brand d-flex align-items-center gap-2" href="dashboard.php">
        <img src="assets/images/nhre-logo.svg" alt="NHRE" class="nhre-logo-img">
      </a>
      <div class="d-flex gap-2">
        <a href="dashboard.php" class="btn btn-dashboard-logout ripple">
          <i class="fa-solid fa-house"></i>
          <span>Dashboard</span>
        </a>
        <a href="logout.php" class="btn btn-dashboard-logout ripple">
          <i class="fa-solid fa-arrow-right-from-bracket"></i>
          <span>Logout</span>
        </a>
      </div>
    </div>
  </nav>

  <main class="dashboard-main">
    <section class="container">
      <div class="dashboard-hero glass-card">
        <div>
          <span class="auth-kicker"><?= $technician_view ? 'Lab Technician Workspace' : 'Medical Tests Marketplace' ?></span>
          <h1><?= $technician_view ? 'Test Requests' : 'Book diagnostic tests for ' . e($fullname) ?></h1>
          <p><?= $technician_view ? 'Review only the requests assigned to your authorized hospitals and laboratory sections.' : 'Filter by pricing, location, test type, turnaround time, and book a diagnostic service directly from NHRE.' ?></p>
        </div>
        <div class="dashboard-user-pill">
          <i class="fa-solid fa-flask-vial"></i>
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

      <?php if (!$technician_view): ?>
      <div class="row g-4">
        <div class="col-lg-3">
          <article class="dashboard-card">
            <div class="dashboard-card-icon"><i class="fa-solid fa-filter"></i></div>
            <h2>Filters</h2>
            <form method="get" class="mt-3">
              <div class="mb-3">
                <label class="form-label">Min Price</label>
                <input type="number" class="form-control" name="min_price" value="<?= e($min_price) ?>" min="0" step="1">
              </div>
              <div class="mb-3">
                <label class="form-label">Max Price</label>
                <input type="number" class="form-control" name="max_price" value="<?= e($max_price) ?>" min="0" step="1">
              </div>
              <div class="mb-3 lab-location-picker">
                <label class="form-label">Division</label><select class="form-select js-division" name="division"><option value="">All divisions</option><?php foreach ($labDivisions as $option): ?><option value="<?= e($option) ?>" <?= $division === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?></select>
                <label class="form-label mt-2">City / district</label><select class="form-select js-district" name="district" <?= $division === '' ? 'disabled' : '' ?>><option value="">All cities / districts</option></select>
                <label class="form-label mt-2">Hospital</label><select class="form-select js-hospital" name="hospital_id" <?= $district === '' ? 'disabled' : '' ?>><option value="">All hospitals</option></select>
              </div>
              <div class="mb-3">
                <label class="form-label">Test Type</label>
                <select class="form-select" name="test_type">
                  <option value="">All Types</option>
                  <?php foreach ($types as $type_option): ?>
                    <option value="<?= e($type_option) ?>" <?= $test_type === $type_option ? 'selected' : '' ?>><?= e($type_option) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="mb-3">
                <label class="form-label">Result Time</label>
                <select class="form-select" name="result_time">
                  <option value="">Any</option>
                  <?php foreach ($result_times as $result_option): ?>
                    <option value="<?= e($result_option) ?>" <?= $result_time === $result_option ? 'selected' : '' ?>><?= e($result_option) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="mb-3">
                <label class="form-label">Availability</label>
                <select class="form-select" name="availability">
                  <option value="">Any</option>
                  <option value="available" <?= $availability === 'available' ? 'selected' : '' ?>>Available</option>
                  <option value="unavailable" <?= $availability === 'unavailable' ? 'selected' : '' ?>>Unavailable</option>
                </select>
              </div>
              <div class="mb-3">
                <label class="form-label">Home Collection</label>
                <select class="form-select" name="home_collection">
                  <option value="">Any</option>
                  <option value="yes" <?= $home_collection === 'yes' ? 'selected' : '' ?>>Yes</option>
                  <option value="no" <?= $home_collection === 'no' ? 'selected' : '' ?>>No</option>
                </select>
              </div>
              <button type="submit" class="btn btn-solid-nhre w-100">
                <i class="fa-solid fa-magnifying-glass"></i> Apply Filters
              </button>
            </form>
          </article>
        </div>

        <div class="col-lg-9">
          <div class="row g-4">
            <?php if ($tests): ?>
              <?php foreach ($tests as $test): ?>
                <div class="col-md-6">
                  <article class="dashboard-card medical-test-card">
                    <div class="d-flex justify-content-between align-items-start gap-3">
                      <div>
                        <h2><?= e($test['name']) ?></h2>
                        <p class="test-description"><?= e($test['description'] ?: 'Diagnostic service prepared by NHRE partner labs.') ?></p>
                      </div>
                      <span class="test-price">৳<?= e(number_format((float)$test['price'], 2)) ?></span>
                    </div>
                    <div class="test-meta-row">
                      <span class="test-chip"><i class="fa-solid fa-tag"></i><?= e($test['test_type']) ?></span>
                      <span class="test-chip"><i class="fa-solid fa-location-dot"></i><?= e($test['place']) ?></span>
                      <span class="test-chip"><i class="fa-solid fa-clock"></i><?= e($test['result_time']) ?></span>
                    </div>
                    <div class="test-meta-row">
                      <span class="test-chip"><i class="fa-solid fa-hospital"></i><?= e($test['department'] ?: 'General') ?></span>
                      <span class="test-chip"><i class="fa-solid fa-house"></i><?= $test['home_collection'] ? 'Home Collection' : 'Lab Visit' ?></span>
                      <span class="test-chip"><i class="fa-solid fa-circle-check"></i><?= (int)$test['availability'] === 1 ? 'Available' : 'Unavailable' ?></span>
                    </div>
                    <div class="test-actions">
                      <span class="text-muted small"><?= (int)$test['availability'] === 1 ? 'Open for booking' : 'Temporarily unavailable' ?></span>
                      <button type="button" class="btn btn-solid-nhre" data-bs-toggle="modal" data-bs-target="#bookModal<?= (int)$test['id'] ?>">
                        <i class="fa-solid fa-calendar-plus"></i> Book
                      </button>
                    </div>
                  </article>

                  <div class="modal fade vaccination-modal" id="bookModal<?= (int)$test['id'] ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                      <div class="modal-content">
                        <form method="post" action="auth/medical_test_process.php" enctype="multipart/form-data">
                          <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                          <input type="hidden" name="action" value="book_test">
                          <input type="hidden" name="test_id" value="<?= (int)$test['id'] ?>">
                          <div class="modal-header">
                            <h5 class="modal-title">Book <?= e($test['name']) ?></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                          </div>
                          <div class="modal-body">
                            <p class="text-muted">Price: <strong>৳<?= e(number_format((float)$test['price'], 2)) ?></strong></p>
                            <p class="text-muted">Place: <strong><?= e($test['place']) ?></strong></p>
                            <div class="mb-3">
                              <label class="form-label">Booking Date</label>
                              <input type="date" class="form-control" name="booking_date" required>
                            </div>
                            <div class="mb-3">
                              <label class="form-label">Preferred Time</label>
                              <input type="time" class="form-control" name="booking_time">
                            </div>
                            <div class="mb-3">
                              <label class="form-label">Notes</label>
                              <textarea class="form-control" name="notes" rows="3" placeholder="Add any instructions or support needs"></textarea>
                            </div>
                          </div>
                          <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-solid-nhre">Submit Booking</button>
                          </div>
                        </form>
                      </div>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <div class="col-12">
                <article class="dashboard-card">
                  <h2>No tests available</h2>
                  <p>No medical tests match the selected filters. Try broadening your search.</p>
                </article>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <section class="mt-5">
        <div class="dashboard-hero glass-card">
          <div>
            <span class="auth-kicker">My Bookings</span>
            <h1>Your booked and ongoing tests</h1>
            <p>View your test bookings, statuses, and any available results.</p>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-hover align-middle medical-test-table">
            <thead>
              <tr>
                <th>Test</th>
                <th>Place</th>
                <th>Date</th>
                <th>Status</th>
                <th>Result</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($bookings): ?>
                <?php foreach ($bookings as $booking): ?>
                  <tr>
                    <td><?= e($booking['test_name']) ?></td>
                    <td><?= e($booking['place']) ?></td>
                    <td><?= e($booking['booking_date']) ?></td>
                    <td><span class="badge bg-light text-dark"><?= e($booking['status']) ?></span></td>
                    <td>
                      <?php if (!empty($booking['result_file'])): ?>
                        <a href="<?= e($booking['result_file']) ?>" target="_blank" rel="noopener">View</a>
                      <?php else: ?>
                        <span class="text-muted">Pending</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="5" class="text-muted">You do not have any bookings yet.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>

      <?php endif; ?>
      <?php if ($technician_view): ?>
        <section class="mt-5">
          <div class="dashboard-hero glass-card">
            <div>
              <span class="auth-kicker">Lab Technician Management</span>
              <h1><?= $labView === 'reports' ? 'Laboratory Reports' : ($labView === 'history' ? 'Test History' : 'Manage test bookings') ?></h1>
              <p><?= $labView === 'reports' ? 'Review completed test reports for your authorized sections.' : ($labView === 'history' ? 'Review completed and cancelled requests for your authorized sections.' : 'Review patient bookings, update status, and upload results.') ?></p>
            </div>
          </div>

          <div class="table-responsive">
            <table class="table table-hover align-middle medical-test-table">
              <thead>
                <tr>
                  <th>Patient</th>
                  <th>Test</th>
                  <th>Date</th>
                  <th>Hospital / section</th>
                  <th>Status</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($technician_bookings as $booking): ?>
                  <tr>
                    <td><?= e($booking['patient_name']) ?></td>
                    <td><?= e($booking['test_name']) ?></td>
                    <td><?= e($booking['booking_date']) ?></td>
                    <td><?= e($booking['hospital_name'] ?: 'Unassigned') ?><br><small class="text-muted"><?= e($booking['department'] ?: 'General') ?></small></td>
                    <td><span class="badge bg-light text-dark"><?= e($booking['status']) ?></span></td>
                    <td>
                      <button class="btn btn-manage-booking btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#techModal<?= (int)$booking['id'] ?>">Manage</button>
                    </td>
                  </tr>

                  <div class="modal fade vaccination-modal" id="techModal<?= (int)$booking['id'] ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                      <div class="modal-content">
                        <form method="post" action="auth/medical_test_process.php" enctype="multipart/form-data">
                          <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                          <input type="hidden" name="action" value="update_booking">
                          <input type="hidden" name="booking_id" value="<?= (int)$booking['id'] ?>">
                          <div class="modal-header">
                            <h5 class="modal-title">Manage Booking</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                          </div>
                          <div class="modal-body">
                            <div class="mb-3">
                              <label class="form-label">Status</label>
                              <select class="form-select" name="status">
                                <option value="Pending" <?= $booking['status'] === 'Pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="Confirmed" <?= $booking['status'] === 'Confirmed' ? 'selected' : '' ?>>Confirmed</option>
                                <option value="Ongoing" <?= $booking['status'] === 'Ongoing' ? 'selected' : '' ?>>Ongoing</option>
                                <option value="Completed" <?= $booking['status'] === 'Completed' ? 'selected' : '' ?>>Completed</option>
                                <option value="Cancelled" <?= $booking['status'] === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                              </select>
                            </div>
                            <div class="mb-3">
                              <label class="form-label">Result Notes</label>
                              <textarea class="form-control" name="result_notes" rows="3" placeholder="Add result summary"></textarea>
                            </div>
                            <div class="mb-3">
                              <label class="form-label">Result File</label>
                              <input type="file" class="form-control" name="result_file">
                            </div>
                          </div>
                          <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-solid-nhre">Save</button>
                          </div>
                        </form>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </section>
      <?php endif; ?>
    </section>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    (() => {
      const hospitals = <?= json_encode($hospitalDirectory, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
      document.querySelectorAll('.lab-location-picker').forEach((picker) => {
        const div = picker.querySelector('.js-division'), dist = picker.querySelector('.js-district'), hospital = picker.querySelector('.js-hospital');
        const reset = (select, label) => { select.replaceChildren(new Option(`All ${label}`, '', true, true)); select.disabled = true; };
        const districtsForDivision = () => [...new Set(hospitals.filter(h => h.division === div.value).map(h => h.district))].sort();
        const loadDistricts = (selected = '') => { reset(dist, 'cities / districts'); districtsForDivision().forEach(value => dist.add(new Option(value, value, value === selected, value === selected))); dist.disabled = !div.value; };
        const loadHospitals = (selected = '') => { reset(hospital, 'hospitals'); hospitals.filter(h => h.division === div.value && h.district === dist.value).forEach(item => hospital.add(new Option(item.name, item.id, String(item.id) === String(selected), String(item.id) === String(selected)))); hospital.disabled = !dist.value; };
        div.addEventListener('change', () => { loadDistricts(); reset(hospital, 'hospitals'); });
        dist.addEventListener('change', () => loadHospitals());
        if (div.value) { loadDistricts(<?= json_encode($district) ?>); if (dist.value) loadHospitals(<?= (int)$hospitalId ?>); } else { reset(dist, 'cities / districts'); reset(hospital, 'hospitals'); }
      });
    })();
  </script>
  <script src="assets/js/app.js?v=20260818-10"></script>
</body>
</html>
