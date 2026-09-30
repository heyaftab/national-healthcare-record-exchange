<?php
require_once __DIR__ . '/auth/auth_check.php';
require_role(['Patient', 'Doctor']);

$fullname = $_SESSION['fullname'] ?? 'NHRE User';
$role = $_SESSION['role'] ?? 'User';

$vaccine = trim($_GET['vaccine'] ?? '');
$validVaccines = vaccination_names();
if (!in_array($vaccine, $validVaccines, true)) {
    $vaccine = '';
}

$type = trim($_GET['type'] ?? '');
if (!in_array($type, ['Public', 'Private'], true)) {
    $type = '';
}

ensure_vaccination_center_tables();

$districts = [];
try {
    $districts = db()->query('SELECT DISTINCT district FROM vaccination_centers WHERE is_active = 1 ORDER BY district')->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $districts = [];
}

$district = trim($_GET['district'] ?? '');
if ($district !== '' && !in_array($district, $districts, true)) {
    $district = '';
}

$centers = [];
if ($vaccine !== '') {
    try {
        $sql = 'SELECT c.name, c.district, c.division, c.center_type, c.address, c.phone, p.price
                FROM vaccination_centers c
                INNER JOIN vaccination_center_prices p ON p.center_id = c.id
                WHERE p.vaccine_name = ? AND c.is_active = 1';
        $params = [$vaccine];
        if ($type !== '') {
            $sql .= ' AND c.center_type = ?';
            $params[] = $type;
        }
        if ($district !== '') {
            $sql .= ' AND c.district = ?';
            $params[] = $district;
        }
        $sql .= ' ORDER BY c.center_type DESC, c.division, c.name';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        $centers = $stmt->fetchAll();
    } catch (PDOException $e) {
        $centers = [];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Vaccination Centers - NHRE</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="assets/css/styles.css?v=20260811-16">
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
        <a href="vaccination.php" class="btn btn-dashboard-logout ripple"><i class="fa-solid fa-syringe"></i> <span>Vaccination</span></a>
        <a href="notifications.php" class="btn btn-dashboard-logout ripple"><i class="fa-solid fa-bell"></i> <span>Notifications</span></a>
      </div>
    </div>
  </nav>

  <main class="dashboard-main">
    <section class="container">
      <div class="dashboard-hero glass-card">
        <div>
          <span class="auth-kicker">Vaccination Centers</span>
          <h1><?= $vaccine !== '' ? e($vaccine) . ' vaccination centers' : 'Vaccination centers' ?></h1>
          <p>Find approved government and private vaccination centers with per-dose prices.</p>
        </div>
        <div class="dashboard-user-pill">
          <i class="fa-solid fa-location-dot"></i>
          <span><?= e($role) ?></span>
        </div>
      </div>

      <div class="d-flex flex-wrap align-items-center gap-2 mb-4">
        <?php if ($vaccine !== ''): ?>
          <?php foreach (['' => 'All', 'Public' => 'Public', 'Private' => 'Private'] as $typeKey => $typeLabel): ?>
            <a class="btn <?= $type === $typeKey ? 'btn-solid-nhre' : 'btn-filter' ?> ripple"
               href="vaccination_centers.php?vaccine=<?= urlencode($vaccine) ?><?= $typeKey !== '' ? '&type=' . urlencode($typeKey) : '' ?><?= $district !== '' ? '&district=' . urlencode($district) : '' ?>">
              <?= e($typeLabel) ?>
            </a>
          <?php endforeach; ?>

          <form class="ms-auto d-flex align-items-center gap-2" method="get" action="vaccination_centers.php">
            <input type="hidden" name="vaccine" value="<?= e($vaccine) ?>">
            <input type="hidden" name="type" value="<?= e($type) ?>">
            <label class="form-label fw-semibold text-muted mb-0" for="districtFilter">District</label>
            <select class="form-select form-select-sm" id="districtFilter" name="district" onchange="this.form.submit()">
              <option value="">All districts</option>
              <?php foreach ($districts as $districtName): ?>
                <option value="<?= e($districtName) ?>" <?= $district === $districtName ? 'selected' : '' ?>><?= e($districtName) ?></option>
              <?php endforeach; ?>
            </select>
          </form>
        <?php endif; ?>
        <a class="btn btn-filter ripple" href="vaccination.php">
          <i class="fa-solid fa-arrow-left"></i> <span>Back to schedule</span>
        </a>
      </div>

      <?php if ($vaccine === ''): ?>
        <article class="dashboard-card">
          <div class="dashboard-card-icon"><i class="fa-solid fa-shield-virus"></i></div>
          <h2>Select a vaccine</h2>
          <p>Choose a vaccine to see the vaccination centers that offer it.</p>
          <div class="d-flex flex-wrap gap-2 mt-3">
            <?php foreach ($validVaccines as $name): ?>
              <a class="btn btn-filter ripple" href="vaccination_centers.php?vaccine=<?= urlencode($name) ?>"><?= e($name) ?></a>
            <?php endforeach; ?>
          </div>
        </article>
      <?php elseif (!$centers): ?>
        <article class="dashboard-card">
          <div class="dashboard-card-icon"><i class="fa-solid fa-circle-exclamation"></i></div>
          <h2>No centers found</h2>
          <p>No <?= $type !== '' ? strtolower(e($type)) . ' ' : '' ?>centers currently list the <?= e($vaccine) ?> vaccine. Try another filter or check back later.</p>
        </article>
      <?php else: ?>
        <div class="row g-4">
          <?php foreach ($centers as $center): ?>
            <?php $isPublic = $center['center_type'] === 'Public'; ?>
            <div class="col-md-6 col-xl-4">
              <article class="dashboard-card">
                <div class="d-flex align-items-start justify-content-between gap-2">
                  <div class="dashboard-card-icon"><i class="fa-solid fa-<?= $isPublic ? 'hospital' : 'building' ?>"></i></div>
                  <?php if ($isPublic): ?>
                    <span class="badge bg-success-subtle text-success-emphasis">Public</span>
                  <?php else: ?>
                    <span class="badge bg-warning-subtle text-warning-emphasis">Private</span>
                  <?php endif; ?>
                </div>
                <h2><?= e($center['name']) ?></h2>
                <p class="mb-1"><i class="fa-solid fa-map-location-dot text-muted me-1"></i><?= e($center['district']) ?>, <?= e($center['division']) ?></p>
                <?php if ($center['address']): ?>
                  <p class="mb-1 text-muted"><i class="fa-solid fa-location-dot me-1"></i><?= e($center['address']) ?></p>
                <?php endif; ?>
                <?php if ($center['phone']): ?>
                  <p class="mb-1 text-muted"><i class="fa-solid fa-phone me-1"></i><?= e($center['phone']) ?></p>
                <?php endif; ?>
                <div class="d-flex justify-content-between align-items-center mt-3">
                  <span class="badge bg-secondary-subtle text-secondary-emphasis"><?= e($vaccine) ?></span>
                  <?php if ($isPublic): ?>
                    <span class="fw-bold text-teal">Free (Government EPI)</span>
                  <?php else: ?>
                    <span class="fw-bold text-teal">৳<?= number_format((int)$center['price']) ?></span>
                  <?php endif; ?>
                </div>
              </article>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/app.js?v=20260818-10"></script>
</body>
</html>
