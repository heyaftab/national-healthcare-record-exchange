<?php
require_once __DIR__ . '/auth/auth_check.php';
require_role(['Doctor', 'Lab Technician', 'Pharmacist']);

$fullname = $_SESSION['fullname'] ?? 'NHRE User';
$role = $_SESSION['role'] ?? 'User';
$errors = session_pull('errors', []);
$success = session_pull('success');
$providerId = (int)($_SESSION['user_id'] ?? 0);

$query = trim((string)($_GET['q'] ?? ''));
$results = [];
$searched = $query !== '';

if ($searched) {
    if (mb_strlen($query) > 80) {
        $errors[] = 'Search query is too long.';
    } else {
        try {
            $stmt = db()->prepare(
                'SELECT id, fullname, nid, email, phone, account_number, date_of_birth, gender, blood_group
                 FROM users
                 WHERE role = \'Patient\'
                   AND (fullname LIKE ? OR nid LIKE ? OR phone LIKE ? OR email LIKE ? OR account_number LIKE ?)
                 ORDER BY fullname ASC
                 LIMIT 15'
            );
            $like = '%' . $query . '%';
            $stmt->execute([$like, $like, $like, $like, $like]);
            $results = $stmt->fetchAll();
        } catch (PDOException $e) {
            $errors[] = 'Search failed. Please try again later.';
        }
    }
}

foreach ($results as &$patient) {
    $patient['access'] = active_access((int)$patient['id'], $providerId);
}
unset($patient);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Patient Search - NHRE</title>
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
          <span class="auth-kicker">Patient Lookup</span>
          <h1>Patient Search</h1>
          <p>Search by NHRE Patient ID, National ID, name, or phone number. Only basic profile details are shown until the patient grants access.</p>
        </div>
        <div class="dashboard-user-pill">
          <i class="fa-solid fa-magnifying-glass"></i>
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

      <form method="GET" action="patient_search.php" class="dashboard-card mt-4">
        <div class="d-flex flex-column flex-sm-row gap-2">
          <input type="text" class="form-control" name="q" value="<?= e($query) ?>"
            placeholder="Name, NID, phone number, or email" maxlength="80" required>
          <button type="submit" class="btn btn-solid-nhre flex-shrink-0">
            <i class="fa-solid fa-magnifying-glass"></i> Search
          </button>
        </div>
      </form>

      <?php if ($searched): ?>
        <div class="row g-4 mt-2">
          <?php if ($results): ?>
            <?php foreach ($results as $patient): ?>
              <div class="col-md-6 col-xl-4">
                <article class="dashboard-card">
                  <div class="d-flex align-items-center gap-3 mb-3">
                    <span class="sidebar-avatar"><?= e(strtoupper(mb_substr(trim($patient['fullname']), 0, 1))) ?></span>
                    <div>
                      <h2 class="fs-5 mb-0"><?= e($patient['fullname']) ?></h2>
                      <small class="text-muted">Patient ID #<?= (int)$patient['id'] ?></small>
                    </div>
                  </div>
                  <p class="mb-1"><strong>Age:</strong> <?= $patient['date_of_birth'] ? e(age_from_dob($patient['date_of_birth'])) . ' years' : '—' ?></p>
                  <p class="mb-1"><strong>Gender:</strong> <?= $patient['gender'] ? e($patient['gender']) : '—' ?></p>
                  <p class="mb-0"><strong>Blood Group:</strong> <?= $patient['blood_group'] ? e($patient['blood_group']) : '—' ?></p>

                  <hr>
                  <?php if ($patient['access']): ?>
                    <p class="text-success mb-2"><i class="fa-solid fa-circle-check"></i> Authorized until <?= e(date('j M Y', strtotime($patient['access']['expires_at']))) ?></p>
                    <a class="btn btn-solid-nhre w-100" href="authorized_records.php?patient=<?= (int)$patient['id'] ?>">
                      <i class="fa-solid fa-file-shield"></i> View Authorized Records
                    </a>
                  <?php else: ?>
                    <form action="auth/access_request_process.php" method="POST">
                      <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                      <input type="hidden" name="patient_id" value="<?= (int)$patient['id'] ?>">
                      <button type="submit" class="btn btn-outline-nhre w-100">
                        <i class="fa-solid fa-user-plus"></i> Request Access
                      </button>
                    </form>
                    <small class="text-muted d-block mt-2">The patient will be notified and can grant access from their Data Access page.</small>
                  <?php endif; ?>
                </article>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="col-12">
              <article class="dashboard-card">
                <p class="mb-0 text-muted">No patients match your search.</p>
              </article>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </section>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/app.js?v=20260811-8"></script>
</body>
</html>
