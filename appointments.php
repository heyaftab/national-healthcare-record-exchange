<?php
require_once __DIR__ . '/auth/auth_check.php';
require_role(['Patient', 'Doctor', 'Hospital Admin', 'System Admin']);
ensure_appointments_table_exists();

$fullname = $_SESSION['fullname'] ?? 'NHRE User';
$email = $_SESSION['email'] ?? '';
$role = $_SESSION['role'] ?? 'User';
$errors = session_pull('errors', []);
$success = session_pull('success');

$today = date('Y-m-d');
$appointment_statuses = ['Pending', 'Approved', 'Completed', 'Cancelled', 'Rejected'];
$doctor_list = [];
$doctor_list_all = [];
$selected_doctor = null;
$appointments = [];
$filter_patient = trim((string)($_GET['patient'] ?? ''));
$filter_doctor = (int)($_GET['doctor'] ?? 0);
$filter_status = trim((string)($_GET['status'] ?? ''));
$filter_date = trim((string)($_GET['date'] ?? ''));
$doctor_search = trim((string)($_GET['doctor_search'] ?? ''));
$doctor_district = trim((string)($_GET['doctor_district'] ?? ''));
$doctor_hospital = trim((string)($_GET['doctor_hospital'] ?? ''));
$doctor_specialization = trim((string)($_GET['doctor_specialization'] ?? ''));
$selected_doctor_id = (int)($_GET['doctor_id'] ?? 0);
$show_specialization_view = isset($_GET['view_specialization']) && $_GET['view_specialization'] === '1';
$current_user_district = '';
$adminHospitalId = 0;

/** Return a stable, distinct cartoon portrait for each doctor in the catalog. */
function doctor_cartoon_avatar(array $doctor): string
{
    return user_avatar_url(
        isset($doctor['profile_photo']) ? (string)$doctor['profile_photo'] : null,
        (int)($doctor['id'] ?? 0),
        'Doctor'
    );
}

try {
    ensure_doctor_profile_columns();
    ensure_doctor_catalog_tables();

    $stmt = db()->prepare('SELECT district FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([(int)($_SESSION['user_id'] ?? 0)]);
    $current_user_row = $stmt->fetch();
    if ($current_user_row) {
        $current_user_district = (string)($current_user_row['district'] ?? '');
    }

    if ($role === 'Patient') {
        $stmt = db()->prepare(
            'SELECT u.id, u.fullname, u.profile_photo, u.district, u.hospital_name, u.specialization, u.qualification, u.experience_years, u.consultation_fee, u.rating, u.reviews_count, u.address, u.bio, u.visiting_hours, u.awards, u.is_featured,
                    d.name AS district_name, h.name AS hospital_name_db, s.name AS specialization_name
             FROM users u
             LEFT JOIN districts d ON d.id = u.district_id
             LEFT JOIN hospitals h ON h.id = u.hospital_id
             LEFT JOIN specializations s ON s.id = u.specialization_id
             WHERE u.role = ?
             ORDER BY u.fullname ASC'
        );
        $stmt->execute(['Doctor']);
        $doctor_rows = $stmt->fetchAll();

        $doctor_list = array_map(function (array $doctor): array {
            $doctor['district'] = $doctor['district'] ?: ($doctor['district_name'] ?? '');
            $doctor['hospital_name'] = $doctor['hospital_name'] ?: ($doctor['hospital_name_db'] ?? '');
            $doctor['specialization'] = $doctor['specialization'] ?: ($doctor['specialization_name'] ?? '');
            return $doctor;
        }, $doctor_rows);

        $doctor_list_all = $doctor_list;

        $doctor_min_rating = (float)($_GET['doctor_min_rating'] ?? 0);
        $doctor_min_experience = (int)($_GET['doctor_min_experience'] ?? 0);
        $doctor_min_fee = (int)($_GET['doctor_min_fee'] ?? 0);
        $doctor_max_fee = (int)($_GET['doctor_max_fee'] ?? 0);

        if ($doctor_search !== '') {
            $doctor_list = array_values(array_filter($doctor_list, static function ($doctor) use ($doctor_search): bool {
                return strpos((string)($doctor['fullname'] ?? ''), $doctor_search) !== false
                    || strpos((string)($doctor['specialization'] ?? ''), $doctor_search) !== false
                    || strpos((string)($doctor['hospital_name'] ?? ''), $doctor_search) !== false
                    || strpos((string)($doctor['district'] ?? ''), $doctor_search) !== false;
            }));
        }
        if ($doctor_district !== '') {
            $doctor_list = array_values(array_filter($doctor_list, static function ($doctor) use ($doctor_district): bool {
                return (string)($doctor['district'] ?? '') === $doctor_district;
            }));
        }
        if ($doctor_hospital !== '') {
            $doctor_list = array_values(array_filter($doctor_list, static function ($doctor) use ($doctor_hospital): bool {
                return (string)($doctor['hospital_name'] ?? '') === $doctor_hospital;
            }));
        }
        if ($doctor_specialization !== '') {
            $doctor_list = array_values(array_filter($doctor_list, static function ($doctor) use ($doctor_specialization): bool {
                return (string)($doctor['specialization'] ?? '') === $doctor_specialization;
            }));
        }
        if ($doctor_min_rating > 0) {
            $doctor_list = array_values(array_filter($doctor_list, static function ($doctor) use ($doctor_min_rating): bool {
                return (float)($doctor['rating'] ?? 0) >= $doctor_min_rating;
            }));
        }
        if ($doctor_min_experience > 0) {
            $doctor_list = array_values(array_filter($doctor_list, static function ($doctor) use ($doctor_min_experience): bool {
                return (int)($doctor['experience_years'] ?? 0) >= $doctor_min_experience;
            }));
        }
        if ($doctor_min_fee > 0) {
            $doctor_list = array_values(array_filter($doctor_list, static function ($doctor) use ($doctor_min_fee): bool {
                return (int)($doctor['consultation_fee'] ?? 0) >= $doctor_min_fee;
            }));
        }
        if ($doctor_max_fee > 0) {
            $doctor_list = array_values(array_filter($doctor_list, static function ($doctor) use ($doctor_max_fee): bool {
                return (int)($doctor['consultation_fee'] ?? 0) <= $doctor_max_fee;
            }));
        }

        if ($selected_doctor_id > 0) {
            foreach ($doctor_list as $doctor) {
                if ((int)$doctor['id'] === $selected_doctor_id) {
                    $selected_doctor = $doctor;
                    break;
                }
            }
        }

        $doctor_reviews = [];
        $patient_review = null;
        if ($selected_doctor !== null) {
            $doctor_reviews = get_doctor_reviews((int)$selected_doctor['id'], 30);
            $patient_review = get_patient_review((int)$selected_doctor['id'], (int)($_SESSION['user_id'] ?? 0));
        }

        $featured_doctors = array_values(array_filter($doctor_rows, static function ($doctor) use ($current_user_district): bool {
            $rating = (float)($doctor['rating'] ?? 0);
            $reviews = (int)($doctor['reviews_count'] ?? 0);
            $sameDistrict = $current_user_district !== '' && (string)($doctor['district'] ?? '') === $current_user_district;
            return $rating >= 4.4 && ($sameDistrict || $reviews >= 20);
        }));

        usort($featured_doctors, static function ($a, $b): int {
            $ratingDiff = ((float)($b['rating'] ?? 0) - (float)($a['rating'] ?? 0));
            if ($ratingDiff !== 0.0) {
                return $ratingDiff > 0 ? 1 : -1;
            }
            $reviewDiff = ((int)($b['reviews_count'] ?? 0) - (int)($a['reviews_count'] ?? 0));
            if ($reviewDiff !== 0) {
                return $reviewDiff > 0 ? 1 : -1;
            }
            return ((int)($b['experience_years'] ?? 0) - (int)($a['experience_years'] ?? 0));
        });
        $featured_doctors = array_slice($featured_doctors, 0, 3);
        foreach ($featured_doctors as &$featured) {
            $featured['district'] = $featured['district'] ?: ($featured['district_name'] ?? '');
            $featured['hospital_name'] = $featured['hospital_name'] ?: ($featured['hospital_name_db'] ?? '');
            $featured['specialization'] = $featured['specialization'] ?: ($featured['specialization_name'] ?? '');
        }
        unset($featured);

        if ($featured_doctors === []) {
            $featured_doctors = array_slice($doctor_rows, 0, 3);
        }

        $stmt = db()->prepare(
            'SELECT a.appointment_id, a.appointment_date, a.appointment_time, a.reason, a.status, a.doctor_notes, u.fullname AS doctor_name
             FROM appointments a
             JOIN users u ON u.id = a.doctor_id
             WHERE a.patient_id = ?
             ORDER BY a.appointment_date ASC, a.appointment_time ASC'
        );
        $stmt->execute([(int)($_SESSION['user_id'] ?? 0)]);
        $appointments = $stmt->fetchAll();
    } elseif ($role === 'Doctor') {
        $stmt = db()->prepare(
            'SELECT a.appointment_id, a.appointment_date, a.appointment_time, a.reason, a.status, a.doctor_notes, u.fullname AS patient_name
             FROM appointments a
             JOIN users u ON u.id = a.patient_id
             WHERE a.doctor_id = ?
             ORDER BY a.appointment_date ASC, a.appointment_time ASC'
        );
        $stmt->execute([(int)($_SESSION['user_id'] ?? 0)]);
        $appointments = $stmt->fetchAll();
    } elseif ($role === 'Hospital Admin') {
        $scopeStmt = db()->prepare('SELECT hospital_id FROM users WHERE id = ? LIMIT 1');
        $scopeStmt->execute([(int)$_SESSION['user_id']]);
        $adminHospitalId = (int)$scopeStmt->fetchColumn();
        if ($adminHospitalId <= 0) {
            throw new RuntimeException('Your administrator account is not assigned to a hospital.');
        }
        $stmt = db()->prepare('SELECT id, fullname FROM users WHERE role = ? AND hospital_id = ? ORDER BY fullname ASC');
        $stmt->execute(['Doctor', $adminHospitalId]);
        $doctor_list = $stmt->fetchAll();

        $sql = 'SELECT a.appointment_id, a.appointment_date, a.appointment_time, a.reason, a.status, a.doctor_notes, pd.fullname AS patient_name, dd.fullname AS doctor_name
                FROM appointments a
                JOIN users pd ON pd.id = a.patient_id
                JOIN users dd ON dd.id = a.doctor_id
                WHERE dd.hospital_id = ?';
        $conditions = [];
        $params = [$adminHospitalId];

        if ($filter_patient !== '') {
            $conditions[] = 'pd.fullname LIKE ?';
            $params[] = '%' . $filter_patient . '%';
        }
        if ($filter_doctor > 0) {
            $conditions[] = 'dd.id = ?';
            $params[] = $filter_doctor;
        }
        if (in_array($filter_status, $appointment_statuses, true)) {
            $conditions[] = 'a.status = ?';
            $params[] = $filter_status;
        }
        if ($filter_date !== '' && DateTimeImmutable::createFromFormat('Y-m-d', $filter_date) !== false) {
            $conditions[] = 'a.appointment_date = ?';
            $params[] = $filter_date;
        }
        if ($conditions) {
            $sql .= ' AND ' . implode(' AND ', $conditions);
        }
        $sql .= ' ORDER BY a.appointment_date ASC, a.appointment_time ASC';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        $appointments = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    $errors[] = 'Unable to load appointment data. Please try again later.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Appointments - NHRE</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="assets/css/styles.css?v=20260811-16">
</head>
<body class="dashboard-body">
  <?php require __DIR__ . '/includes/sidebar.php'; ?>
  <?php require __DIR__ . '/includes/topnav.php'; ?>

  <main class="dashboard-main">
    <section class="container">
      <div class="dashboard-hero glass-card">
        <div>
          <span class="auth-kicker">Appointment Workspace</span>
          <h1>Book care, review requests, and manage follow-ups</h1>
          <p>Use this workspace to search doctors, book appointments, and keep your care timeline organized.</p>
        </div>
        <div class="dashboard-user-pill">
          <i class="fa-solid fa-calendar-check"></i>
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

      <?php if ($role === 'Patient'): ?>
        <article class="dashboard-card mt-4" id="current-appointments">
          <div class="d-flex align-items-center justify-content-between gap-3"><div><div class="dashboard-card-icon"><i class="fa-solid fa-calendar-day"></i></div><h2>Ongoing / Current Appointments</h2><p class="text-muted mb-0">Approved, pending, and upcoming care appears here before the doctor directory.</p></div><a href="#my-appointments" class="btn btn-outline-nhre">View all appointments</a></div>
          <div class="row g-3 mt-1">
            <?php $currentAppointments = array_values(array_filter($appointments, static fn(array $a): bool => in_array($a['status'], ['Pending', 'Approved'], true) || $a['appointment_date'] >= date('Y-m-d'))); $currentAppointments = array_slice($currentAppointments, 0, 4); ?>
            <?php foreach ($currentAppointments as $appointment): ?>
              <div class="col-md-6 col-xl-3"><div class="border rounded p-3 h-100"><strong><?= e($appointment['doctor_name']) ?></strong><div class="small text-muted">#<?= e($appointment['appointment_id']) ?> · <?= e($appointment['appointment_date']) ?> <?= e(substr($appointment['appointment_time'], 0, 5)) ?></div><span class="badge mt-2 <?= $appointment['status'] === 'Approved' ? 'bg-success' : 'bg-warning text-dark' ?>"><?= e($appointment['status']) ?></span><div class="mt-2"><a class="btn btn-sm btn-outline-primary" href="#my-appointments">View</a></div></div></div>
            <?php endforeach; ?>
            <?php if (!$currentAppointments): ?><div class="col-12 text-muted">No current appointments. Choose a doctor below to request care.</div><?php endif; ?>
          </div>
        </article>
        <div class="row g-4 mt-3">
          <div class="col-lg-5">
            <article class="dashboard-card">
              <div class="dashboard-card-icon"><i class="fa-solid fa-magnifying-glass"></i></div>
              <h2>Find a doctor</h2>
              <form action="appointments.php#appointments" method="GET" class="mt-3">
                <input type="hidden" name="doctor_id" value="<?= e($selected_doctor_id) ?>">
                <div class="mb-3">
                  <label class="form-label">Search doctor</label>
                  <input type="text" class="form-control" name="doctor_search" value="<?= e($doctor_search) ?>" placeholder="Name, specialty, hospital or district">
                </div>
                <div class="mb-3">
                  <label class="form-label">District</label>
                  <select class="form-select" name="doctor_district">
                    <option value="">All districts</option>
                    <?php $districts = array_values(array_unique(array_filter(array_map(function ($doctor) { return (string)($doctor['district'] ?? ''); }, $doctor_list_all)))); sort($districts); foreach ($districts as $district): ?>
                      <option value="<?= e($district) ?>" <?= $doctor_district === $district ? 'selected' : '' ?>><?= e($district) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="mb-3">
                  <label class="form-label">Hospital</label>
                  <select class="form-select" name="doctor_hospital">
                    <option value="">All hospitals</option>
                    <?php $hospitals = array_values(array_unique(array_filter(array_map(function ($doctor) { return (string)($doctor['hospital_name'] ?? ''); }, $doctor_list_all)))); sort($hospitals); foreach ($hospitals as $hospital): ?>
                      <option value="<?= e($hospital) ?>" <?= $doctor_hospital === $hospital ? 'selected' : '' ?>><?= e($hospital) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="mb-3">
                  <label class="form-label">Specialization</label>
                  <select class="form-select" name="doctor_specialization">
                    <option value="">All specializations</option>
                    <?php $specials = array_values(array_unique(array_filter(array_map(function ($doctor) { return (string)($doctor['specialization'] ?? ''); }, $doctor_list_all)))); sort($specials); foreach ($specials as $special): ?>
                      <option value="<?= e($special) ?>" <?= $doctor_specialization === $special ? 'selected' : '' ?>><?= e($special) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="row g-2">
                  <div class="col-6">
                    <label class="form-label">Min rating</label>
                    <select class="form-select" name="doctor_min_rating">
                      <option value="0">Any rating</option>
                      <option value="4.5" <?= (float)($doctor_min_rating ?? 0) === 4.5 ? 'selected' : '' ?>>4.5+</option>
                      <option value="4.0" <?= (float)($doctor_min_rating ?? 0) === 4.0 ? 'selected' : '' ?>>4.0+</option>
                      <option value="3.5" <?= (float)($doctor_min_rating ?? 0) === 3.5 ? 'selected' : '' ?>>3.5+</option>
                    </select>
                  </div>
                  <div class="col-6">
                    <label class="form-label">Min experience</label>
                    <select class="form-select" name="doctor_min_experience">
                      <option value="0">Any</option>
                      <option value="5" <?= (int)($doctor_min_experience ?? 0) === 5 ? 'selected' : '' ?>>5+ years</option>
                      <option value="10" <?= (int)($doctor_min_experience ?? 0) === 10 ? 'selected' : '' ?>>10+ years</option>
                      <option value="15" <?= (int)($doctor_min_experience ?? 0) === 15 ? 'selected' : '' ?>>15+ years</option>
                    </select>
                  </div>
                </div>
                <div class="d-flex gap-2 mt-3">
                  <button type="submit" class="btn btn-solid-nhre flex-grow-1">Apply filters</button>
                  <a href="appointments.php" class="btn btn-outline-secondary">Clear</a>
                </div>
              </form>
              <div class="mt-3 text-muted small">Showing <?= count($doctor_list) ?> doctor(s).</div>
            </article>
          </div>

          <div class="col-lg-7">
            <article class="dashboard-card" id="appointments">
              <div class="dashboard-card-icon"><i class="fa-solid fa-user-doctor"></i></div>
              <h2>Recommended doctors</h2>
              <div class="d-flex justify-content-between align-items-center mt-3">
                <p class="mb-0 text-muted">Highlighted doctors are selected for strong ratings and location relevance.</p>
                <a href="appointments.php?view_specialization=1#doctor-results" class="btn btn-outline-secondary btn-sm">View all by specialization</a>
              </div>
              <div class="row g-3 mt-2">
                <?php if ($featured_doctors): ?>
                  <?php foreach ($featured_doctors as $featured): ?>
                    <div class="col-md-4">
                      <div class="border rounded p-3 h-100">
                        <div class="doctor-card-heading">
                          <img class="doctor-cartoon-avatar" src="<?= e(doctor_cartoon_avatar($featured)) ?>" alt="Cartoon portrait of <?= e($featured['fullname']) ?>">
                          <div>
                            <div class="fw-semibold"><?= e($featured['fullname']) ?></div>
                            <div class="text-muted small"><?= e($featured['specialization'] ?: 'General Physician') ?></div>
                          </div>
                        </div>
                        <div class="text-muted small"><?= render_rating_stars((float)($featured['rating'] ?? 0)) ?> <strong><?= e(round((float)($featured['rating'] ?? 0), 1)) ?></strong> (<?= e($featured['reviews_count'] ?? '0') ?> reviews)</div>
                        <div class="text-muted small"><?= e($featured['hospital_name'] ?: 'Hospital not listed') ?></div>
                        <div class="text-muted small"><?= e($featured['district'] ?: 'Location not listed') ?></div>
                        <div class="text-muted small">Fee: <?= e($featured['consultation_fee'] ?? 'N/A') ?></div>
                        <a href="appointments.php?doctor_id=<?= e($featured['id']) ?>#doctor-profile" class="btn btn-outline-primary btn-sm mt-2">View profile</a>
                      </div>
                    </div>
                  <?php endforeach; ?>
                <?php else: ?>
                  <div class="col-12 text-muted">No featured doctors are available right now.</div>
                <?php endif; ?>
              </div>
            </article>
          </div>
        </div>

        <div class="row g-4 mt-3">
          <div class="col-12">
            <article class="dashboard-card" id="doctor-results">
              <div class="dashboard-card-icon"><i class="fa-solid fa-list"></i></div>
              <h2>Doctor results</h2>
              <div class="list-group mt-3">
                <?php if ($doctor_list): ?>
                  <?php foreach ($doctor_list as $doctor): ?>
                    <div class="list-group-item d-flex flex-column flex-lg-row justify-content-between gap-3">
                      <div class="doctor-result-info">
                        <img class="doctor-cartoon-avatar doctor-cartoon-avatar--result" src="<?= e(doctor_cartoon_avatar($doctor)) ?>" alt="Cartoon portrait of <?= e($doctor['fullname']) ?>">
                        <div>
                          <div class="fw-semibold"><?= e($doctor['fullname']) ?></div>
                        <div class="text-muted small"><?= e($doctor['specialization'] ?: 'General Physician') ?></div>
                        <div class="text-muted small"><?= e($doctor['qualification'] ?: 'Qualified doctor') ?></div>
                        <div class="text-muted small"><?= e($doctor['hospital_name'] ?: 'Hospital not listed') ?> • <?= e($doctor['district'] ?: 'Location not listed') ?></div>
                        <div class="text-muted small">Experience: <?= e($doctor['experience_years'] ?? 'N/A') ?> years • Fee: <?= e($doctor['consultation_fee'] ?? 'N/A') ?></div>
                        </div>
                      </div>
                      <div class="d-flex gap-2 align-items-start">
                        <a href="appointments.php?doctor_id=<?= e($doctor['id']) ?>#doctor-profile" class="btn btn-outline-primary btn-sm">View profile</a>
                        <a href="appointments.php?doctor_id=<?= e($doctor['id']) ?>#doctor-profile" class="btn btn-solid-nhre btn-sm">Book</a>
                      </div>
                    </div>
                  <?php endforeach; ?>
                <?php else: ?>
                  <div class="text-muted">No doctors found for the current filters.</div>
                <?php endif; ?>
              </div>
            </article>
          </div>
        </div>

        <?php if ($show_specialization_view): ?>
          <div class="row g-4 mt-3">
            <div class="col-12">
              <article class="dashboard-card">
                <div class="dashboard-card-icon"><i class="fa-solid fa-tag"></i></div>
                <h2>Doctors by specialization</h2>
                <div class="mt-3">
                  <?php $specializationGroups = []; foreach ($doctor_list as $doctor) { $specializationGroups[(string)($doctor['specialization'] ?: 'General Physician')][] = $doctor; } ksort($specializationGroups); foreach ($specializationGroups as $specialization => $group): ?>
                    <div class="border rounded p-3 mb-3">
                      <h5 class="mb-3"><?= e($specialization) ?></h5>
                      <div class="list-group">
                        <?php foreach ($group as $doctor): ?>
                          <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div class="doctor-result-info">
                              <img class="doctor-cartoon-avatar doctor-cartoon-avatar--small" src="<?= e(doctor_cartoon_avatar($doctor)) ?>" alt="Cartoon portrait of <?= e($doctor['fullname']) ?>">
                              <div>
                                <div class="fw-semibold"><?= e($doctor['fullname']) ?></div>
                        <div class="text-muted small"><?= render_rating_stars((float)($doctor['rating'] ?? 0)) ?> <strong><?= e(round((float)($doctor['rating'] ?? 0), 1)) ?></strong> (<?= e($doctor['reviews_count'] ?? '0') ?> reviews)</div>
                        <div class="text-muted small"><?= e($doctor['hospital_name'] ?: 'Hospital not listed') ?> • <?= e($doctor['district'] ?: 'Location not listed') ?></div>
                              </div>
                            </div>
                            <a href="appointments.php?doctor_id=<?= e($doctor['id']) ?>#doctor-profile" class="btn btn-outline-primary btn-sm">View profile</a>
                          </div>
                        <?php endforeach; ?>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              </article>
            </div>
          </div>
        <?php endif; ?>

        <div class="row g-4 mt-3">
          <div class="col-12">
            <article class="dashboard-card" id="doctor-profile">
              <div class="dashboard-card-icon"><i class="fa-solid fa-notes-medical"></i></div>
              <h2>Doctor profile</h2>
              <?php if ($selected_doctor): ?>
                <div class="row g-4 mt-2">
                  <div class="col-lg-7">
                    <div class="doctor-profile-heading">
                      <img class="doctor-cartoon-avatar doctor-cartoon-avatar--profile" src="<?= e(doctor_cartoon_avatar($selected_doctor)) ?>" alt="Cartoon portrait of <?= e($selected_doctor['fullname']) ?>">
                      <div>
                        <h4><?= e($selected_doctor['fullname']) ?></h4>
                        <p class="mb-2"><strong><?= e($selected_doctor['specialization'] ?: 'General Physician') ?></strong></p>
                      </div>
                    </div>
                    <p class="mb-2">Qualification: <?= e($selected_doctor['qualification'] ?: 'Not listed') ?></p>
                    <p class="mb-2">Hospital: <?= e($selected_doctor['hospital_name'] ?: 'Not listed') ?></p>
                    <p class="mb-2">Location: <?= e($selected_doctor['district'] ?: 'Not listed') ?> • <?= e($selected_doctor['address'] ?: 'Address not listed') ?></p>
                    <p class="mb-2">Experience: <?= e($selected_doctor['experience_years'] ?? 'N/A') ?> years</p>
                    <p class="mb-2">Consultation Fee: <?= e($selected_doctor['consultation_fee'] ?? 'N/A') ?></p>
                    <p class="mb-2">Rating: <?= render_rating_stars((float)($selected_doctor['rating'] ?? 0)) ?> <strong><?= e(round((float)($selected_doctor['rating'] ?? 0), 1)) ?></strong> (<?= e($selected_doctor['reviews_count'] ?? '0') ?> reviews)</p>
                    <p class="mb-2">Visiting Hours: <?= e($selected_doctor['visiting_hours'] ?: 'Daily clinic availability shared on request') ?></p>
                    <?php if (!empty($selected_doctor['bio'])): ?><p class="mb-2">Bio: <?= e($selected_doctor['bio']) ?></p><?php endif; ?>
                    <?php if (!empty($selected_doctor['awards'])): ?><p class="mb-2">Awards: <?= e($selected_doctor['awards']) ?></p><?php endif; ?>
                  </div>
                  <div class="col-lg-5">
                    <form action="auth/appointment_book_process.php" method="POST">
                      <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                      <input type="hidden" name="doctor_id" value="<?= e($selected_doctor['id']) ?>">
                      <div class="mb-3">
                        <label class="form-label">Appointment Date</label>
                        <input type="date" class="form-control" name="appointment_date" min="<?= e($today) ?>" required>
                      </div>
                      <div class="mb-3">
                        <label class="form-label">Appointment Time</label>
                        <select class="form-select" name="appointment_time" required>
                          <?php foreach (get_doctor_time_slots((int)$selected_doctor['id'], null, (string)($selected_doctor['visiting_hours'] ?? '')) as $slot): ?>
                            <option value="<?= e($slot) ?>"><?= e($slot) ?></option>
                          <?php endforeach; ?>
                        </select>
                      </div>
                      <div class="mb-3">
                        <label class="form-label">Reason for Visit</label>
                        <textarea class="form-control" name="reason" rows="4" maxlength="1000" required></textarea>
                      </div>
                      <button type="submit" class="btn btn-solid-nhre w-100">Book Appointment</button>
                    </form>
                  </div>
                </div>

                <hr class="my-4">
                <div class="row g-4">
                  <div class="col-lg-6">
                    <h4><i class="fa-solid fa-star text-warning me-1"></i>Patient reviews</h4>
                    <p class="text-muted">
                      Average rating
                      <strong><?= e(round((float)($selected_doctor['rating'] ?? 0), 1)) ?></strong> / 5
                      from <?= e($selected_doctor['reviews_count'] ?? 0) ?> review(s).
                    </p>
                    <?php if ($doctor_reviews): ?>
                      <?php foreach ($doctor_reviews as $review): ?>
                        <div class="review-item">
                          <div class="review-head">
                            <span class="sidebar-avatar"><?= e(strtoupper(mb_substr(trim((string)$review['patient_name']), 0, 1))) ?></span>
                            <div>
                              <strong><?= e($review['patient_name']) ?></strong>
                              <div class="d-flex align-items-center gap-2">
                                <?= render_rating_stars((float)$review['rating']) ?>
                                <small class="text-muted"><?= e(date('j M Y', strtotime((string)$review['updated_at']))) ?></small>
                              </div>
                            </div>
                          </div>
                          <?php if (!empty($review['review'])): ?>
                            <p class="review-text mb-0"><?= e($review['review']) ?></p>
                          <?php endif; ?>
                        </div>
                      <?php endforeach; ?>
                    <?php else: ?>
                      <p class="text-muted">No written reviews yet. Be the first to share your experience.</p>
                    <?php endif; ?>
                  </div>
                  <div class="col-lg-6">
                    <h4><i class="fa-solid fa-pen-nib me-1"></i><?= $patient_review ? 'Update your review' : 'Rate this doctor' ?></h4>
                    <form action="auth/doctor_review_process.php" method="POST" class="mt-3">
                      <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                      <input type="hidden" name="doctor_id" value="<?= e($selected_doctor['id']) ?>">
                      <div class="mb-3">
                        <label class="form-label d-block">Your rating</label>
                        <div class="star-picker">
                          <input type="radio" id="star-5" name="rating" value="5" <?= ($patient_review['rating'] ?? 0) == 5 ? 'checked' : '' ?>>
                          <label for="star-5" title="5 - Excellent"><i class="fa-solid fa-star"></i></label>
                          <input type="radio" id="star-4" name="rating" value="4" <?= ($patient_review['rating'] ?? 0) == 4 ? 'checked' : '' ?>>
                          <label for="star-4" title="4 - Good"><i class="fa-solid fa-star"></i></label>
                          <input type="radio" id="star-3" name="rating" value="3" <?= ($patient_review['rating'] ?? 0) == 3 ? 'checked' : '' ?>>
                          <label for="star-3" title="3 - Average"><i class="fa-solid fa-star"></i></label>
                          <input type="radio" id="star-2" name="rating" value="2" <?= ($patient_review['rating'] ?? 0) == 2 ? 'checked' : '' ?>>
                          <label for="star-2" title="2 - Poor"><i class="fa-solid fa-star"></i></label>
                          <input type="radio" id="star-1" name="rating" value="1" <?= ($patient_review['rating'] ?? 0) == 1 ? 'checked' : '' ?>>
                          <label for="star-1" title="1 - Very poor"><i class="fa-solid fa-star"></i></label>
                        </div>
                      </div>
                      <div class="mb-3">
                        <label class="form-label" for="review-text">Your review <span class="text-muted">(optional)</span></label>
                        <textarea class="form-control" id="review-text" name="review" rows="4" maxlength="1000" placeholder="Share your experience with this doctor."><?= e((string)($patient_review['review'] ?? '')) ?></textarea>
                      </div>
                      <button type="submit" class="btn btn-solid-nhre w-100">
                        <i class="fa-solid fa-paper-plane me-1"></i><?= $patient_review ? 'Update review' : 'Submit review' ?>
                      </button>
                      <?php if ($patient_review): ?>
                        <small class="text-muted d-block mt-2">You already reviewed this doctor. Submitting again will update your rating.</small>
                      <?php endif; ?>
                    </form>
                  </div>
                </div>
              <?php else: ?>
                <div class="text-muted">Select a doctor from the results to view their profile and book an appointment.</div>
              <?php endif; ?>
            </article>
          </div>
        </div>

        <div class="row g-4 mt-3">
          <div class="col-12">
            <article class="dashboard-card" id="my-appointments">
              <div class="dashboard-card-icon"><i class="fa-solid fa-clock"></i></div>
              <h2>My appointments</h2>
              <div class="table-responsive mt-3">
                <table class="table table-hover align-middle">
                  <thead>
                    <tr>
                      <th>ID</th>
                      <th>Doctor</th>
                      <th>Date</th>
                      <th>Time</th>
                      <th>Reason</th>
                      <th>Status</th>
                      <th>Doctor Notes</th>
                      <th>Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if ($appointments): ?>
                      <?php foreach ($appointments as $appointment): ?>
                        <tr>
                          <td><?= e($appointment['appointment_id']) ?></td>
                          <td><?= e($appointment['doctor_name']) ?></td>
                          <td><?= e($appointment['appointment_date']) ?></td>
                          <td><?= e($appointment['appointment_time']) ?></td>
                          <td><?= e($appointment['reason']) ?></td>
                          <td><span class="badge <?= $appointment['status'] === 'Pending' ? 'bg-warning-subtle text-warning-emphasis' : ($appointment['status'] === 'Approved' ? 'bg-info-subtle text-info-emphasis' : ($appointment['status'] === 'Completed' ? 'bg-success-subtle text-success-emphasis' : ($appointment['status'] === 'Cancelled' ? 'bg-danger-subtle text-danger-emphasis' : 'bg-secondary-subtle text-secondary-emphasis'))) ?>"><?= e($appointment['status']) ?></span></td>
                          <td><?= e($appointment['doctor_notes']) ?></td>
                          <td>
                            <?php if (in_array($appointment['status'], ['Pending', 'Approved'], true)): ?>
                              <form action="auth/appointment_cancel_process.php" method="POST" class="d-inline">
                                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                                <input type="hidden" name="appointment_id" value="<?= e($appointment['appointment_id']) ?>">
                                <button type="submit" class="btn btn-outline-danger btn-sm">Cancel</button>
                              </form>
                            <?php else: ?>
                              <span class="text-muted">N/A</span>
                            <?php endif; ?>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    <?php else: ?>
                      <tr><td colspan="8" class="text-center text-muted">No appointments found.</td></tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </article>
          </div>
        </div>
      <?php elseif ($role === 'Doctor'): ?>
        <div class="row g-4 mt-3">
          <div class="col-12">
            <article class="dashboard-card">
              <div class="dashboard-card-icon"><i class="fa-solid fa-stethoscope"></i></div>
              <h2>Assigned appointments</h2>
              <div class="table-responsive mt-3">
                <table class="table table-hover align-middle">
                  <thead>
                    <tr>
                      <th>ID</th>
                      <th>Patient</th>
                      <th>Date</th>
                      <th>Time</th>
                      <th>Reason</th>
                      <th>Status</th>
                      <th>Doctor Notes</th>
                      <th>Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if ($appointments): ?>
                      <?php foreach ($appointments as $appointment): ?>
                        <tr>
                          <td><?= e($appointment['appointment_id']) ?></td>
                          <td><?= e($appointment['patient_name']) ?></td>
                          <td><?= e($appointment['appointment_date']) ?></td>
                          <td><?= e($appointment['appointment_time']) ?></td>
                          <td><?= e($appointment['reason']) ?></td>
                          <td><span class="badge <?= $appointment['status'] === 'Pending' ? 'bg-warning-subtle text-warning-emphasis' : ($appointment['status'] === 'Approved' ? 'bg-info-subtle text-info-emphasis' : ($appointment['status'] === 'Completed' ? 'bg-success-subtle text-success-emphasis' : ($appointment['status'] === 'Cancelled' ? 'bg-danger-subtle text-danger-emphasis' : 'bg-secondary-subtle text-secondary-emphasis'))) ?>"><?= e($appointment['status']) ?></span></td>
                          <td>
                            <form action="auth/appointment_update_process.php" method="POST" class="d-flex flex-column gap-2">
                              <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                              <input type="hidden" name="appointment_id" value="<?= e($appointment['appointment_id']) ?>">
                              <textarea class="form-control form-control-sm" name="doctor_notes" rows="2" placeholder="Add notes"><?= e($appointment['doctor_notes']) ?></textarea>
                              <div class="d-flex gap-2 flex-wrap">
                                <button type="submit" name="status" value="Approved" class="btn btn-outline-success btn-sm">Approve</button>
                                <button type="submit" name="status" value="Rejected" class="btn btn-outline-danger btn-sm">Reject</button>
                                <button type="submit" name="status" value="Completed" class="btn btn-outline-primary btn-sm">Complete</button>
                                <button type="submit" name="status" value="" class="btn btn-outline-secondary btn-sm">Save notes</button>
                              </div>
                            </form>
                          </td>
                          <td></td>
                        </tr>
                      <?php endforeach; ?>
                    <?php else: ?>
                      <tr><td colspan="8" class="text-center text-muted">No appointments assigned to you.</td></tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </article>
          </div>
        </div>
      <?php elseif ($role === 'Hospital Admin'): ?>
        <div class="row g-4 mt-3">
          <div class="col-12">
            <article class="dashboard-card">
              <div class="dashboard-card-icon"><i class="fa-solid fa-hospital"></i></div>
              <h2>All appointments</h2>
              <form action="appointments.php" method="GET" class="row g-3 align-items-end mt-3">
                <div class="col-md-3">
                  <label class="form-label">Patient</label>
                  <input type="text" class="form-control" name="patient" value="<?= e($filter_patient) ?>" placeholder="Patient name">
                </div>
                <div class="col-md-3">
                  <label class="form-label">Doctor</label>
                  <select class="form-select" name="doctor">
                    <option value="0">All doctors</option>
                    <?php foreach ($doctor_list as $doctor): ?>
                      <option value="<?= e($doctor['id']) ?>" <?= $filter_doctor === (int)$doctor['id'] ? 'selected' : '' ?>><?= e($doctor['fullname']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-2">
                  <label class="form-label">Status</label>
                  <select class="form-select" name="status">
                    <option value="">All statuses</option>
                    <?php foreach ($appointment_statuses as $statusOption): ?>
                      <option value="<?= e($statusOption) ?>" <?= $filter_status === $statusOption ? 'selected' : '' ?>><?= e($statusOption) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-2">
                  <label class="form-label">Date</label>
                  <input type="date" class="form-control" name="date" value="<?= e($filter_date) ?>">
                </div>
                <div class="col-md-2">
                  <button type="submit" class="btn btn-solid-nhre w-100">Apply filter</button>
                </div>
              </form>
              <div class="table-responsive mt-3">
                <table class="table table-hover align-middle">
                  <thead>
                    <tr>
                      <th>ID</th>
                      <th>Patient</th>
                      <th>Doctor</th>
                      <th>Date</th>
                      <th>Time</th>
                      <th>Status</th>
                      <th>Doctor Notes</th>
                      <th>Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if ($appointments): ?>
                      <?php foreach ($appointments as $appointment): ?>
                        <tr>
                          <td><?= e($appointment['appointment_id']) ?></td>
                          <td><?= e($appointment['patient_name']) ?></td>
                          <td><?= e($appointment['doctor_name']) ?></td>
                          <td><?= e($appointment['appointment_date']) ?></td>
                          <td><?= e($appointment['appointment_time']) ?></td>
                          <td><span class="badge <?= $appointment['status'] === 'Pending' ? 'bg-warning-subtle text-warning-emphasis' : ($appointment['status'] === 'Approved' ? 'bg-info-subtle text-info-emphasis' : ($appointment['status'] === 'Completed' ? 'bg-success-subtle text-success-emphasis' : ($appointment['status'] === 'Cancelled' ? 'bg-danger-subtle text-danger-emphasis' : 'bg-secondary-subtle text-secondary-emphasis'))) ?>"><?= e($appointment['status']) ?></span></td>
                          <td>
                            <form action="auth/appointment_update_process.php" method="POST" class="d-flex flex-column gap-2">
                              <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                              <input type="hidden" name="appointment_id" value="<?= e($appointment['appointment_id']) ?>">
                              <textarea class="form-control form-control-sm" name="doctor_notes" rows="2" placeholder="Doctor notes"><?= e($appointment['doctor_notes']) ?></textarea>
                              <input type="hidden" name="status" value="<?= e($appointment['status']) ?>">
                              <button type="submit" name="action" value="update" class="btn btn-outline-primary btn-sm">Save notes</button>
                            </form>
                          </td>
                          <td class="d-flex gap-2 flex-wrap">
                            <form action="auth/appointment_update_process.php" method="POST">
                              <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                              <input type="hidden" name="appointment_id" value="<?= e($appointment['appointment_id']) ?>">
                              <input type="hidden" name="status" value="Approved">
                              <button type="submit" name="action" value="update" class="btn btn-outline-success btn-sm">Approve</button>
                            </form>
                            <form action="auth/appointment_update_process.php" method="POST">
                              <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                              <input type="hidden" name="appointment_id" value="<?= e($appointment['appointment_id']) ?>">
                              <input type="hidden" name="status" value="Rejected">
                              <button type="submit" name="action" value="update" class="btn btn-outline-danger btn-sm">Reject</button>
                            </form>
                            <form action="auth/appointment_update_process.php" method="POST">
                              <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                              <input type="hidden" name="appointment_id" value="<?= e($appointment['appointment_id']) ?>">
                              <input type="hidden" name="status" value="Completed">
                              <button type="submit" name="action" value="update" class="btn btn-outline-secondary btn-sm">Complete</button>
                            </form>
                            <form action="auth/appointment_update_process.php" method="POST">
                              <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                              <input type="hidden" name="appointment_id" value="<?= e($appointment['appointment_id']) ?>">
                              <button type="submit" name="action" value="delete" class="btn btn-outline-danger btn-sm">Delete</button>
                            </form>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    <?php else: ?>
                      <tr><td colspan="8" class="text-center text-muted">No appointments found.</td></tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </article>
          </div>
        </div>
      <?php else: ?>
        <div class="row g-4 mt-3">
          <div class="col-12">
            <article class="dashboard-card">
              <div class="dashboard-card-icon"><i class="fa-solid fa-ban"></i></div>
              <h2>Appointment management</h2>
              <p>Appointment management is not available for <?= e($role) ?>s.</p>
            </article>
          </div>
        </div>
      <?php endif; ?>
    </section>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/app.js?v=20260818-10"></script>
</body>
</html>
