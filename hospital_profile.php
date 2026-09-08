<?php
declare(strict_types=1);

require_once __DIR__ . '/auth/auth_check.php';
require_role(['Hospital Admin']);
ensure_doctor_catalog_tables();

$errors = session_pull('errors', []);
$success = session_pull('success');

try {
    $pdo = db();
    $districts = $pdo->query('SELECT id, name FROM districts ORDER BY name')->fetchAll();
    $hospitals = $pdo->query(
        'SELECT h.id, h.name, h.address, h.phone, h.email, h.is_active, d.name AS district_name,
                (SELECT COUNT(*) FROM users u WHERE u.hospital_id = h.id) AS account_count
         FROM hospitals h LEFT JOIN districts d ON d.id = h.district_id
         ORDER BY h.is_active DESC, h.name ASC'
    )->fetchAll();
} catch (PDOException $e) {
    $errors[] = 'Unable to load the hospital directory.';
    $districts = $hospitals = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Hospital Profile - NHRE</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="assets/css/styles.css?v=20260818-18">
</head>
<body class="dashboard-body">
<?php require __DIR__ . '/includes/sidebar.php'; ?>
<?php require __DIR__ . '/includes/topnav.php'; ?>
<main class="dashboard-main"><section class="container">
  <div class="dashboard-hero glass-card"><div><span class="auth-kicker">Hospital network</span><h1>Hospital connections</h1><p>Connect new partner hospitals and manage their availability in NHRE.</p></div><div class="dashboard-user-pill"><i class="fa-solid fa-hospital"></i><span><?= count($hospitals) ?> integrated</span></div></div>
  <?php if ($errors): ?><div class="alert alert-danger auth-alert mt-4" role="alert"><?php foreach ($errors as $error): ?><div><?= e($error) ?></div><?php endforeach; ?></div><?php endif; ?>
  <?php if ($success): ?><div class="alert alert-success auth-alert mt-4" role="alert"><?= e($success) ?></div><?php endif; ?>
  <div class="row g-4 mt-3">
    <div class="col-lg-5"><article class="dashboard-card h-100"><div class="dashboard-card-icon"><i class="fa-solid fa-link"></i></div><h2>Connect a hospital</h2><p class="text-muted">Add a hospital to the NHRE partner directory.</p>
      <form action="auth/hospital_connection_create_process.php" method="POST" class="auth-form row g-3"><input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
        <div class="col-12"><input class="form-control" name="name" placeholder="Hospital name" required maxlength="190"></div>
        <div class="col-md-6"><select class="form-select" name="district_id" required><option value="">District</option><?php foreach ($districts as $district): ?><option value="<?= (int)$district['id'] ?>"><?= e($district['name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-6"><input class="form-control" name="phone" placeholder="Phone number" maxlength="30"></div>
        <div class="col-12"><input class="form-control" type="email" name="email" placeholder="Contact email" maxlength="190"></div>
        <div class="col-12"><input class="form-control" name="address" placeholder="Address" maxlength="255"></div>
        <div class="col-12"><button class="btn btn-solid-nhre w-100" type="submit"><i class="fa-solid fa-plus me-2"></i>Connect hospital</button></div>
      </form>
    </article></div>
    <div class="col-lg-7"><article class="dashboard-card h-100"><div class="dashboard-card-icon"><i class="fa-solid fa-building-circle-check"></i></div><h2>Integrated hospitals</h2><p class="text-muted">Disconnecting is reversible and retains linked account history.</p>
      <div class="table-responsive mt-3"><table class="table table-hover align-middle"><thead><tr><th>Hospital</th><th>District</th><th>Accounts</th><th>Status</th><th class="text-end">Connection</th></tr></thead><tbody>
      <?php foreach ($hospitals as $hospital): ?><tr><td><strong><?= e($hospital['name']) ?></strong><small class="d-block text-muted"><?= e($hospital['email'] ?: $hospital['phone']) ?></small></td><td><?= e($hospital['district_name'] ?? '—') ?></td><td><?= (int)$hospital['account_count'] ?></td><td><span class="badge <?= (int)$hospital['is_active'] ? 'bg-success-subtle text-success-emphasis' : 'bg-secondary-subtle text-secondary-emphasis' ?>"><?= (int)$hospital['is_active'] ? 'Connected' : 'Disconnected' ?></span></td><td class="text-end"><form action="auth/hospital_connection_toggle_process.php" method="POST" onsubmit="return confirm('<?= (int)$hospital['is_active'] ? 'Disconnect' : 'Reconnect' ?> this hospital?');"><input type="hidden" name="_csrf" value="<?= csrf_token() ?>"><input type="hidden" name="hospital_id" value="<?= (int)$hospital['id'] ?>"><button class="btn btn-sm <?= (int)$hospital['is_active'] ? 'btn-outline-danger' : 'btn-outline-success' ?>" type="submit"><?= (int)$hospital['is_active'] ? 'Disconnect' : 'Reconnect' ?></button></form></td></tr><?php endforeach; ?>
      <?php if ($hospitals === []): ?><tr><td colspan="5" class="text-center text-muted">No hospital connections found.</td></tr><?php endif; ?></tbody></table></div>
    </article></div>
  </div>
</section></main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script><script src="assets/js/app.js?v=20260811-8"></script>
</body></html>
