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
    'reports' => ['Reports', ['Hospital Admin', 'System Admin']],
    'audit-logs' => ['Audit Logs', ['Hospital Admin', 'System Admin']],
    'user-management' => ['Users', ['System Admin']],
    'users' => ['Users', ['System Admin']],
    'organizations' => ['Organizations', ['System Admin']],
    'roles-permissions' => ['Roles & Permissions', ['System Admin']],
    'patients' => ['Patients', ['System Admin']],
    'data-exchange' => ['Data Exchange', ['System Admin']],
    'access-overview' => ['Access Permissions', ['System Admin']],
    'system-statistics' => ['System Statistics', ['System Admin']],
    'settings' => ['Settings', ['System Admin']],
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
    } elseif ($view === 'user-management' || $view === 'users') {
        $columns=['Name','Email','Role','Hospital','Joined','Action']; $rows=$pdo->query('SELECT u.id account_id,u.fullname name,u.email,u.role,COALESCE(h.name,\'—\') hospital,u.created_at joined FROM users u LEFT JOIN hospitals h ON h.id=u.hospital_id ORDER BY u.created_at DESC LIMIT 150')->fetchAll();
    } elseif ($view === 'organizations') {
        $columns=['Hospital','District','Accounts','Status']; $rows=$pdo->query('SELECT h.name hospital,COALESCE(d.name,\'—\') district,COUNT(u.id) accounts,IF(h.is_active=1,\'Active\',\'Inactive\') status FROM hospitals h LEFT JOIN districts d ON d.id=h.district_id LEFT JOIN users u ON u.hospital_id=h.id GROUP BY h.id ORDER BY h.name')->fetchAll();
    } elseif ($view === 'roles-permissions') {
        $columns=['Role','Permissions','Access Scope','Audit Mode'];
        $rows = [
            ['Role'=>'System Admin','Permissions'=>'Manage users, organizations, settings, governance policies','Access Scope'=>'Platform-wide','Audit Mode'=>'Mandatory'],
            ['Role'=>'Hospital Admin','Permissions'=>'Manage local staff, appointments, hospital records, operational policies','Access Scope'=>'Hospital only','Audit Mode'=>'Mandatory'],
            ['Role'=>'Doctor','Permissions'=>'View/manage assigned patients and clinical documentation','Access Scope'=>'Clinical care context','Audit Mode'=>'Mandatory'],
            ['Role'=>'Patient','Permissions'=>'View own records, consented access, appointments','Access Scope'=>'Own data and consented sharing','Audit Mode'=>'Audit logging'],
            ['Role'=>'Pharmacist','Permissions'=>'Dispense medicines, stock workflows, approved prescriptions','Access Scope'=>'Medication scope only','Audit Mode'=>'Mandatory'],
            ['Role'=>'Lab Technician','Permissions'=>'Process lab orders, upload reports, maintain test history','Access Scope'=>'Laboratory scope only','Audit Mode'=>'Mandatory'],
        ];
    } elseif ($view === 'patients') {
        $columns=['Patient','Email','District','Status','Last Update'];
        $rows=$pdo->query('SELECT u.fullname patient, u.email, COALESCE(u.district, \'—\') district, \'Active\' status, COALESCE(MAX(a.created_at), u.created_at) last_update FROM users u LEFT JOIN appointments a ON a.patient_id=u.id WHERE u.role=\'Patient\' GROUP BY u.id ORDER BY u.fullname LIMIT 100')->fetchAll();
    } elseif ($view === 'data-exchange' || $view === 'access-overview') {
        $columns=['Patient','Provider','Role','Status','Expires']; $where=''; $s=$pdo->prepare("SELECT patient.fullname patient, provider.fullname provider, ap.provider_role role, ap.status, ap.expires_at expires FROM access_permissions ap JOIN users patient ON patient.id=ap.patient_id JOIN users provider ON provider.id=ap.provider_id ORDER BY ap.created_at DESC LIMIT 100"); $s->execute(); $rows=$s->fetchAll();
    } elseif ($view === 'settings') {
        $columns=['Category','Purpose','Status'];
        $rows=[
            ['Category'=>'General','Purpose'=>'Core platform metadata, organization defaults, naming conventions','Status'=>'Configured'],
            ['Category'=>'Security','Purpose'=>'Passwords, MFA, login policies, session hardening','Status'=>'Configured'],
            ['Category'=>'Privacy & Consent','Purpose'=>'Consent rules, data sharing, legal frameworks, patient permissions','Status'=>'Configured'],
            ['Category'=>'Access Control','Purpose'=>'Role-based access, emergency-access controls, approval workflows','Status'=>'Configured'],
            ['Category'=>'Healthcare Organizations','Purpose'=>'Hospital admin settings, district mappings, partner onboarding','Status'=>'Configured'],
            ['Category'=>'Medical Terminology','Purpose'=>'Reference coding, SNOMED/ICD mappings and clinical vocabulary consistency','Status'=>'Configured'],
            ['Category'=>'Integrations','Purpose'=>'API, interoperability, external system connectivity, pull/push rules','Status'=>'Configured'],
            ['Category'=>'Notifications','Purpose'=>'Alerts, reminders, escalation workflows, policy delivery','Status'=>'Configured'],
            ['Category'=>'Emergency Access','Purpose'=>'Time-bound emergency override controls and mandatory audit review','Status'=>'Configured'],
            ['Category'=>'Backup & Recovery','Purpose'=>'Restore windows, retention strategy, system continuity','Status'=>'Configured'],
            ['Category'=>'Data & Reporting','Purpose'=>'Operational metrics, exports, health reporting, governance dashboards','Status'=>'Configured'],
            ['Category'=>'Localization','Purpose'=>'Language, region and date formatting for a national-scale deployment','Status'=>'Configured'],
            ['Category'=>'Maintenance','Purpose'=>'Scheduled tasks, upgrades, downtime windows, service health checks','Status'=>'Configured'],
            ['Category'=>'Policies','Purpose'=>'Data governance, privacy policies, and operating standards across the exchange','Status'=>'Configured'],
        ];
        $settingsCards = [
            ['title' => 'General', 'text' => 'Platform metadata, defaults, institution naming, and core registry configuration.', 'icon' => 'fa-gear'],
            ['title' => 'Security', 'text' => 'Authentication rules, session controls, audit strength, and security posture thresholds.', 'icon' => 'fa-shield-halved'],
            ['title' => 'Privacy & Consent', 'text' => 'Patient permissions, record-sharing rules, legal policy controls, and consent workflows.', 'icon' => 'fa-user-lock'],
            ['title' => 'Access Control', 'text' => 'Role definitions, approval chains, emergency override policy, and least-privilege enforcement.', 'icon' => 'fa-key'],
            ['title' => 'Healthcare Organizations', 'text' => 'Hospital onboarding, district mapping, operational configuration, and ownership boundaries.', 'icon' => 'fa-building'],
            ['title' => 'Medical Terminology', 'text' => 'Clinical terminology, coding standards, and consistency rules for interoperability.', 'icon' => 'fa-stethoscope'],
            ['title' => 'Integrations', 'text' => 'API and partner integration settings, trust configuration, and exchange rules.', 'icon' => 'fa-plug'],
            ['title' => 'Notifications', 'text' => 'Alerts, reminder schedules, escalation messages, and communication workflows.', 'icon' => 'fa-bell'],
            ['title' => 'Emergency Access', 'text' => 'Temporary emergency override handling with mandatory review and audit constraints.', 'icon' => 'fa-triangle-exclamation'],
            ['title' => 'Backup & Recovery', 'text' => 'Restore windows, retention policies, continuity planning, and disaster recovery guardrails.', 'icon' => 'fa-hard-drive'],
            ['title' => 'Data & Reporting', 'text' => 'Metrics exports, analytics configuration, and event governance reporting.', 'icon' => 'fa-chart-line'],
            ['title' => 'Localization', 'text' => 'Regional language, date formats, and national deployment conventions.', 'icon' => 'fa-language'],
            ['title' => 'Maintenance', 'text' => 'Scheduled maintenance windows, deployment policy, and service health controls.', 'icon' => 'fa-wrench'],
            ['title' => 'Policies', 'text' => 'Governance standards, data handling policy, and operational requirements for the exchange.', 'icon' => 'fa-file-contract'],
        ];
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
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= e($title) ?> - NHRE</title><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"><link rel="stylesheet" href="assets/css/styles.css"></head><body class="dashboard-body"><?php require __DIR__.'/includes/sidebar.php'; require __DIR__.'/includes/topnav.php'; ?><main class="dashboard-main"><section class="container"><div class="dashboard-hero glass-card"><div><span class="auth-kicker">Administration workspace</span><h1><?= e($title) ?></h1><p>Role-scoped operational information for <?= e($role) ?>.</p></div></div><?php if(isset($error)): ?><div class="alert alert-danger mt-4"><?= e($error) ?></div><?php else: ?><?php if($metrics): ?><div class="row g-3 mt-2"><?php foreach($metrics as $metric): ?><div class="col-md-3"><article class="dashboard-card"><span class="text-muted"><?= e($metric['role']) ?></span><h2><?= (int)$metric['total'] ?></h2></article></div><?php endforeach; ?></div><?php endif; ?><?php if ($view === 'settings' && isset($settingsCards)): ?><section class="mt-4"><div class="d-flex justify-content-between align-items-center mb-3"><div><h2 class="h5 fw-bold mb-0">Platform settings domains</h2><p class="text-muted mb-0">Governance and control areas for the NHRE exchange.</p></div><span class="badge text-bg-light border"><?= count($settingsCards) ?> categories</span></div><div class="row g-3"><?php foreach ($settingsCards as $card): ?><div class="col-lg-4 col-md-6"><article class="dashboard-card h-100"><div class="d-flex justify-content-between align-items-start mb-3"><div class="dashboard-card-icon"><i class="fa-solid <?= e($card['icon']) ?>"></i></div><span class="badge text-bg-light border">Configured</span></div><h3 class="h6 fw-bold mb-2"><?= e($card['title']) ?></h3><p class="text-muted mb-0"><?= e($card['text']) ?></p></article></div><?php endforeach; ?></div></section><?php else: ?><article class="dashboard-card mt-4"><div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><?php foreach($columns as $column): ?><th><?= e($column) ?></th><?php endforeach; ?></tr></thead><tbody><?php foreach($rows as $row): ?><tr><?php foreach($row as $key => $value): ?><?php if($key !== 'account_id'): ?><td><?= e($value ?? '—') ?></td><?php endif; ?><?php endforeach; ?><?php if($view === 'user-management'): ?><td class="text-end"><?php if((int)$row['account_id'] === (int)$_SESSION['user_id']): ?><span class="text-muted small">Current account</span><?php else: ?><form action="auth/system_account_delete_process.php" method="post" onsubmit="return confirm('Remove this account and its related data? This cannot be undone.');"><input type="hidden" name="_csrf" value="<?= csrf_token() ?>"><input type="hidden" name="account_id" value="<?= (int)$row['account_id'] ?>"><button class="btn btn-sm btn-outline-danger" type="submit">Remove</button></form><?php endif; ?></td><?php endif; ?></tr><?php endforeach; ?><?php if(!$rows): ?><tr><td colspan="<?= count($columns) ?>" class="text-center text-muted">No records are available yet.</td></tr><?php endif; ?></tbody></table></div></article><?php endif; ?><?php endif; ?></section></main><script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script><script src="assets/js/app.js"></script></body></html>
