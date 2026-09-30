<?php
require_once __DIR__ . '/auth/auth_check.php';
require_auth();

$fullname = $_SESSION['fullname'] ?? 'NHRE User';
$email = $_SESSION['email'] ?? '';
$role = $_SESSION['role'] ?? 'User';
$errors = session_pull('errors', []);
$success = session_pull('success');

try {
    $userId = (int)($_SESSION['user_id'] ?? 0);
    $stmt = db()->prepare('SELECT id, blood_group, phone, district, available, last_donation_date, next_eligible_date FROM blood_donors WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $donorProfile = $stmt->fetch();

    $stmt = db()->prepare(
        'SELECT bd.id, bd.blood_group, bd.phone, bd.district, bd.available, bd.last_donation_date, bd.next_eligible_date, u.fullname, u.email
         FROM blood_donors bd
         JOIN users u ON u.id = bd.user_id
         WHERE bd.available = 1 AND bd.district = ? AND bd.user_id != ?
         ORDER BY bd.created_at DESC LIMIT 20'
    );
    $district = $donorProfile['district'] ?? '';
    $stmt->execute([$district, $userId]);
    $availableDonors = $stmt->fetchAll();

    $stmt = db()->prepare('SELECT * FROM blood_requests ORDER BY created_at DESC LIMIT 10');
    $requests = $stmt->fetchAll();
} catch (PDOException $e) {
    $donorProfile = null;
    $availableDonors = [];
    $requests = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Blood Donation - NHRE</title>
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
        <a href="dashboard.php" class="btn btn-dashboard-logout ripple"><i class="fa-solid fa-house"></i> <span>Dashboard</span></a>
        <a href="logout.php" class="btn btn-dashboard-logout ripple"><i class="fa-solid fa-arrow-right-from-bracket"></i> <span>Logout</span></a>
      </div>
    </div>
  </nav>

  <main class="dashboard-main">
    <section class="container">
      <div class="dashboard-hero glass-card">
        <div>
          <span class="auth-kicker">Blood Donation</span>
          <h1>Community blood support</h1>
          <p>Register as a donor, request blood, and let hospitals update eligibility after every donation.</p>
        </div>
        <div class="dashboard-user-pill">
          <i class="fa-solid fa-droplet"></i>
          <span><?= e($role) ?></span>
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
          <article class="dashboard-card">
            <div class="dashboard-card-icon"><i class="fa-solid fa-hand-holding-medical"></i></div>
            <h2>Register as Donor</h2>
            <p>Share your blood group, district, and phone number so patients can reach you when needed.</p>
            <form action="auth/blood_donor_process.php" method="POST" class="mt-3">
              <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
              <div class="mb-3">
                <label class="form-label">Blood Group</label>
                <select class="form-select" name="blood_group" required>
                  <option value="A+">A+</option>
                  <option value="A-">A-</option>
                  <option value="B+">B+</option>
                  <option value="B-">B-</option>
                  <option value="O+">O+</option>
                  <option value="O-">O-</option>
                  <option value="AB+">AB+</option>
                  <option value="AB-">AB-</option>
                </select>
              </div>
              <div class="mb-3">
                <label class="form-label">Phone Number</label>
                <input type="text" class="form-control" name="phone" placeholder="01XXXXXXXXX" required>
              </div>
              <div class="mb-3">
                <label class="form-label">District</label>
                <input type="text" class="form-control" name="district" placeholder="Dhaka" required>
              </div>
              <button type="submit" class="btn btn-solid-nhre w-100">Save Donor Profile</button>
            </form>
          </article>
        </div>

        <div class="col-lg-4">
          <article class="dashboard-card">
            <div class="dashboard-card-icon"><i class="fa-solid fa-bell"></i></div>
            <h2>Request Blood</h2>
            <p>Submit a blood request and see available donors in your district with phone numbers.</p>
            <form action="auth/blood_request_process.php" method="POST" class="mt-3">
              <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
              <div class="mb-3">
                <label class="form-label">Requester Name</label>
                <input type="text" class="form-control" name="requester_name" required>
              </div>
              <div class="mb-3">
                <label class="form-label">Phone Number</label>
                <input type="text" class="form-control" name="phone" required>
              </div>
              <div class="mb-3">
                <label class="form-label">Blood Group Needed</label>
                <select class="form-select" name="blood_group" required>
                  <option value="A+">A+</option>
                  <option value="A-">A-</option>
                  <option value="B+">B+</option>
                  <option value="B-">B-</option>
                  <option value="O+">O+</option>
                  <option value="O-">O-</option>
                  <option value="AB+">AB+</option>
                  <option value="AB-">AB-</option>
                </select>
              </div>
              <div class="mb-3">
                <label class="form-label">District</label>
                <input type="text" class="form-control" name="district" placeholder="Dhaka" required>
              </div>
              <div class="mb-3">
                <label class="form-label">Notes</label>
                <textarea class="form-control" rows="3" name="notes" placeholder="Patient condition or urgency"></textarea>
              </div>
              <button type="submit" class="btn btn-solid-nhre w-100">Submit Blood Request</button>
            </form>
          </article>
        </div>

        <div class="col-lg-4">
          <article class="dashboard-card">
            <div class="dashboard-card-icon"><i class="fa-solid fa-hospital-user"></i></div>
            <h2>Hospital Eligibility Updates</h2>
            <p>Hospitals can mark donors as recently donated or make them available again after recovery.</p>
            <div class="mt-3">
              <?php if ($donorProfile): ?>
                <div class="border rounded p-3 mb-3">
                  <p class="mb-1"><strong>Your donor status</strong></p>
                  <p class="mb-1">Blood group: <?= e($donorProfile['blood_group']) ?></p>
                  <p class="mb-1">Phone: <?= e($donorProfile['phone']) ?></p>
                  <p class="mb-1">District: <?= e($donorProfile['district']) ?></p>
                  <p class="mb-1">Available: <?= (int)$donorProfile['available'] === 1 ? 'Yes' : 'No' ?></p>
                  <?php if (!empty($donorProfile['next_eligible_date'])): ?>
                    <p class="mb-0">Next eligible: <?= e($donorProfile['next_eligible_date']) ?></p>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
              <?php if ($role === 'Hospital Admin'): ?>
                <form action="auth/hospital_blood_update_process.php" method="POST">
                  <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                  <div class="mb-3">
                    <label class="form-label">Donor ID</label>
                    <input type="number" class="form-control" name="donor_id" placeholder="Enter donor ID" required>
                  </div>
                  <div class="mb-3">
                    <label class="form-label">Update</label>
                    <select class="form-select" name="action" required>
                      <option value="mark_donated">Mark as donated (block for 4 months)</option>
                      <option value="mark_available">Mark as available again</option>
                    </select>
                  </div>
                  <button type="submit" class="btn btn-solid-nhre w-100">Update Donor</button>
                </form>
              <?php else: ?>
                <p class="text-muted mb-0">Eligibility updates are managed by hospital administrators.</p>
              <?php endif; ?>
            </div>
          </article>
        </div>
      </div>

      <div class="row g-4 mt-2">
        <div class="col-12">
          <article class="dashboard-card">
            <div class="dashboard-card-icon"><i class="fa-solid fa-users"></i></div>
            <h2>Available Donors in Your District</h2>
            <p>Patients can see the phone numbers of eligible donors who are currently available.</p>
            <?php if ($availableDonors): ?>
              <div class="table-responsive mt-3">
                <table class="table table-hover align-middle">
                  <thead>
                    <tr>
                      <th>Name</th>
                      <th>Blood Group</th>
                      <th>District</th>
                      <th>Phone</th>
                      <th>Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($availableDonors as $donor): ?>
                      <tr>
                        <td><?= e($donor['fullname']) ?></td>
                        <td><?= e($donor['blood_group']) ?></td>
                        <td><?= e($donor['district']) ?></td>
                        <td><?= e($donor['phone']) ?></td>
                        <td><span class="badge bg-success">Available</span></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php else: ?>
              <p class="text-muted mt-3">No eligible donors are currently available in your district.</p>
            <?php endif; ?>
          </article>
        </div>
      </div>

      <div class="row g-4 mt-1">
        <div class="col-12">
          <article class="dashboard-card">
            <div class="dashboard-card-icon"><i class="fa-solid fa-clipboard-list"></i></div>
            <h2>Recent Blood Requests</h2>
            <?php if ($requests): ?>
              <div class="table-responsive mt-3">
                <table class="table table-hover align-middle">
                  <thead>
                    <tr>
                      <th>Requester</th>
                      <th>Phone</th>
                      <th>Blood Group</th>
                      <th>District</th>
                      <th>Notes</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($requests as $request): ?>
                      <tr>
                        <td><?= e($request['requester_name']) ?></td>
                        <td><?= e($request['phone']) ?></td>
                        <td><?= e($request['blood_group']) ?></td>
                        <td><?= e($request['district']) ?></td>
                        <td><?= e($request['notes']) ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php else: ?>
              <p class="text-muted mt-3">No blood requests submitted yet.</p>
            <?php endif; ?>
          </article>
        </div>
      </div>
    </section>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/app.js?v=20260818-10"></script>
</body>
</html>
