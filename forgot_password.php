<?php
require_once __DIR__ . '/auth/auth_check.php';
redirect_if_authenticated();

$errors = session_pull('errors', []);
$old = session_pull('old', []);
$success = session_pull('success');
$reset_url = session_pull('reset_url');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Forgot Password - NHRE</title>
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

    <div class="auth-mini-brand" aria-label="NHRE brand mark">
      <div class="auth-mini-brand-mark">
        <img src="assets/images/nhre-logo.svg" alt="NHRE" aria-hidden="true">
      </div>
    </div>

    <section class="auth-shell auth-compact-shell container">
      <div class="row justify-content-center">
        <div class="col-lg-6 col-md-8">
          <div class="card auth-card auth-compact-card glass-card">
            <div class="card-body">
              <div class="auth-compact-header">
                <span class="auth-step-badge">Account recovery</span>
                <h2>Forgot your password?</h2>
                <p>Enter the email connected to your account and we’ll send a secure reset link.</p>
              </div>

              <div class="auth-inline-note">
                <i class="fa-solid fa-shield-heart"></i>
                <span>We’ll only use this email to send your password reset instructions.</span>
              </div>

              <?php if ($success): ?>
                <div class="alert alert-success auth-alert" role="alert">
                  <i class="fa-solid fa-circle-check"></i>
                  <div>
                    <div><?= e($success) ?></div>
                    <?php if ($reset_url): ?>
                      <a href="<?= e($reset_url) ?>" class="alert-link"><?= e($reset_url) ?></a>
                    <?php endif; ?>
                  </div>
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

              <form action="auth/forgot_password_process.php" method="POST" class="needs-validation auth-form" novalidate>
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <div class="form-floating mb-3">
                  <input type="email" class="form-control" id="email" name="email" placeholder="name@example.com" value="<?= e($old['email'] ?? '') ?>" required>
                  <label for="email"><i class="fa-solid fa-envelope"></i> Email address</label>
                  <div class="invalid-feedback">Please enter a valid email address.</div>
                </div>

                <button type="submit" class="btn btn-auth-primary ripple w-100">
                  <span>Create reset link</span>
                  <i class="fa-solid fa-paper-plane"></i>
                </button>
              </form>

              <p class="auth-switch"><a href="login.php">Back to login</a></p>
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
