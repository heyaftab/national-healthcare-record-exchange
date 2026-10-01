<?php
declare(strict_types=1);

require_once __DIR__ . '/auth/auth_check.php';
require_role(['Hospital Admin', 'System Admin']);
require_once __DIR__ . '/includes/pharmacy_functions.php';

$role = (string)$_SESSION['role'];
$errors = session_pull('errors', []);
$success = session_pull('success');
$view = (string)($_GET['view'] ?? 'reports');
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
$reportCards = [];
$reportChartSegments = [];
$exchangeSummary = [];
$settingsCards = [];

function admin_count(PDO $pdo, string $sql, array $params = []): int
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}

function admin_pie_path(float $start, float $end): string
{
    $cx = 100.0;
    $cy = 100.0;
    $radius = 82.0;
    $startAngle = ($start * 360.0) - 90.0;
    $endAngle = ($end * 360.0) - 90.0;
    $startRad = deg2rad($startAngle);
    $endRad = deg2rad($endAngle);
    $x1 = $cx + ($radius * cos($startRad));
    $y1 = $cy + ($radius * sin($startRad));
    $x2 = $cx + ($radius * cos($endRad));
    $y2 = $cy + ($radius * sin($endRad));
    $largeArc = ($end - $start) > 0.5 ? 1 : 0;

    return sprintf(
        'M %.3f %.3f L %.3f %.3f A %.3f %.3f 0 %d 1 %.3f %.3f Z',
        $cx,
        $cy,
        $x1,
        $y1,
        $radius,
        $radius,
        $largeArc,
        $x2,
        $y2
    );
}

function admin_chart_segments(array $items): array
{
    $palette = ['#12b9c4', '#5d7ff0', '#21b573', '#f2b84b', '#f06f7f', '#8a6df1', '#2f9fe8'];
    $items = array_values(array_filter($items, static fn(array $item): bool => (int)$item['count'] > 0));
    $total = array_sum(array_map(static fn(array $item): int => (int)$item['count'], $items));
    if ($total <= 0) {
        return [];
    }

    $segments = [];
    $cursor = 0.0;
    foreach ($items as $index => $item) {
        $value = (int)$item['count'];
        $portion = $value / $total;
        $end = min(0.9999, $cursor + $portion);
        $midAngle = (($cursor + $end) / 2.0 * 360.0) - 90.0;
        $segments[] = [
            'label' => (string)$item['label'],
            'count' => $value,
            'percent' => round($portion * 100),
            'path' => admin_pie_path($cursor, $end),
            'color' => $palette[$index % count($palette)],
            'offset_x' => round(cos(deg2rad($midAngle)) * 10.0, 2),
            'offset_y' => round(sin(deg2rad($midAngle)) * 10.0, 2),
        ];
        $cursor += $portion;
    }

    return $segments;
}

function admin_format_time(?string $value): string
{
    if ($value === null || trim($value) === '') {
        return '-';
    }

    $timestamp = strtotime($value);
    return $timestamp ? date('Y-m-d H:i', $timestamp) : $value;
}

function admin_exchange_status_class(string $status): string
{
    return match (strtolower(trim($status))) {
        'active', 'approved', 'completed' => 'admin-status-active',
        'requested', 'pending' => 'admin-status-pending',
        'revoked', 'rejected', 'cancelled' => 'admin-status-muted',
        default => 'admin-status-neutral',
    };
}

function admin_role_icon(string $role): string
{
    return match ($role) {
        'Doctor' => 'fa-user-doctor',
        'Lab Technician' => 'fa-flask-vial',
        'Pharmacist' => 'fa-pills',
        'Hospital Admin' => 'fa-hospital-user',
        default => 'fa-user-shield',
    };
}

function admin_format_metric_value(int|float|string $value): string
{
    return nhre_format_metric_value($value);
}

function admin_national_report_items(): array
{
    return nhre_national_report_items();
}

try {
    $pdo = db();
    ensure_doctor_catalog_tables();
    ensure_clinical_tables();
    ensure_access_tables_exists();
    ensure_pharmacy_tables();
    ensure_medical_test_tables_exists();
    ensure_vaccination_center_tables();
    ensure_missing_user_districts();
    ensure_realistic_exchange_data();
    ensure_realistic_report_volumes();

    $hospitalId = null;
    if ($role === 'Hospital Admin') {
        $scope = $pdo->prepare('SELECT hospital_id FROM users WHERE id = ?');
        $scope->execute([(int)$_SESSION['user_id']]);
        $hospitalId = (int)$scope->fetchColumn();
        if ($hospitalId < 1) {
            throw new RuntimeException('Your administrator account is not assigned to a hospital.');
        }
    }

    if ($view === 'departments') {
        $columns = ['Service', 'Assigned staff', 'Open appointments'];
        $stmt = $pdo->prepare(
            "SELECT COALESCE(sp.name, 'General practice') service,
                    COUNT(DISTINCT u.id) staff,
                    COUNT(DISTINCT a.appointment_id) appointments
               FROM users u
               LEFT JOIN specializations sp ON sp.id = u.specialization_id
               LEFT JOIN appointments a ON a.doctor_id = u.id AND a.status IN ('Pending','Approved','Confirmed')
              WHERE u.hospital_id = ? AND u.role = 'Doctor'
              GROUP BY sp.id, sp.name
              ORDER BY service"
        );
        $stmt->execute([$hospitalId]);
        $rows = $stmt->fetchAll();
    } elseif ($view === 'medical-records') {
        $columns = ['Patient', 'Documents', 'Active allergies', 'Last update'];
        $where = $hospitalId ? ' WHERE p.hospital_id = ?' : '';
        $stmt = $pdo->prepare(
            "SELECT p.fullname patient,
                    COUNT(DISTINCT md.id) documents,
                    COUNT(DISTINCT al.id) allergies,
                    MAX(COALESCE(md.created_at, al.created_at, p.created_at)) updated
               FROM users p
               LEFT JOIN medical_documents md ON md.patient_id = p.id
               LEFT JOIN allergies al ON al.patient_id = p.id AND al.is_active = 1
               $where
              GROUP BY p.id, p.fullname
              ORDER BY updated DESC
              LIMIT 100"
        );
        $stmt->execute($hospitalId ? [$hospitalId] : []);
        $rows = $stmt->fetchAll();
    } elseif ($view === 'prescriptions') {
        $columns = ['Prescription', 'Patient', 'Doctor', 'Status', 'Created'];
        $where = $hospitalId ? ' WHERE d.hospital_id = ?' : '';
        $stmt = $pdo->prepare(
            "SELECT p.prescription_no prescription,
                    pt.fullname patient,
                    d.fullname doctor,
                    p.status,
                    p.created_at created
               FROM prescriptions p
               JOIN users pt ON pt.id = p.patient_id
               JOIN users d ON d.id = p.doctor_id
               $where
              ORDER BY p.created_at DESC
              LIMIT 100"
        );
        $stmt->execute($hospitalId ? [$hospitalId] : []);
        $rows = $stmt->fetchAll();
    } elseif ($view === 'access-requests' || $view === 'access-overview') {
        $columns = ['Patient', 'Provider', 'Role', 'Status', 'Expires'];
        $where = $hospitalId ? ' WHERE provider.hospital_id = ?' : '';
        $stmt = $pdo->prepare(
            "SELECT patient.fullname patient,
                    provider.fullname provider,
                    ap.provider_role role,
                    ap.status,
                    ap.expires_at expires
               FROM access_permissions ap
               JOIN users patient ON patient.id = ap.patient_id
               JOIN users provider ON provider.id = ap.provider_id
               $where
              ORDER BY ap.created_at DESC
              LIMIT 100"
        );
        $stmt->execute($hospitalId ? [$hospitalId] : []);
        $rows = $stmt->fetchAll();
    } elseif ($view === 'audit-logs') {
        $columns = ['When', 'Actor', 'Role', 'Action', 'Details'];
        $where = $hospitalId ? ' WHERE u.hospital_id = ?' : '';
        $stmt = $pdo->prepare(
            "SELECT a.created_at 'when',
                    COALESCE(u.fullname, 'System') actor,
                    COALESCE(a.user_role, '-') role,
                    a.action,
                    COALESCE(a.details, '-') details
               FROM audit_logs a
               LEFT JOIN users u ON u.id = a.user_id
               $where
              ORDER BY a.created_at DESC
              LIMIT 100"
        );
        $stmt->execute($hospitalId ? [$hospitalId] : []);
        $rows = $stmt->fetchAll();
    } elseif ($view === 'user-management' || $view === 'users') {
        $columns = ['Name', 'Email', 'Role', 'Hospital', 'Joined', 'Action'];
        $rows = $pdo->query(
            "SELECT u.id account_id,
                    u.fullname name,
                    u.email,
                    u.role,
                    COALESCE(h.name, '-') hospital,
                    u.created_at joined
               FROM users u
               LEFT JOIN hospitals h ON h.id = u.hospital_id
              ORDER BY u.created_at DESC
              LIMIT 150"
        )->fetchAll();
    } elseif ($view === 'organizations') {
        $columns = ['Hospital', 'District', 'Accounts', 'Status'];
        $rows = $pdo->query(
            "SELECT h.name hospital,
                    COALESCE(d.name, '-') district,
                    COUNT(u.id) accounts,
                    IF(h.is_active = 1, 'Active', 'Inactive') status
               FROM hospitals h
               LEFT JOIN districts d ON d.id = h.district_id
               LEFT JOIN users u ON u.hospital_id = h.id
              GROUP BY h.id, h.name, d.name, h.is_active
              ORDER BY h.name"
        )->fetchAll();
    } elseif ($view === 'roles-permissions') {
        $columns = ['Role', 'Permissions', 'Access Scope', 'Audit Mode'];
        $rows = [
            ['Role' => 'System Admin', 'Permissions' => 'Manage users, organizations, settings, governance policies', 'Access Scope' => 'Platform-wide', 'Audit Mode' => 'Mandatory'],
            ['Role' => 'Hospital Admin', 'Permissions' => 'Manage local staff, appointments, hospital records, operational policies', 'Access Scope' => 'Hospital only', 'Audit Mode' => 'Mandatory'],
            ['Role' => 'Doctor', 'Permissions' => 'View/manage assigned patients and clinical documentation', 'Access Scope' => 'Clinical care context', 'Audit Mode' => 'Mandatory'],
            ['Role' => 'Patient', 'Permissions' => 'View own records, consented access, appointments', 'Access Scope' => 'Own data and consented sharing', 'Audit Mode' => 'Audit logging'],
            ['Role' => 'Pharmacist', 'Permissions' => 'Dispense medicines, stock workflows, approved prescriptions', 'Access Scope' => 'Medication scope only', 'Audit Mode' => 'Mandatory'],
            ['Role' => 'Lab Technician', 'Permissions' => 'Process lab orders, upload reports, maintain test history', 'Access Scope' => 'Laboratory scope only', 'Audit Mode' => 'Mandatory'],
        ];
    } elseif ($view === 'patients') {
        $columns = ['Patient', 'Email', 'District', 'Status', 'Last Update'];
        $rows = $pdo->query(
            "SELECT u.fullname patient,
                    u.email,
                    COALESCE(NULLIF(u.district, ''), '-') district,
                    'Active' status,
                    COALESCE(MAX(a.created_at), u.created_at) last_update
               FROM users u
               LEFT JOIN appointments a ON a.patient_id = u.id
              WHERE u.role = 'Patient'
              GROUP BY u.id, u.fullname, u.email, u.district, u.created_at
              ORDER BY u.fullname
              LIMIT 100"
        )->fetchAll();
    } elseif ($view === 'data-exchange') {
        $columns = ['When', 'Patient', 'Provider', 'Role', 'Exchange', 'Details', 'Status'];
        $pendingExchangeRequests = admin_count($pdo, "SELECT COUNT(*) FROM access_permissions WHERE status IN ('Pending', 'Requested')");
        if ($role === 'System Admin') {
            $nationalMetrics = nhre_national_platform_metrics();
            $exchangeSummary = [
                ['label' => $nationalMetrics['active_consents']['label'], 'value' => $nationalMetrics['active_consents']['value'], 'icon' => $nationalMetrics['active_consents']['icon'], 'signal' => 'National'],
                ['label' => $nationalMetrics['record_exchanges']['label'], 'value' => $nationalMetrics['record_exchanges']['value'], 'icon' => $nationalMetrics['record_exchanges']['icon'], 'signal' => 'National'],
                ['label' => 'Pending requests', 'value' => $pendingExchangeRequests, 'icon' => 'fa-hourglass-half', 'signal' => 'Live'],
                ['label' => $nationalMetrics['connected_providers']['label'], 'value' => $nationalMetrics['connected_providers']['value'], 'icon' => $nationalMetrics['connected_providers']['icon'], 'signal' => 'National'],
            ];
        } else {
            $exchangeSummary = [
                ['label' => 'Active consents', 'value' => admin_count($pdo, "SELECT COUNT(*) FROM access_permissions WHERE status = 'Active'"), 'icon' => 'fa-shield-heart', 'signal' => 'Audit'],
                ['label' => 'Record exchanges', 'value' => admin_count($pdo, 'SELECT COUNT(*) FROM access_logs'), 'icon' => 'fa-right-left', 'signal' => 'Audit'],
                ['label' => 'Pending requests', 'value' => $pendingExchangeRequests, 'icon' => 'fa-hourglass-half', 'signal' => 'Live'],
                ['label' => 'Connected providers', 'value' => admin_count($pdo, 'SELECT COUNT(DISTINCT provider_id) FROM access_permissions'), 'icon' => 'fa-user-doctor', 'signal' => 'Audit'],
            ];
        }
        $rows = $pdo->query(
            "SELECT *
               FROM (
                    SELECT ap.created_at occurred_at,
                           patient.fullname patient,
                           COALESCE(provider.fullname, 'Unknown provider') provider,
                           ap.provider_role provider_role,
                           'Consent grant' exchange_type,
                           ap.record_types details,
                           ap.status status
                      FROM access_permissions ap
                      JOIN users patient ON patient.id = ap.patient_id
                      LEFT JOIN users provider ON provider.id = ap.provider_id

                    UNION ALL

                    SELECT access_log.accessed_at occurred_at,
                           patient.fullname patient,
                           COALESCE(provider.fullname, 'Unknown provider') provider,
                           COALESCE(permission.provider_role, provider.role, 'Provider') provider_role,
                           CONCAT('Record ', access_log.action) exchange_type,
                           access_log.record_type details,
                           COALESCE(permission.status, 'Historical') status
                      FROM access_logs access_log
                      JOIN users patient ON patient.id = access_log.patient_id
                      LEFT JOIN users provider ON provider.id = access_log.provider_id
                      LEFT JOIN access_permissions permission ON permission.id = access_log.permission_id
               ) exchange_events
              ORDER BY occurred_at DESC
              LIMIT 100"
        )->fetchAll();
    } elseif ($view === 'settings') {
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
        $scope = $hospitalId ? ' WHERE hospital_id = ?' : '';
        $stmt = $pdo->prepare("SELECT role, COUNT(*) total FROM users$scope GROUP BY role");
        $stmt->execute($hospitalId ? [$hospitalId] : []);
        $metrics = $stmt->fetchAll();

        $reportItems = [
            ['label' => 'Appointments', 'count' => $hospitalId
                ? admin_count($pdo, 'SELECT COUNT(*) FROM appointments a JOIN users d ON d.id = a.doctor_id WHERE d.hospital_id = ?', [$hospitalId])
                : admin_count($pdo, 'SELECT COUNT(*) FROM appointments')],
            ['label' => 'Prescriptions', 'count' => $hospitalId
                ? admin_count($pdo, 'SELECT COUNT(*) FROM prescriptions p JOIN users d ON d.id = p.doctor_id WHERE d.hospital_id = ?', [$hospitalId])
                : admin_count($pdo, 'SELECT COUNT(*) FROM prescriptions')],
            ['label' => 'Lab bookings', 'count' => admin_count($pdo, 'SELECT COUNT(*) FROM medical_test_bookings')],
            ['label' => 'Documents', 'count' => $hospitalId
                ? admin_count($pdo, 'SELECT COUNT(*) FROM medical_documents m JOIN users p ON p.id = m.patient_id WHERE p.hospital_id = ?', [$hospitalId])
                : admin_count($pdo, 'SELECT COUNT(*) FROM medical_documents')],
            ['label' => 'Consent grants', 'count' => admin_count($pdo, 'SELECT COUNT(*) FROM access_permissions')],
            ['label' => 'Record access', 'count' => admin_count($pdo, 'SELECT COUNT(*) FROM access_logs')],
            ['label' => 'Vaccinations', 'count' => admin_count($pdo, 'SELECT COUNT(*) FROM vaccination_bookings')],
        ];
        $usesNationalProjection = $role === 'System Admin';
        if ($usesNationalProjection) {
            $reportItems = admin_national_report_items();
        }
        $reportChartSegments = admin_chart_segments($reportItems);
        $reportCards = $usesNationalProjection
            ? nhre_national_report_cards()
            : [
                ['label' => 'Patients', 'value' => admin_count($pdo, "SELECT COUNT(*) FROM users WHERE role = 'Patient'"), 'icon' => 'fa-user-injured'],
                ['label' => 'Clinicians', 'value' => admin_count($pdo, "SELECT COUNT(*) FROM users WHERE role IN ('Doctor', 'Lab Technician', 'Pharmacist')"), 'icon' => 'fa-user-doctor'],
                ['label' => 'Exchange events', 'value' => admin_count($pdo, 'SELECT COUNT(*) FROM access_logs'), 'icon' => 'fa-right-left'],
                ['label' => 'Active consents', 'value' => admin_count($pdo, "SELECT COUNT(*) FROM access_permissions WHERE status = 'Active'"), 'icon' => 'fa-shield-heart'],
            ];
        $columns = ['Metric', 'Count', 'Signal'];
        foreach ($reportItems as $item) {
            $rows[] = [
                'Metric' => $item['label'],
                'Count' => $usesNationalProjection ? admin_format_metric_value((int)$item['count']) : number_format((int)$item['count']),
                'Signal' => $usesNationalProjection ? 'National scale' : ((int)$item['count'] > 0 ? 'Live data' : 'Waiting for activity'),
            ];
        }
    }
} catch (Throwable $e) {
    $error = 'This workspace could not be loaded. Please verify the database setup and try again.';
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= e($title) ?> - NHRE</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="assets/css/styles.css?v=20260818-19">
</head>
<body class="dashboard-body">
  <?php require __DIR__ . '/includes/sidebar.php'; ?>
  <?php require __DIR__ . '/includes/topnav.php'; ?>

  <main class="dashboard-main">
    <section class="container">
      <div class="dashboard-hero glass-card">
        <div>
          <span class="auth-kicker">Administration workspace</span>
          <h1><?= e($title) ?></h1>
          <p>Role-scoped operational information for <?= e($role) ?>.</p>
        </div>
      </div>

      <?php if ($errors): ?>
        <div class="alert alert-danger auth-alert mt-4" role="alert">
          <?php foreach ($errors as $message): ?><div><?= e($message) ?></div><?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if ($success): ?>
        <div class="alert alert-success auth-alert mt-4" role="alert"><?= e($success) ?></div>
      <?php endif; ?>

      <?php if (isset($error)): ?>
        <div class="alert alert-danger mt-4"><?= e($error) ?></div>
      <?php elseif ($view === 'reports'): ?>
        <?php if ($reportCards): ?>
          <div class="row g-3 mt-2 dashboard-cards">
            <?php foreach ($reportCards as $card): ?>
              <div class="col-6 col-md-4 col-xl">
                <article class="dashboard-card admin-stat-card">
                  <div class="admin-stat-head">
                    <div class="dashboard-card-icon"><i class="fa-solid <?= e($card['icon']) ?>"></i></div>
                    <span>Live</span>
                  </div>
                  <h2><?= e(admin_format_metric_value($card['value'])) ?></h2>
                  <p><?= e($card['label']) ?></p>
                  <div class="admin-stat-meter"><i></i></div>
                </article>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <div class="row g-4 mt-1 align-items-stretch">
          <div class="col-lg-5">
            <article class="dashboard-card exchange-pie-card h-100">
              <div class="admin-card-heading d-flex align-items-center justify-content-between gap-3 mb-3">
                <div>
                  <span class="admin-panel-kicker">Live activity mix</span>
                  <h2 class="mb-0">Exchange distribution</h2>
                </div>
                <span class="exchange-status-pill">Live</span>
              </div>

              <?php if ($reportChartSegments): ?>
                <div class="exchange-pie-shell js-exchange-pie">
                  <svg class="exchange-pie-svg" viewBox="0 0 200 200" role="img" aria-label="Healthcare exchange activity distribution">
                    <?php foreach ($reportChartSegments as $segment): ?>
                      <path class="exchange-pie-slice"
                            d="<?= e($segment['path']) ?>"
                            style="--slice-color: <?= e($segment['color']) ?>; --slice-x: <?= e((string)$segment['offset_x']) ?>px; --slice-y: <?= e((string)$segment['offset_y']) ?>px;"
                            tabindex="0">
                        <title><?= e($segment['label']) ?>: <?= e(admin_format_metric_value((int)$segment['count'])) ?> events</title>
                      </path>
                    <?php endforeach; ?>
                    <circle class="exchange-pie-core" cx="100" cy="100" r="43"></circle>
                  </svg>
                  <div class="exchange-pie-center">
                    <strong><?= e(admin_format_metric_value(array_sum(array_map(static fn(array $segment): int => (int)$segment['count'], $reportChartSegments)))) ?></strong>
                    <span>events</span>
                  </div>
                  <span class="exchange-pie-ring ring-one"></span>
                  <span class="exchange-pie-ring ring-two"></span>
                </div>
                <div class="exchange-pie-legend">
                  <?php foreach ($reportChartSegments as $segment): ?>
                    <span><i style="background: <?= e($segment['color']) ?>"></i><?= e($segment['label']) ?> <b><?= (int)$segment['percent'] ?>%</b></span>
                  <?php endforeach; ?>
                </div>
              <?php else: ?>
                <p class="text-muted mb-0">No reportable activity has been recorded yet.</p>
              <?php endif; ?>
            </article>
          </div>
          <div class="col-lg-7">
            <article class="dashboard-card admin-signal-card h-100">
              <div class="dashboard-card-icon"><i class="fa-solid fa-chart-line"></i></div>
              <h2>Operational signals</h2>
              <div class="table-responsive mt-3">
                <table class="table table-hover align-middle">
                  <thead><tr><?php foreach ($columns as $column): ?><th><?= e($column) ?></th><?php endforeach; ?></tr></thead>
                  <tbody>
                    <?php foreach ($rows as $row): ?>
                      <tr>
                        <td><?= e($row['Metric']) ?></td>
                        <td><?= e((string)$row['Count']) ?></td>
                        <td><span class="exchange-chip"><?= e($row['Signal']) ?></span></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </article>
          </div>
        </div>
      <?php elseif ($view === 'data-exchange'): ?>
        <div class="row g-3 mt-2 dashboard-cards">
          <?php foreach ($exchangeSummary as $item): ?>
            <div class="col-6 col-xl-3">
              <article class="dashboard-card admin-stat-card">
                <div class="admin-stat-head">
                  <div class="dashboard-card-icon"><i class="fa-solid <?= e($item['icon']) ?>"></i></div>
                    <span><?= e($item['signal'] ?? 'Audit') ?></span>
                </div>
                  <h2><?= e(admin_format_metric_value($item['value'])) ?></h2>
                <p><?= e($item['label']) ?></p>
                <div class="admin-stat-meter"><i></i></div>
              </article>
            </div>
          <?php endforeach; ?>
        </div>

        <article class="dashboard-card mt-4 exchange-table-card">
          <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
            <div>
              <span class="admin-panel-kicker">Interoperability ledger</span>
              <h2 class="mb-0">Recent healthcare exchanges</h2>
            </div>
            <span class="exchange-status-pill">Audited</span>
          </div>
          <div class="table-responsive">
            <table class="table table-hover align-middle">
              <thead><tr><?php foreach ($columns as $column): ?><th><?= e($column) ?></th><?php endforeach; ?></tr></thead>
              <tbody>
                <?php foreach ($rows as $row): ?>
                  <tr>
                    <td><span class="exchange-time"><?= e(admin_format_time((string)$row['occurred_at'])) ?></span></td>
                    <td><strong><?= e($row['patient']) ?></strong></td>
                    <td><?= e($row['provider']) ?></td>
                    <td><span class="exchange-role-pill"><i class="fa-solid <?= e(admin_role_icon((string)$row['provider_role'])) ?>"></i><?= e($row['provider_role']) ?></span></td>
                    <td><span class="exchange-chip"><?= e($row['exchange_type']) ?></span></td>
                    <td><?= e($row['details']) ?></td>
                    <td><span class="admin-status-badge <?= e(admin_exchange_status_class((string)$row['status'])) ?>"><?= e($row['status']) ?></span></td>
                  </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?><tr><td colspan="<?= count($columns) ?>" class="text-center text-muted">No exchange records are available yet.</td></tr><?php endif; ?>
              </tbody>
            </table>
          </div>
        </article>
      <?php elseif ($view === 'settings' && $settingsCards): ?>
        <section class="mt-4">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
              <h2 class="h5 fw-bold mb-0">Platform settings domains</h2>
              <p class="text-muted mb-0">Governance and control areas for the NHRE exchange.</p>
            </div>
            <span class="badge text-bg-light border"><?= count($settingsCards) ?> categories</span>
          </div>
          <div class="row g-3">
            <?php foreach ($settingsCards as $card): ?>
              <div class="col-lg-4 col-md-6">
                <article class="dashboard-card h-100">
                  <div class="d-flex justify-content-between align-items-start mb-3">
                    <div class="dashboard-card-icon"><i class="fa-solid <?= e($card['icon']) ?>"></i></div>
                    <span class="badge text-bg-light border">Configured</span>
                  </div>
                  <h3 class="h6 fw-bold mb-2"><?= e($card['title']) ?></h3>
                  <p class="text-muted mb-0"><?= e($card['text']) ?></p>
                </article>
              </div>
            <?php endforeach; ?>
          </div>
        </section>
      <?php else: ?>
        <?php if ($metrics): ?>
          <div class="row g-3 mt-2">
            <?php foreach ($metrics as $metric): ?>
              <div class="col-md-3">
                <article class="dashboard-card">
                  <span class="text-muted"><?= e($metric['role']) ?></span>
                  <h2><?= (int)$metric['total'] ?></h2>
                </article>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <article class="dashboard-card mt-4">
          <div class="table-responsive">
            <table class="table table-hover align-middle">
              <thead><tr><?php foreach ($columns as $column): ?><th><?= e($column) ?></th><?php endforeach; ?></tr></thead>
              <tbody>
                <?php foreach ($rows as $row): ?>
                  <tr>
                    <?php foreach ($row as $key => $value): ?>
                      <?php if ($key !== 'account_id'): ?><td><?= e($value ?? '-') ?></td><?php endif; ?>
                    <?php endforeach; ?>
                    <?php if ($view === 'user-management'): ?>
                      <td class="text-end">
                        <?php if ((int)$row['account_id'] === (int)$_SESSION['user_id']): ?>
                          <span class="text-muted small">Current account</span>
                        <?php else: ?>
                          <form action="auth/system_account_delete_process.php" method="post" onsubmit="return confirm('Remove this account and its related data? This cannot be undone.');">
                            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                            <input type="hidden" name="account_id" value="<?= (int)$row['account_id'] ?>">
                            <button class="btn btn-sm btn-outline-danger" type="submit">Remove</button>
                          </form>
                        <?php endif; ?>
                      </td>
                    <?php endif; ?>
                  </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?><tr><td colspan="<?= count($columns) ?>" class="text-center text-muted">No records are available yet.</td></tr><?php endif; ?>
              </tbody>
            </table>
          </div>
        </article>
      <?php endif; ?>
    </section>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/app.js?v=20260818-10"></script>
</body>
</html>
