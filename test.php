<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/auth/auth_check.php';
require_auth();

if (($_SESSION['role'] ?? '') !== 'Hospital Admin') {
    redirect('dashboard.php');
}

$results = [];

$results['PHP Version'] = PHP_VERSION;

try {
    $pdo = db();
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $results['Database connection'] = 'Connected to "' . DB_NAME . '"';
    $results['Tables'] = $tables ? implode(', ', $tables) : '(none found)';

    $stmt = $pdo->query('SELECT COUNT(*) FROM users');
    $results['Registered users'] = $stmt->fetchColumn();
} catch (PDOException $e) {
    $results['Database connection'] = 'FAILED: ' . $e->getMessage();
}

$results['Valid roles'] = implode(', ', valid_roles());
$results['Session started'] = session_status() === PHP_SESSION_ACTIVE ? 'Yes' : 'No';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>NHRE Setup Test</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
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
    <section class="container" style="max-width: 680px;">
      <div class="glass-card p-4 mt-4">
        <h2 class="mb-4">NHRE Setup Test</h2>
        <table class="table">
          <tbody>
            <?php foreach ($results as $label => $value): ?>
              <tr>
                <th scope="row" class="w-50"><?= e($label) ?></th>
                <td><?= e($value) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <a href="dashboard.php" class="btn btn-solid-nhre">Back to Dashboard</a>
      </div>
    </section>
  </main>
  <script src="assets/js/app.js?v=20260811-8"></script>
</body>
</html>
