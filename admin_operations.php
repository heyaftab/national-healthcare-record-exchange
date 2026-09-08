<?php
declare(strict_types=1);

require_once __DIR__ . '/auth/auth_check.php';
require_role(['Hospital Admin', 'System Admin']);
require_once __DIR__ . '/includes/pharmacy_functions.php';

$role = (string) $_SESSION['role'];
$errors = session_pull('errors', []);
$success = session_pull('success');
$view = (string) ($_GET['view'] ?? 'reports');
$views = [
    'departments' => ['Departments', ['Hospital Admin']],
    'medical-records' => ['Medical Records', ['Hospital Admin', 'System Admin']],
    'prescriptions' => ['Prescriptions', ['Hospital Admin', 'System Admin']],
    'access-requests' => ['Access Requests', ['Hospital Admin']],
    'reports' => ['Reports & Analytics', ['Hospital Admin', 'System Admin']],
    'audit-logs' => ['Audit Logs', ['Hospital Admin', 'System Admin']],
    'user-management' => ['User Management', ['System Admin']],
    'organizations' => ['Healthcare Organizations', ['System Admin']],
    'access-overview' => ['Access Permissions', ['System Admin']],
    'system-statistics' => ['System Statistics', ['System Admin']],
    'settings' => ['System Settings', ['System Admin']],
];
if (!isset($views[$view]) || !in_array($role, $views[$view][1], true)) {
    $_SESSION['errors'] = ['You do not have permission to access that workspace.'];
    redirect('dashboard.php');
}
$title = $views[$view][0];
$rows = [];
$columns = [];
$metrics = [];
try {
    $pdo = db();
    ensure_clinical_tables(); ensure_access_tables_exists(); ensure_pharmacy_tables();
    $hospitalId = null;
    if ($role === 'Hospital Admin') {
        $s = $pdo->prepare('SELECT hospital_id FROM users WHERE id = ?'); $s->execute([(int) $_SESSION['user_id']]);
        $hospitalId = (int) $s->fetchColumn();
        if ($hospitalId < 1) throw new RuntimeException('Your administrator account is not assigned to a hospital.');
    }
    if ($view === 'departments') {
        $columns = ['Service', 'Assigned staff', 'Open appointments'];
        $s = $pdo->prepare("SELECT COALESCE(sp.name, 'General practice') service, COUNT(DISTINCT u.id) staff, COUNT(DISTINCT a.appointment_id) appointments FROM users u LEFT JOIN specializations sp ON sp.id=u.specialization_id LEFT JOIN appointments a ON a.doctor_id=u.id AND a.status IN ('Pending','Approved','Confirmed') WHERE u.hospital_id=? AND u.role='Doctor' GROUP BY sp.id, sp.name ORDER BY service"); $s->execute([$hospitalId]); $rows = $s->fetchAll();
    } elseif ($view === 'medical-records') {
        $columns = ['Patient', 'Documents', 'Active allergies', 'Last update'];
        $where = $hospitalId ? ' WHERE p.hospital_id = ?' : ''; $s = $pdo->prepare("SELECT p.fullname, COUNT(DISTINCT md.id) documents, COUNT(DISTINCT al.id) allergies, MAX(COALESCE(md.created_at, al.created_at, p.created_at)) updated FROM users p LEFT JOIN medical_documents md ON md.patient_id=p.id LEFT JOIN allergies al ON al.patient_id=p.id AND al.is_active=1$where GROUP BY p.id ORDER BY updated DESC LIMIT 100"); $s->execute($hospitalId ? [$hospitalId] : []); $rows=$s->fetchAll();
    } elseif ($view === 'prescriptions') {
        $columns=['Prescription','Patient','Doctor','Status','Created']; $where=$hospitalId ? ' WHERE d.hospital_id=?' : ''; $s=$pdo->prepare("SELECT p.prescription_no, pt.fullname patient, d.fullname doctor, p.status, p.created_at FROM prescriptions p JOIN users pt ON pt.id=p.patient_id JOIN users d ON d.id=p.doctor_id$where ORDER BY p.created_at DESC LIMIT 100"); $s->execute($hospitalId ? [$hospitalId] : []); $rows=$s->fetchAll();
    } elseif ($view === 'access-requests' || $view === 'access-overview') {
        $columns=['Patient','Provider','Role','Status','Expires']; $where=$hospitalId ? ' WHERE provider.hospital_id=?' : ''; $s=$pdo->prepare("SELECT patient.fullname patient, provider.fullname provider, ap.provider_role role, ap.status, ap.expires_at expires FROM access_permissions ap JOIN users patient ON patient.id=ap.patient_id JOIN users provider ON provider.id=ap.provider_id$where ORDER BY ap.created_at DESC LIMIT 100"); $s->execute($hospitalId ? [$hospitalId] : []); $rows=$s->fetchAll();
    } elseif ($view === 'audit-logs') {
        $columns=['When','Actor','Role','Action','Details']; $where=$hospitalId ? ' WHERE u.hospital_id=?' : ''; $s=$pdo->prepare("SELECT a.created_at, COALESCE(u.fullname,'System') actor, COALESCE(a.user_role,'—') role, a.action, COALESCE(a.details,'—') details FROM audit_logs a LEFT JOIN users u ON u.id=a.user_id$where ORDER BY a.created_at DESC LIMIT 100"); $s->execute($hospitalId ? [$hospitalId] : []); $rows=$s->fetchAll();
    } elseif ($view === 'user-management') {
        $columns=['Name','Email','Role','Hospital','Joined','Action']; $rows=$pdo->query('SELECT u.id account_id,u.fullname name,u.email,u.role,COALESCE(h.name,\'—\') hospital,u.created_at joined FROM users u LEFT JOIN hospitals h ON h.id=u.hospital_id ORDER BY u.created_at DESC LIMIT 150')->fetchAll();
    } elseif ($view === 'organizations') {
        $columns=['Hospital','District','Accounts','Status']; $rows=$pdo->query('SELECT h.name hospital,COALESCE(d.name,\'—\') district,COUNT(u.id) accounts,IF(h.is_active=1,\'Active\',\'Inactive\') status FROM hospitals h LEFT JOIN districts d ON d.id=h.district_id LEFT JOIN users u ON u.hospital_id=h.id GROUP BY h.id ORDER BY h.name')->fetchAll();
    } elseif ($view === 'settings') {
        $columns=['Setting','Value']; $rows=[['Setting'=>'Roles supported','Value'=>implode(', ', valid_roles())],['Setting'=>'Session lifetime','Value'=>SESSION_LIFETIME . ' seconds'],['Setting'=>'Login attempt limit','Value'=>(string) LOGIN_ATTEMPT_LIMIT],['Setting'=>'Reminder','Value'=>'Environment credentials must be configured outside the web root before deployment.']];
    } else {
        $scope = $hospitalId ? ' WHERE hospital_id=?' : ''; $s=$pdo->prepare("SELECT role, COUNT(*) total FROM users$scope GROUP BY role"); $s->execute($hospitalId ? [$hospitalId] : []); $metrics=$s->fetchAll();
        $columns=['Metric','Count'];
        if ($hospitalId) {
            $reportQueries = [
                ['Appointments', 'SELECT COUNT(*) FROM appointments a JOIN users d ON d.id=a.doctor_id WHERE d.hospital_id=?'],
                ['Medical documents', 'SELECT COUNT(*) FROM medical_documents m JOIN users p ON p.id=m.patient_id WHERE p.hospital_id=?'],
                ['Access permissions', 'SELECT COUNT(*) FROM access_permissions ap JOIN users provider ON provider.id=ap.provider_id WHERE provider.hospital_id=?'],
            ];
            foreach ($reportQueries as [$label, $sql]) { $q=$pdo->prepare($sql); $q->execute([$hospitalId]); $rows[]=['Metric'=>$label,'Count'=>$q->fetchColumn()]; }
        } else {
            foreach ([['Appointments', 'appointments'], ['Medical documents', 'medical_documents'], ['Access permissions', 'access_permissions']] as [$label,$table]) { $q=$pdo->query("SELECT COUNT(*) FROM $table"); $rows[]=['Metric'=>$label,'Count'=>$q->fetchColumn()]; }
        }
    }
} catch (Throwable $e) { $error = 'This workspace could not be loaded. Please verify the database setup and try again.'; }
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= e($title) ?> - NHRE</title><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"><link rel="stylesheet" href="assets/css/styles.css"></head><body class="dashboard-body"><?php require __DIR__.'/includes/sidebar.php'; require __DIR__.'/includes/topnav.php'; ?><main class="dashboard-main"><section class="container"><div class="dashboard-hero glass-card"><div><span class="auth-kicker">Administration workspace</span><h1><?= e($title) ?></h1><p>Role-scoped operational information for <?= e($role) ?>.</p></div></div><?php if(isset($error)): ?><div class="alert alert-danger mt-4"><?= e($error) ?></div><?php else: ?><?php if($metrics): ?><div class="row g-3 mt-2"><?php foreach($metrics as $metric): ?><div class="col-md-3"><article class="dashboard-card"><span class="text-muted"><?= e($metric['role']) ?></span><h2><?= (int)$metric['total'] ?></h2></article></div><?php endforeach; ?></div><?php endif; ?><article class="dashboard-card mt-4"><div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><?php foreach($columns as $column): ?><th><?= e($column) ?></th><?php endforeach; ?></tr></thead><tbody><?php foreach($rows as $row): ?><tr><?php foreach($row as $key => $value): ?><?php if($key !== 'account_id'): ?><td><?= e($value ?? '—') ?></td><?php endif; ?><?php endforeach; ?><?php if($view === 'user-management'): ?><td class="text-end"><?php if((int)$row['account_id'] === (int)$_SESSION['user_id']): ?><span class="text-muted small">Current account</span><?php else: ?><form action="auth/system_account_delete_process.php" method="post" onsubmit="return confirm('Remove this account and its related data? This cannot be undone.');"><input type="hidden" name="_csrf" value="<?= csrf_token() ?>"><input type="hidden" name="account_id" value="<?= (int)$row['account_id'] ?>"><button class="btn btn-sm btn-outline-danger" type="submit">Remove</button></form><?php endif; ?></td><?php endif; ?></tr><?php endforeach; ?><?php if(!$rows): ?><tr><td colspan="<?= count($columns) ?>" class="text-center text-muted">No records are available yet.</td></tr><?php endif; ?></tbody></table></div></article><?php endif; ?></section></main><script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script><script src="assets/js/app.js"></script></body></html>
