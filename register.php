<?php
require_once __DIR__ . '/auth/auth_check.php';
redirect_if_authenticated();

$errors = session_pull('errors', []);
$old = session_pull('old', []);
$roles = self_service_roles();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register - NHRE</title>
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
            <h1>Create your healthcare identity for a <em>better tomorrow</em></h1>
            <span class="auth-info-rule"></span>
            <p>Join the National Healthcare Record Exchange and securely manage your health information in one place.</p>
            <div class="auth-info-list">
              <span><i class="fa-solid fa-shield-heart"></i><b>Secure &amp; Private</b><small>Your data is protected with advanced encryption.</small></span>
              <span><i class="fa-solid fa-share-nodes"></i><b>Connected Healthcare</b><small>Share records securely with trusted providers.</small></span>
              <span><i class="fa-solid fa-file-medical"></i><b>Complete Records</b><small>Access your medical history, reports, and prescriptions.</small></span>
            </div>
          </div>
        </div>

        <div class="col-xl-8 col-lg-9">
          <div class="card auth-card glass-card">
            <div class="card-body">
              <a class="auth-back-link" href="index.php"><i class="fa-solid fa-arrow-left"></i> Back to home</a>
              <div class="auth-card-head auth-card-head--with-badge">
                <div><span class="auth-kicker">Create account</span>
                <h2>Register for NHRE</h2>
                <p>Set up your secure healthcare identity.</p></div>
                <div class="auth-security-badge"><i class="fa-solid fa-shield-halved"></i><span><b>Your data is safe</b>and encrypted</span></div>
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

              <form action="auth/register_process.php" method="POST" class="needs-validation auth-form" id="registerForm" novalidate>
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <div class="row g-3">

                  <div class="col-12">
                    <h3 class="form-group-head"><span class="form-group-head-icon" aria-hidden="true"><i class="fa-solid fa-id-card"></i></span> Personal Information</h3>
                  </div>

                  <div class="col-md-6">
                    <div class="form-floating">
                      <input type="text" class="form-control" id="fullname" name="fullname" placeholder="Full Name" value="<?= e($old['fullname'] ?? '') ?>" required minlength="2" maxlength="150">
                      <label for="fullname"><i class="fa-solid fa-user"></i> Full Name</label>
                      <div class="invalid-feedback">Full name is required.</div>
                    </div>
                  </div>

                  <div class="col-md-6">
                    <div class="form-floating">
                      <input type="text" class="form-control" id="nid" name="nid" placeholder="National ID" value="<?= e($old['nid'] ?? '') ?>" required inputmode="numeric" autocomplete="off" maxlength="20" pattern="[0-9]{10,20}" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                      <label for="nid"><i class="fa-solid fa-id-card"></i> National ID (NID)</label>
                      <div class="invalid-feedback">Enter a 10 to 20 digit National ID.</div>
                    </div>
                  </div>

                  <div class="col-md-6">
                    <div class="form-floating">
                      <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" value="<?= e($old['date_of_birth'] ?? '') ?>">
                      <label for="date_of_birth"><i class="fa-solid fa-calendar-day"></i> Date of Birth</label>
                    </div>
                  </div>

                  <div class="col-md-6">
                    <div class="form-floating">
                      <select class="form-select" id="gender" name="gender">
                        <option value="" <?= empty($old['gender']) ? 'selected' : '' ?>>Select gender</option>
                        <option value="Male" <?= (($old['gender'] ?? '') === 'Male') ? 'selected' : '' ?>>Male</option>
                        <option value="Female" <?= (($old['gender'] ?? '') === 'Female') ? 'selected' : '' ?>>Female</option>
                        <option value="Other" <?= (($old['gender'] ?? '') === 'Other') ? 'selected' : '' ?>>Other</option>
                      </select>
                      <label for="gender"><i class="fa-solid fa-venus-mars"></i> Gender</label>
                    </div>
                  </div>

                  <div class="col-md-6">
                    <div class="form-floating">
                      <select class="form-select" id="blood_group" name="blood_group">
                        <option value="" <?= empty($old['blood_group']) ? 'selected' : '' ?>>Select blood group</option>
                        <option value="A+" <?= (($old['blood_group'] ?? '') === 'A+') ? 'selected' : '' ?>>A+</option>
                        <option value="A-" <?= (($old['blood_group'] ?? '') === 'A-') ? 'selected' : '' ?>>A-</option>
                        <option value="B+" <?= (($old['blood_group'] ?? '') === 'B+') ? 'selected' : '' ?>>B+</option>
                        <option value="B-" <?= (($old['blood_group'] ?? '') === 'B-') ? 'selected' : '' ?>>B-</option>
                        <option value="AB+" <?= (($old['blood_group'] ?? '') === 'AB+') ? 'selected' : '' ?>>AB+</option>
                        <option value="AB-" <?= (($old['blood_group'] ?? '') === 'AB-') ? 'selected' : '' ?>>AB-</option>
                        <option value="O+" <?= (($old['blood_group'] ?? '') === 'O+') ? 'selected' : '' ?>>O+</option>
                        <option value="O-" <?= (($old['blood_group'] ?? '') === 'O-') ? 'selected' : '' ?>>O-</option>
                      </select>
                      <label for="blood_group"><i class="fa-solid fa-droplet"></i> Blood Group</label>
                    </div>
                  </div>

                  <div class="col-md-6">
                    <div class="form-floating">
                      <input type="text" class="form-control" id="nationality" name="nationality" placeholder="Bangladeshi" value="<?= e($old['nationality'] ?? '') ?>">
                      <label for="nationality"><i class="fa-solid fa-globe"></i> Nationality</label>
                    </div>
                  </div>

                  <div class="col-md-6">
                    <div class="form-floating">
                      <select class="form-select" id="marital_status" name="marital_status">
                        <option value="" <?= empty($old['marital_status']) ? 'selected' : '' ?>>Select status</option>
                        <option value="Single" <?= (($old['marital_status'] ?? '') === 'Single') ? 'selected' : '' ?>>Single</option>
                        <option value="Married" <?= (($old['marital_status'] ?? '') === 'Married') ? 'selected' : '' ?>>Married</option>
                        <option value="Divorced" <?= (($old['marital_status'] ?? '') === 'Divorced') ? 'selected' : '' ?>>Divorced</option>
                        <option value="Widowed" <?= (($old['marital_status'] ?? '') === 'Widowed') ? 'selected' : '' ?>>Widowed</option>
                      </select>
                      <label for="marital_status"><i class="fa-solid fa-ring"></i> Marital Status</label>
                    </div>
                  </div>

                  <div class="col-md-6">
                    <div class="form-floating">
                      <input type="text" class="form-control" id="occupation" name="occupation" placeholder="Engineer" value="<?= e($old['occupation'] ?? '') ?>">
                      <label for="occupation"><i class="fa-solid fa-briefcase"></i> Occupation</label>
                    </div>
                  </div>

                  <div class="col-12">
                    <h3 class="form-group-head"><span class="form-group-head-icon" aria-hidden="true"><i class="fa-solid fa-address-book"></i></span> Contact Details</h3>
                  </div>

                  <div class="col-md-6">
                    <div class="form-floating">
                      <input type="email" class="form-control" id="email" name="email" placeholder="name@example.com" value="<?= e($old['email'] ?? '') ?>" required>
                      <label for="email"><i class="fa-solid fa-envelope"></i> Email</label>
                      <div class="invalid-feedback">Enter a valid email address.</div>
                    </div>
                  </div>

                  <div class="col-md-6">
                    <div class="form-floating">
                      <input type="tel" class="form-control" id="phone" name="phone" placeholder="+880..." value="<?= e($old['phone'] ?? '') ?>" required pattern="^\+?[0-9][0-9\s().-]{7,19}$">
                      <label for="phone"><i class="fa-solid fa-phone"></i> Phone Number</label>
                      <div class="invalid-feedback">Enter a valid phone number.</div>
                    </div>
                  </div>

                  <div class="col-md-6">
                    <div class="form-floating">
                      <input type="text" class="form-control" id="emergency_contact" name="emergency_contact" placeholder="+880..." value="<?= e($old['emergency_contact'] ?? '') ?>">
                      <label for="emergency_contact"><i class="fa-solid fa-phone-volume"></i> Emergency Contact</label>
                    </div>
                  </div>

                  <div class="col-12">
                    <div class="form-floating">
                      <textarea class="form-control" id="address" name="address" placeholder="Address" style="min-height: 110px;"><?= e($old['address'] ?? '') ?></textarea>
                      <label for="address"><i class="fa-solid fa-location-dot"></i> Address</label>
                    </div>
                  </div>

                  <div class="col-12">
                    <h3 class="form-group-head"><span class="form-group-head-icon" aria-hidden="true"><i class="fa-solid fa-lock"></i></span> Account Security</h3>
                  </div>

                  <div class="col-md-6">
                    <div class="form-floating">
                      <input type="password" class="form-control" id="password" name="password" placeholder="Password" required minlength="8" data-strong-password="true">
                      <label for="password"><i class="fa-solid fa-lock"></i> Password</label>
                      <div class="invalid-feedback">Use 8+ chars with uppercase, lowercase, number, and symbol.</div>
                    </div>
                    <div class="password-meter" aria-hidden="true">
                      <span></span>
                    </div>
                  </div>

                  <div class="col-md-6">
                    <div class="form-floating">
                      <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Confirm Password" required>
                      <label for="confirm_password"><i class="fa-solid fa-shield-halved"></i> Confirm Password</label>
                      <div class="invalid-feedback">Passwords must match.</div>
                    </div>
                  </div>

                  <div class="col-12">
                    <fieldset class="role-picker">
                      <legend><i class="fa-solid fa-users"></i> User Role</legend>
                      <div class="role-picker-options">
                        <?php foreach ($roles as $role): ?>
                          <?php $roleId = 'role-' . strtolower(str_replace(' ', '-', $role)); ?>
                          <input class="btn-check" type="radio" name="role" id="<?= e($roleId) ?>" value="<?= e($role) ?>" <?= (($old['role'] ?? '') === $role || (!$old && $role === 'Patient')) ? 'checked' : '' ?> required>
                          <label for="<?= e($roleId) ?>"><i class="fa-solid <?= $role === 'Patient' ? 'fa-user' : ($role === 'Doctor' ? 'fa-user-doctor' : ($role === 'Hospital Staff' ? 'fa-hospital' : ($role === 'Laboratory' ? 'fa-microscope' : 'fa-pills'))) ?>"></i><?= e($role) ?></label>
                        <?php endforeach; ?>
                      </div>
                    </fieldset>
                    <small class="text-muted">Administrative accounts are provisioned by NHRE administration.</small>
                  </div>
                </div>

                <div class="form-check auth-terms mt-4">
                  <input class="form-check-input" type="checkbox" value="1" id="terms" required>
                  <label class="form-check-label" for="terms">I agree to the <a href="terms_conditions.php">Terms of Service</a> and <a href="privacy_policy.php">Privacy Policy</a></label>
                  <div class="invalid-feedback">Please accept the terms to continue.</div>
                </div>

                <button type="submit" class="btn btn-auth-primary ripple w-100 mt-4">
                  <span>Create Account</span>
                  <i class="fa-solid fa-user-plus"></i>
                </button>
              </form>

              <p class="auth-switch">Already registered? <a href="login.php">Login here</a></p>
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
