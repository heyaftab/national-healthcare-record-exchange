<?php
require_once __DIR__ . '/auth/auth_check.php';

require_auth();
ensure_clinical_tables();

$role = (string)$_SESSION['role'];
$viewer = (int)$_SESSION['user_id'];
$patientId = $role === 'Patient' ? $viewer : (int)($_GET['patient'] ?? 0);

if ($role !== 'Patient' && (!$patientId || active_access_for_record_type($patientId, $viewer, 'Medical Documents') === null)) {
    $_SESSION['errors'] = ['Active Medical Documents authorization is required to view documents.'];
    redirect('patient_search.php');
}

$errors = session_pull('errors', []);
$success = session_pull('success');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $role === 'Patient') {
    if (!csrf_check($_POST['_csrf'] ?? null)) {
        $errors[] = 'Security token expired. Please try again.';
    } elseif (empty($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Choose a document to upload.';
    } else {
        $file = $_FILES['document'];
        $allowed = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']) ?: '';
        $name = basename((string)$file['name']);
        $category = trim((string)($_POST['category'] ?? 'Other'));
        $notes = trim((string)($_POST['notes'] ?? ''));

        if (!isset($allowed[$mime])
            || (int)$file['size'] > 5 * 1024 * 1024
            || preg_match('/\.php[0-9]*$/i', $name)
            || mb_strlen($name) > 255
            || mb_strlen($notes) > 2000
        ) {
            $errors[] = 'Upload a PDF, JPG, or PNG under 5 MB with valid details.';
        }

        if (!$errors) {
            $dir = __DIR__ . '/uploads/private_documents';
            if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
                $errors[] = 'Secure document storage is unavailable.';
            } else {
                $stored = bin2hex(random_bytes(20)) . '.' . $allowed[$mime];
                if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $stored)) {
                    $errors[] = 'Unable to save the document.';
                } else {
                    $stmt = db()->prepare(
                        'INSERT INTO medical_documents(patient_id, uploaded_by, category, original_name, stored_name, mime_type, file_size, notes, verification_status)
                         VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?)'
                    );
                    $stmt->execute([$viewer, $viewer, $category, $name, $stored, $mime, (int)$file['size'], $notes ?: null, 'Pending Verification']);
                    create_notification($viewer, 'Medical document uploaded', 'Your ' . $category . ' is awaiting verification.', 'medical_document');
                    $_SESSION['success'] = 'Document uploaded securely and is pending verification.';
                    redirect('medical_documents.php');
                }
            }
        }
    }
}

$stmt = db()->prepare('SELECT id, category, original_name, file_size, verification_status, created_at, verification_note FROM medical_documents WHERE patient_id = ? ORDER BY created_at DESC');
$stmt->execute([$patientId]);
$documents = $stmt->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Medical Documents - NHRE</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body class="dashboard-body">
<?php require __DIR__ . '/includes/sidebar.php'; require __DIR__ . '/includes/topnav.php'; ?>
<main class="dashboard-main">
  <section class="container">
    <div class="dashboard-hero glass-card">
      <div>
        <span class="auth-kicker">Private file store</span>
        <h1>Medical Documents</h1>
        <p>Files are served only after authorization and are blocked from direct folder access.</p>
      </div>
    </div>

    <?php foreach ($errors as $error): ?>
      <div class="alert alert-danger mt-3"><?= e($error) ?></div>
    <?php endforeach; ?>
    <?php if ($success): ?>
      <div class="alert alert-success mt-3"><?= e($success) ?></div>
    <?php endif; ?>

    <?php if ($role === 'Patient'): ?>
      <form method="post" enctype="multipart/form-data" class="dashboard-card mt-3">
        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
        <div class="row g-2">
          <div class="col-md-3">
            <select class="form-select" name="category">
              <option>Prescription</option>
              <option>Lab report</option>
              <option>Medical certificate</option>
              <option>Imaging report</option>
              <option>Vaccination document</option>
              <option>Other</option>
            </select>
          </div>
          <div class="col-md-5"><input class="form-control" type="file" name="document" accept="application/pdf,image/jpeg,image/png" required></div>
          <div class="col-md-4"><input class="form-control" name="notes" maxlength="2000" placeholder="Optional notes"></div>
        </div>
        <button class="btn btn-solid-nhre mt-3">Upload document</button>
      </form>
    <?php endif; ?>

    <article class="dashboard-card mt-4">
      <div class="table-responsive">
        <table class="table">
          <thead><tr><th>Name</th><th>Category</th><th>Uploaded</th><th>Status</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($documents as $document): ?>
            <tr>
              <td><?= e($document['original_name']) ?></td>
              <td><?= e($document['category']) ?></td>
              <td><?= e($document['created_at']) ?></td>
              <td><?= e($document['verification_status']) ?></td>
              <td>
                <a class="btn btn-sm btn-outline-primary" href="auth/document_download.php?id=<?= (int)$document['id'] ?>">Download</a>
                <?php if ($role === 'Doctor'): ?>
                  <form action="auth/document_verify_process.php" method="post" class="d-inline">
                    <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                    <input type="hidden" name="id" value="<?= (int)$document['id'] ?>">
                    <button name="status" value="Verified" class="btn btn-sm btn-success">Verify</button>
                    <button name="status" value="Rejected" class="btn btn-sm btn-danger">Reject</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$documents): ?>
            <tr><td colspan="5" class="text-muted">No documents found.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </article>
  </section>
</main>
<script src="assets/js/app.js?v=20260818-10"></script>
</body>
</html>
