<?php
require_once __DIR__ . '/auth/auth_check.php';
require_auth();

$role = $_SESSION['role'] ?? '';
if ($role !== 'Hospital Admin') {
    redirect('dashboard.php');
}

$errors = session_pull('errors', []);
$success = session_pull('success');
$accountView = (string)($_GET['role'] ?? '');
$roleViews = [
    '' => ['title' => 'User account directory', 'description' => 'Create and manage patient, clinical, pharmacy, and laboratory accounts for your hospital.', 'roles' => ['Patient', 'Doctor', 'Pharmacist', 'Lab Technician']],
    'Doctor' => ['title' => 'Doctor accounts', 'description' => 'Manage doctor accounts assigned to your hospital.', 'roles' => ['Doctor']],
    'Patient' => ['title' => 'Patient accounts', 'description' => 'Manage patient accounts assigned to your hospital.', 'roles' => ['Patient']],
    'staff' => ['title' => 'Hospital staff accounts', 'description' => 'Create and manage pharmacist and laboratory technician accounts for your hospital.', 'roles' => ['Pharmacist', 'Lab Technician']],
];
if (!isset($roleViews[$accountView])) {
    $accountView = '';
}
$activeView = $roleViews[$accountView];

try {
    $hospitalStmt = db()->prepare('SELECT hospital_id FROM users WHERE id = ? LIMIT 1');
    $hospitalStmt->execute([(int)$_SESSION['user_id']]);
    $hospitalId = (int)$hospitalStmt->fetchColumn();
    if ($hospitalId <= 0) {
        throw new RuntimeException('Your administrator account is not assigned to a hospital.');
    }
    $placeholders = implode(', ', array_fill(0, count($activeView['roles']), '?'));
    $stmt = db()->prepare("SELECT id, fullname, email, role FROM users WHERE role IN ($placeholders) AND hospital_id = ? ORDER BY role, fullname");
    $stmt->execute([...$activeView['roles'], $hospitalId]);
    $accounts = $stmt->fetchAll();
} catch (PDOException|RuntimeException $e) {
    $errors[] = 'Unable to load user accounts.';
    $accounts = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Credentials - NHRE</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="assets/css/styles.css?v=20260818-11">
</head>
<body class="dashboard-body">
  <?php require __DIR__ . '/includes/sidebar.php'; ?>
  <?php require __DIR__ . '/includes/topnav.php'; ?>

  <main class="dashboard-main">
    <section class="container">
      <div class="dashboard-hero glass-card">
        <div>
          <span class="auth-kicker">Admin-only access</span>
          <h1><?= e($activeView['title']) ?></h1>
          <p><?= e($activeView['description']) ?></p>
        </div>
      </div>

      <?php if ($errors): ?>
        <div class="alert alert-danger auth-alert mt-4" role="alert">
          <?php foreach ($errors as $message): ?>
            <div><?= e($message) ?></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if ($success): ?>
        <div class="alert alert-success auth-alert mt-4" role="alert">
          <span><?= e($success) ?></span>
        </div>
      <?php endif; ?>

      <div class="row g-4 mt-3">
        <div class="col-lg-5">
          <article class="dashboard-card h-100">
            <div class="dashboard-card-icon"><i class="fa-solid fa-user-plus"></i></div>
            <h2>Add hospital account</h2>
            <p class="text-muted">New accounts are assigned to your hospital automatically.</p>
            <form action="auth/admin_account_create_process.php" method="POST" class="auth-form row g-3">
              <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
              <input type="hidden" name="account_view" value="<?= e($accountView) ?>">
              <div class="col-12"><input class="form-control" name="fullname" placeholder="Full name" required maxlength="150"></div>
              <div class="col-md-6"><input class="form-control" name="nid" placeholder="National ID" required inputmode="numeric" pattern="[0-9]{10,20}"></div>
              <div class="col-md-6"><select class="form-select" name="role" required><option value="">Choose role</option><?php foreach ($activeView['roles'] as $availableRole): ?><option value="<?= e($availableRole) ?>" <?= count($activeView['roles']) === 1 ? 'selected' : '' ?>><?= e($availableRole) ?></option><?php endforeach; ?></select></div>
              <div class="col-12"><input class="form-control" type="email" name="email" placeholder="Email address" required maxlength="190"></div>
              <div class="col-md-6"><input class="form-control" name="phone" placeholder="Phone number" required maxlength="20"></div>
              <div class="col-md-6"><input class="form-control" type="password" name="password" placeholder="Temporary password" required minlength="8"></div>
              <div class="col-12"><button class="btn btn-solid-nhre w-100" type="submit"><i class="fa-solid fa-user-plus me-2"></i>Create account</button></div>
            </form>
          </article>
        </div>
        <div class="col-lg-7">
          <article class="dashboard-card h-100">
            <div class="dashboard-card-icon"><i class="fa-solid fa-users-gear"></i></div>
            <h2>Managed hospital accounts</h2>
            <p class="text-muted">Passwords are never displayed. Removing an account permanently deletes its related account data.</p>
            <div class="table-responsive mt-3">
              <table class="table table-hover align-middle">
                <thead>
                  <tr>
                    <th>Role</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th class="text-end">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if ($accounts): ?>
                    <?php foreach ($accounts as $account): ?>
                      <tr>
                        <td><?= e($account['role']) ?></td>
                        <td><?= e($account['fullname']) ?></td>
                        <td><?= e($account['email']) ?></td>
                        <td class="text-end"><form action="auth/admin_account_delete_process.php" method="POST" onsubmit="return confirm('Remove this account? This cannot be undone.');"><input type="hidden" name="_csrf" value="<?= csrf_token() ?>"><input type="hidden" name="account_view" value="<?= e($accountView) ?>"><input type="hidden" name="account_id" value="<?= (int)$account['id'] ?>"><button type="submit" class="btn btn-outline-danger btn-sm">Remove</button></form></td>
                      </tr>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <tr><td colspan="4" class="text-center text-muted">No managed accounts found.</td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </article>
        </div>
      </div>
    </section>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/app.js?v=20260818-11"></script>
</body>
</html>
