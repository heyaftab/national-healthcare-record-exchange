<?php
require_once __DIR__ . '/auth/auth_check.php';
redirect_if_authenticated();

$token = $_GET['token'] ?? '';
$errors = session_pull('errors', []);
$success = session_pull('success');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reset Password - NHRE</title>
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
    <a class="auth-brand" href="index.php" aria-label="Back to NHRE home">
      <img src="assets/images/nhre-logo.svg" alt="NHRE" class="nhre-logo-img">
    </a>

    <section class="auth-shell container">
      <div class="row justify-content-center">
        <div class="col-lg-5 col-md-8">
          <div class="card auth-card glass-card">
            <div class="card-body">
              <div class="auth-card-head">
                <span class="auth-kicker">Create new password</span>
                <h2>Reset your password</h2>
                <p>Choose a strong new password for your account.</p>
              </div>

              <?php if ($success): ?>
                <div class="alert alert-success auth-alert" role="alert">
                  <i class="fa-solid fa-circle-check"></i>
                  <span><?= e($success) ?></span>
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

              <form action="auth/reset_password_process.php" method="POST" class="needs-validation auth-form" novalidate>
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="token" value="<?= e($token) ?>">

                <div class="form-floating mb-3">
                  <input type="password" class="form-control" id="password" name="password" placeholder="New Password" required minlength="8">
                  <label for="password"><i class="fa-solid fa-lock"></i> New Password</label>
                  <div class="invalid-feedback">Use 8+ chars with uppercase, lowercase, number, and symbol.</div>
                </div>

                <div class="form-floating mb-3">
                  <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Confirm Password" required>
                  <label for="confirm_password"><i class="fa-solid fa-shield-halved"></i> Confirm Password</label>
                  <div class="invalid-feedback">Passwords must match.</div>
                </div>

                <button type="submit" class="btn btn-auth-primary ripple w-100">
                  <span>Update Password</span>
                  <i class="fa-solid fa-key"></i>
                </button>
              </form>

              <p class="auth-switch"><a href="login.php">Return to login</a></p>
            </div>
          </div>
        </div>
      </div>
    </section>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/app.js?v=20260811-8"></script>
</body>
</html>
