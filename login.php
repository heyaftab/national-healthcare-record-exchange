<?php
require_once __DIR__ . '/auth/auth_check.php';
redirect_if_authenticated();

$errors = session_pull('errors', []);
$old = session_pull('old', []);
$success = session_pull('success');
$error = session_pull('error');
$demo_accounts = [
    ['role' => 'Patient', 'fullname' => 'Patient A', 'email' => 'patient@nhre.gov', 'password' => 'Patient123!', 'badge' => 'primary', 'seed' => 'demo-patient-default', 'email_note' => 'Primary patient'],
    ['role' => 'Doctor', 'fullname' => 'Dr. Afsana Ahmed', 'email' => 'doctor001@nhre.dev', 'password' => 'Doctor123!', 'badge' => 'info', 'seed' => 'demo-doctor-001', 'email_note' => 'Primary doctor'],
    ['role' => 'Pharmacist', 'fullname' => 'Ahsanul Haque', 'email' => 'pharmacist@nhre.gov', 'password' => 'Pharmacist123!', 'badge' => 'success', 'seed' => 'demo-pharmacist'],
    ['role' => 'Lab Technician', 'fullname' => 'Maksudul Islam', 'email' => 'lab@nhre.gov', 'password' => 'Lab123!', 'badge' => 'warning', 'seed' => 'demo-lab-technician'],
    ['role' => 'Hospital Admin', 'fullname' => 'Dr. Mohammad Ashraf Karim', 'email' => 'admin@nhre.gov', 'password' => 'Admin123!', 'badge' => 'danger', 'seed' => 'demo-hospital-admin'],
    ['role' => 'System Admin', 'fullname' => 'Nusrat Jahan Chowdhury', 'email' => 'sysadmin@nhre.gov', 'password' => 'SysAdmin123!', 'badge' => 'dark', 'seed' => 'demo-system-admin'],
];
$demo_profile_photos = [];
$demo_account_numbers = [];
$demo_account_names = [];

try {
    $emails = array_column($demo_accounts, 'email');
    if ($emails !== []) {
        $placeholders = implode(',', array_fill(0, count($emails), '?'));
        $profileStmt = db()->prepare('SELECT email, fullname, profile_photo, account_number FROM users WHERE email IN (' . $placeholders . ')');
        $profileStmt->execute($emails);
        foreach ($profileStmt->fetchAll() as $profile) {
            $email = (string)($profile['email'] ?? '');
            if ($email !== '') {
                $demo_profile_photos[$email] = (string)($profile['profile_photo'] ?? '');
                $demo_account_numbers[$email] = (string)($profile['account_number'] ?? '');
                $demo_account_names[$email] = (string)($profile['fullname'] ?? '');
            }
        }
    }
    foreach ($demo_accounts as &$demoAccount) {
        $demoAccount['account_number'] = $demo_account_numbers[$demoAccount['email']] ?? '—';
        $demoAccount['fullname'] = $demo_account_names[$demoAccount['email']] ?? $demoAccount['role'];
    }
    unset($demoAccount);
} catch (PDOException $e) {
    foreach ($demo_accounts as &$demoAccount) {
        $demoAccount['account_number'] = '—';
        $demoAccount['fullname'] = $demoAccount['role'];
    }
    unset($demoAccount);
}

function demo_profile_picture(array $account, array $photos): string
{
    if (!empty($_SESSION['user_id']) && !empty($_SESSION['profile_photo'])) {
        return $_SESSION['profile_photo'];
    }

    $photo = $photos[$account['email']] ?? '';
    $isLocalPhoto = $photo !== '' && is_file(__DIR__ . '/' . $photo);
    $isCartoonAvatar = str_starts_with($photo, 'https://api.dicebear.com/9.x/avataaars/svg?');
    if ($isLocalPhoto || $isCartoonAvatar) {
        return $photo;
    }

    return 'https://api.dicebear.com/9.x/avataaars/svg?seed=' . rawurlencode($account['seed'])
        . '&backgroundColor=b6e3f4,c0aede,d1d4f9&radius=50';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login - NHRE</title>
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
<body class="auth-body">
  <main class="auth-page">
    <section class="auth-shell auth-shell--split container">
      <div class="row g-0 align-items-stretch justify-content-center">
        <div class="col-xl-4 d-none d-xl-block">
          <div class="auth-info-panel">
            <div class="auth-info-brand">
              <img src="assets/images/nhre-logo.svg" alt="" class="auth-info-logo" aria-hidden="true">
              <div class="auth-info-brand-text">
                <span class="auth-info-name">NHRE</span>
                <span class="auth-info-sub">National Healthcare Record Exchange</span>
              </div>
            </div>
            <div class="auth-trust-pill"><i class="fa-solid fa-shield-halved"></i> Trusted. Secure. Connected.</div>
            <h1>Your health information, <em>securely connected</em></h1>
            <span class="auth-info-rule"></span>
            <p>Sign in to access your records, appointments, and trusted healthcare tools.</p>
            <div class="auth-info-list">
              <span><i class="fa-solid fa-lock"></i><b>Private by design</b><small>Protected with encryption and strict privacy controls.</small></span>
              <span><i class="fa-solid fa-heart-pulse"></i><b>Healthcare at hand</b><small>Your essential records are always within reach.</small></span>
            </div>
          </div>
        </div>

        <div class="col-xl-8 col-lg-9 col-md-8">
          <div class="card auth-card glass-card">
            <div class="card-body">
              <a class="auth-back-link" href="index.php"><i class="fa-solid fa-arrow-left"></i> Back to home</a>
              <div class="auth-card-head auth-card-head--with-badge">
                <div><span class="auth-kicker">Welcome back</span>
                <h2>Login to NHRE</h2>
                <p>Use your registered email and password to continue.</p></div>
                <div class="auth-security-badge"><i class="fa-solid fa-shield-halved"></i><span><b>Your data is safe</b>and encrypted</span></div>
              </div>

              <?php if ($success): ?>
                <div class="alert alert-success auth-alert" role="alert">
                  <i class="fa-solid fa-circle-check"></i>
                  <span><?= e($success) ?></span>
                </div>
              <?php endif; ?>

              <?php if ($error): ?>
                <div class="alert alert-warning auth-alert" role="alert">
                  <i class="fa-solid fa-triangle-exclamation"></i>
                  <span><?= e($error) ?></span>
                </div>
              <?php endif; ?>

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

              <form action="auth/login_process.php" method="POST" class="needs-validation auth-form" id="loginForm" novalidate>
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <div class="form-floating mb-3">
                  <input type="email" class="form-control" id="email" name="email" placeholder="name@example.com" value="<?= e($old['email'] ?? '') ?>" required>
                  <label for="email"><i class="fa-solid fa-envelope"></i> Email</label>
                  <div class="invalid-feedback">Please enter a valid email address.</div>
                </div>

                <div class="form-floating mb-3">
                  <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                  <label for="password"><i class="fa-solid fa-key"></i> Password</label>
                  <div class="invalid-feedback">Please enter your password.</div>
                </div>

                <div class="d-flex justify-content-between align-items-center gap-3 mb-4">
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" value="1" id="remember_me" name="remember_me">
                    <label class="form-check-label" for="remember_me">Remember Me</label>
                  </div>
                  <a href="forgot_password.php" class="auth-link">Forgot Password?</a>
                </div>

                <button type="submit" class="btn btn-auth-primary ripple w-100">
                  <span>Login</span>
                  <i class="fa-solid fa-arrow-right-to-bracket"></i>
                </button>
              </form>

              <p class="auth-switch">New to NHRE? <a href="register.php">Create an account</a></p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="auth-shell container mt-4">
      <div class="row g-4 justify-content-center">
        <div class="col-lg-10">
          <div class="demo-accounts glass-card">
            <div class="demo-accounts-head">
              <h3>Accounts</h3>
              <p>Choose any account below and click <strong>Use</strong> to fill the login form.</p>
            </div>
            <div class="table-responsive">
              <table class="table table-sm table-hover align-middle mb-0">
                <thead>
                  <tr>
                    <th>Profile</th>
                    <th>Role</th>
                    <th>Name</th>
                    <th>NHRE ID</th>
                    <th class="text-end">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($demo_accounts as $account): ?>
                    <tr>
                      <td><img class="demo-account-avatar" src="<?= e(demo_profile_picture($account, $demo_profile_photos)) ?>" alt="Profile picture for <?= e($account['role']) ?> account"></td>
                      <td><span class="badge bg-<?= e($account['badge']) ?>-subtle text-<?= e($account['badge']) ?>-emphasis"><?= e($account['role']) ?></span></td>
                      <td class="fw-semibold"><?= e($account['fullname'] ?? $account['role']) ?></td>
                      <td class="font-monospace"><?= e($account['account_number'] ?? '—') ?></td>
                      <td class="text-end"><button type="button" class="btn btn-demo-fill btn-sm" data-email="<?= e($account['email']) ?>" data-password="<?= e($account['password']) ?>">Use</button></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </section>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/app.js?v=20260811-8"></script>
  <script>
    document.querySelectorAll('.btn-demo-fill').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var email = document.getElementById('email');
        var password = document.getElementById('password');
        if (!email || !password) {
          return;
        }
        email.value = btn.dataset.email;
        password.value = btn.dataset.password;
        email.dispatchEvent(new Event('input', { bubbles: true }));
        password.dispatchEvent(new Event('input', { bubbles: true }));
        document.querySelector('#loginForm')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        email.focus();
        window.nhreToast && nhreToast('Demo credentials filled — you\u2019re ready to log in.');
      });
    });

  </script>
</body>
</html>
