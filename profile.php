<?php
require_once __DIR__ . '/auth/auth_check.php';
require_auth();

$errors = session_pull('errors', []);
$success = session_pull('success');
$old = session_pull('old', []);

$user_id = (int)($_SESSION['user_id'] ?? 0);
$stmt = db()->prepare('SELECT fullname, email, phone, nid, account_number, date_of_birth, nationality, gender, address, emergency_contact, blood_group, marital_status, occupation, profile_photo, role FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (is_array($user) && empty($user['account_number'])) {
    $assigned_account_number = 'NHRE-' . str_pad((string)$user_id, 8, '0', STR_PAD_LEFT);
    $assign_stmt = db()->prepare("UPDATE users SET account_number = ? WHERE id = ? AND (account_number IS NULL OR account_number = '')");
    $assign_stmt->execute([$assigned_account_number, $user_id]);
    $user['account_number'] = $assigned_account_number;
}

$profile = is_array($user) ? $user : [];
$profile = array_merge($profile, is_array($old) ? $old : []);
$fullname = $profile['fullname'] ?? ($_SESSION['fullname'] ?? 'NHRE User');
$email = $profile['email'] ?? ($_SESSION['email'] ?? '');
$role = $profile['role'] ?? ($_SESSION['role'] ?? 'User');
$phone = $profile['phone'] ?? '';
$nid = $profile['nid'] ?? '';
$account_number = $profile['account_number'] ?? '';
$date_of_birth = '';
if (!empty($profile['date_of_birth'])) {
    try {
        $date_of_birth = (new DateTimeImmutable((string)$profile['date_of_birth']))->format('d M Y');
    } catch (Exception $e) {
        $date_of_birth = (string)$profile['date_of_birth'];
    }
}
$nationality = $profile['nationality'] ?? '';
$gender = $profile['gender'] ?? '';
$address = $profile['address'] ?? '';
$emergency_contact = $profile['emergency_contact'] ?? '';
$blood_group = $profile['blood_group'] ?? '';
$marital_status = $profile['marital_status'] ?? '';
$occupation = $profile['occupation'] ?? '';
$profile_photo = $profile['profile_photo'] ?? '';
$resolved_avatar = user_avatar_url((string)$profile_photo, $user_id, (string)$role);
$cartoon_avatar_options = [
    'kind-caregiver' => 'Kind caregiver',
    'bright-clinician' => 'Bright clinician',
    'friendly-helper' => 'Friendly helper',
    'calm-specialist' => 'Calm specialist',
    'happy-neighbor' => 'Happy neighbor',
    'trusted-guide' => 'Trusted guide',
];

function profile_cartoon_avatar_url(string $seed): string
{
    return 'https://api.dicebear.com/9.x/avataaars/svg?seed=' . rawurlencode($seed)
        . '&backgroundColor=b6e3f4,c0aede,d1d4f9&radius=50';
}

function render_profile_value(mixed $value): string
{
    if ($value === null || $value === '') {
        return '<span class="text-muted">Not provided</span>';
    }

    return e($value);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Profile - NHRE</title>
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
        <a href="dashboard.php" class="btn btn-dashboard-logout ripple">
          <i class="fa-solid fa-house"></i>
          <span>Dashboard</span>
        </a>
        <a href="logout.php" class="btn btn-dashboard-logout ripple">
          <i class="fa-solid fa-arrow-right-from-bracket"></i>
          <span>Logout</span>
        </a>
      </div>
    </div>
  </nav>

  <main class="dashboard-main">
    <section class="container">
      <div class="dashboard-hero glass-card">
        <div class="d-flex align-items-center gap-3">
          <div class="profile-avatar-wrapper">
            <img src="<?= e($resolved_avatar) ?>" alt="Profile photo" class="profile-avatar">
          </div>
          <div>
            <span class="auth-kicker">User Profile</span>
            <h1><?= e($fullname) ?></h1>
            <p>Role: <strong><?= e($role) ?></strong></p>
          </div>
        </div>
        <div class="dashboard-user-pill">
          <i class="fa-solid fa-circle-user"></i>
          <span><?= e($email) ?></span>
        </div>
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

      <?php if ($success): ?>
        <div class="alert alert-success auth-alert" role="alert">
          <i class="fa-solid fa-circle-check"></i>
          <span><?= e($success) ?></span>
        </div>
      <?php endif; ?>

      <div class="row g-4">
        <div class="col-lg-4">
          <article class="dashboard-card text-center">
            <div class="dashboard-card-icon mx-auto"><i class="fa-solid fa-user-check"></i></div>
            <h2>Account Summary</h2>
            <p>Verified NHRE account details and access level.</p>
            <div class="mt-3">
              <span class="badge bg-success-subtle text-success-emphasis px-3 py-2">Active Account</span>
            </div>
            <div class="mt-4 text-start">
              <div class="small text-muted mb-2">Account number</div>
              <div class="fw-semibold"><?= e($account_number) ?></div>
            </div>
            <div class="profile-summary-photo mt-4">
              <img src="<?= e($resolved_avatar) ?>" alt="Profile photo">
            </div>
            <div class="cartoon-avatar-picker mt-4 text-start">
              <h3 class="h6 mb-1">Choose a cartoon profile picture</h3>
              <p class="small text-muted mb-3">Select an avatar that feels like you, then save your choice.</p>
              <form action="auth/profile_avatar_process.php" method="POST">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <div class="cartoon-avatar-options">
                  <?php foreach ($cartoon_avatar_options as $seed => $label): ?>
                    <?php $avatar_url = profile_cartoon_avatar_url($seed); ?>
                    <label class="cartoon-avatar-option" title="<?= e($label) ?>">
                      <input type="radio" name="cartoon_avatar" value="<?= e($seed) ?>" <?= $profile_photo === $avatar_url ? 'checked' : '' ?>>
                      <img src="<?= e($avatar_url) ?>" alt="<?= e($label) ?> cartoon avatar">
                      <span class="visually-hidden"><?= e($label) ?></span>
                    </label>
                  <?php endforeach; ?>
                </div>
                <button type="submit" class="btn btn-outline-primary btn-sm w-100 mt-3">
                  <i class="fa-solid fa-wand-magic-sparkles me-1"></i>Save cartoon picture
                </button>
              </form>
            </div>
          </article>
        </div>

        <div class="col-lg-8">
          <article class="dashboard-card">
            <div class="dashboard-card-icon"><i class="fa-solid fa-id-card"></i></div>
            <h2>Modify Profile</h2>
            <p class="text-muted mb-3">Update your personal and contact details below.</p>
            <form action="auth/profile_update_process.php" method="POST" enctype="multipart/form-data" class="auth-form">
              <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
              <div class="profile-photo-card">
                <div class="profile-photo-preview">
                  <img src="<?= e($resolved_avatar) ?>" alt="Current profile photo">
                </div>
                <div>
                  <label for="profile_photo" class="btn btn-auth-primary ripple">
                    <i class="fa-solid fa-upload"></i>
                    <span>Choose photo</span>
                  </label>
                  <input type="file" id="profile_photo" name="profile_photo" class="d-none" accept="image/png,image/jpeg,image/jpg,image/webp,image/gif">
                  <div class="form-text mt-2">PNG, JPG, WEBP, or GIF up to 2 MB.</div>
                </div>
              </div>
              <div class="row g-3">
                <div class="col-md-6">
                  <div class="form-floating">
                    <input type="text" class="form-control" id="fullname" name="fullname" placeholder="Full Name" value="<?= e($profile['fullname'] ?? '') ?>" required maxlength="150">
                    <label for="fullname"><i class="fa-solid fa-user"></i> Full Name</label>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-floating">
                    <input type="email" class="form-control" id="email" name="email" placeholder="Email" value="<?= e($profile['email'] ?? '') ?>" required maxlength="190">
                    <label for="email"><i class="fa-solid fa-envelope"></i> Email Address</label>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-floating">
                    <input type="tel" class="form-control" id="phone" name="phone" placeholder="Phone Number" value="<?= e($profile['phone'] ?? '') ?>" required pattern="^\+?[0-9][0-9\s().-]{7,19}$">
                    <label for="phone"><i class="fa-solid fa-phone"></i> Phone Number</label>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-floating">
                    <input type="text" class="form-control" id="account_number" value="<?= e($account_number) ?>" readonly>
                    <label for="account_number"><i class="fa-solid fa-hashtag"></i> NHRE Account Number</label>
                  </div>
                  <small class="text-muted">Assigned and managed by NHRE.</small>
                </div>
                <div class="col-md-6">
                  <div class="form-floating">
                    <input type="text" class="form-control" id="nid" name="nid" placeholder="NID" value="<?= e($profile['nid'] ?? '') ?>" readonly>
                    <label for="nid"><i class="fa-solid fa-id-card"></i> National ID (NID)</label>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-floating">
                    <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" value="<?= e($profile['date_of_birth'] ?? '') ?>">
                    <label for="date_of_birth"><i class="fa-solid fa-calendar-day"></i> Date of Birth</label>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-floating">
                    <input type="text" class="form-control" id="nationality" name="nationality" placeholder="Nationality" value="<?= e($profile['nationality'] ?? '') ?>">
                    <label for="nationality"><i class="fa-solid fa-globe"></i> Nationality</label>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-floating">
                    <select class="form-select" id="gender" name="gender">
                      <option value="" <?= (($profile['gender'] ?? '') === '') ? 'selected' : '' ?>>Select gender</option>
                      <option value="Male" <?= (($profile['gender'] ?? '') === 'Male') ? 'selected' : '' ?>>Male</option>
                      <option value="Female" <?= (($profile['gender'] ?? '') === 'Female') ? 'selected' : '' ?>>Female</option>
                      <option value="Other" <?= (($profile['gender'] ?? '') === 'Other') ? 'selected' : '' ?>>Other</option>
                    </select>
                    <label for="gender"><i class="fa-solid fa-venus-mars"></i> Gender</label>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-floating">
                    <select class="form-select" id="blood_group" name="blood_group">
                      <option value="" <?= (($profile['blood_group'] ?? '') === '') ? 'selected' : '' ?>>Select blood group</option>
                      <option value="A+" <?= (($profile['blood_group'] ?? '') === 'A+') ? 'selected' : '' ?>>A+</option>
                      <option value="A-" <?= (($profile['blood_group'] ?? '') === 'A-') ? 'selected' : '' ?>>A-</option>
                      <option value="B+" <?= (($profile['blood_group'] ?? '') === 'B+') ? 'selected' : '' ?>>B+</option>
                      <option value="B-" <?= (($profile['blood_group'] ?? '') === 'B-') ? 'selected' : '' ?>>B-</option>
                      <option value="AB+" <?= (($profile['blood_group'] ?? '') === 'AB+') ? 'selected' : '' ?>>AB+</option>
                      <option value="AB-" <?= (($profile['blood_group'] ?? '') === 'AB-') ? 'selected' : '' ?>>AB-</option>
                      <option value="O+" <?= (($profile['blood_group'] ?? '') === 'O+') ? 'selected' : '' ?>>O+</option>
                      <option value="O-" <?= (($profile['blood_group'] ?? '') === 'O-') ? 'selected' : '' ?>>O-</option>
                    </select>
                    <label for="blood_group"><i class="fa-solid fa-droplet"></i> Blood Group</label>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-floating">
                    <select class="form-select" id="marital_status" name="marital_status">
                      <option value="" <?= (($profile['marital_status'] ?? '') === '') ? 'selected' : '' ?>>Select status</option>
                      <option value="Single" <?= (($profile['marital_status'] ?? '') === 'Single') ? 'selected' : '' ?>>Single</option>
                      <option value="Married" <?= (($profile['marital_status'] ?? '') === 'Married') ? 'selected' : '' ?>>Married</option>
                      <option value="Divorced" <?= (($profile['marital_status'] ?? '') === 'Divorced') ? 'selected' : '' ?>>Divorced</option>
                      <option value="Widowed" <?= (($profile['marital_status'] ?? '') === 'Widowed') ? 'selected' : '' ?>>Widowed</option>
                    </select>
                    <label for="marital_status"><i class="fa-solid fa-ring"></i> Marital Status</label>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-floating">
                    <input type="text" class="form-control" id="occupation" name="occupation" placeholder="Occupation" value="<?= e($profile['occupation'] ?? '') ?>">
                    <label for="occupation"><i class="fa-solid fa-briefcase"></i> Occupation</label>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-floating">
                    <input type="text" class="form-control" id="emergency_contact" name="emergency_contact" placeholder="Emergency Contact" value="<?= e($profile['emergency_contact'] ?? '') ?>">
                    <label for="emergency_contact"><i class="fa-solid fa-phone-volume"></i> Emergency Contact</label>
                  </div>
                </div>
                <div class="col-12">
                  <div class="form-floating">
                    <textarea class="form-control" id="address" name="address" placeholder="Address" style="min-height: 110px;"><?= e($profile['address'] ?? '') ?></textarea>
                    <label for="address"><i class="fa-solid fa-location-dot"></i> Address</label>
                  </div>
                </div>
                <div class="col-12 d-flex justify-content-end">
                  <button type="submit" class="btn btn-auth-primary ripple">
                    <i class="fa-solid fa-floppy-disk"></i>
                    <span>Save Changes</span>
                  </button>
                </div>
              </div>
            </form>
          </article>
        </div>
      </div>
    </section>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/app.js?v=20260818-10"></script>
</body>
</html>
