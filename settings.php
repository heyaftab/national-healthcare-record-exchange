<?php
require_once __DIR__.'/auth/auth_check.php';
require_role(valid_roles());
ensure_clinical_tables();

function ensure_user_settings_table(): void
{
    try {
        db()->exec(
            'CREATE TABLE IF NOT EXISTS user_settings (
                user_id INT UNSIGNED NOT NULL,
                setting_key VARCHAR(100) NOT NULL,
                setting_value TEXT NULL,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (user_id, setting_key),
                KEY idx_user_settings_key (setting_key),
                CONSTRAINT fk_user_settings_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    } catch (PDOException $e) {
    }
}

ensure_user_settings_table();

$id = (int)($_SESSION['user_id'] ?? 0);
$errors = session_pull('errors', []);
$success = session_pull('success');

$stmt = db()->prepare(
    'SELECT u.fullname, u.email, u.phone, u.role, s.name AS specialization, u.qualification, h.name AS hospital_name, u.consultation_fee, u.bio, u.visiting_hours, u.is_featured
     FROM users u
     LEFT JOIN specializations s ON s.id = u.specialization_id
     LEFT JOIN hospitals h ON h.id = u.hospital_id
     WHERE u.id = ? LIMIT 1'
);
$stmt->execute([$id]);
$user = $stmt->fetch();

$userRole = (string)($user['role'] ?? ($_SESSION['role'] ?? 'Patient'));

$settingsStmt = db()->prepare('SELECT setting_key, setting_value FROM user_settings WHERE user_id = ?');
$settingsStmt->execute([$id]);
$userSettings = [];
foreach ($settingsStmt->fetchAll() as $row) {
    $userSettings[(string)$row['setting_key']] = (string)$row['setting_value'];
}

$readSetting = static function (string $key, string $default = ''): string {
    global $userSettings;
    return array_key_exists($key, $userSettings) ? (string)$userSettings[$key] : $default;
};

$boolSetting = static function (string $key): bool {
    global $userSettings;
    $value = strtolower((string)($userSettings[$key] ?? '0'));
    return in_array($value, ['1', 'true', 'on', 'yes'], true);
};
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Settings - NHRE</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body class="dashboard-body">
<?php require __DIR__.'/includes/sidebar.php'; require __DIR__.'/includes/topnav.php'; ?>
<main class="dashboard-main">
    <section class="container">
        <div class="dashboard-hero glass-card">
            <div>
                <span class="auth-kicker">Account & security</span>
                <h1>Settings</h1>
                <p>Manage your NHRE security, contact preferences, and role-specific workspace controls.</p>
            </div>
        </div>

        <?php foreach ($errors as $e): ?>
            <div class="alert alert-danger mt-3"><?= e($e) ?></div>
        <?php endforeach; ?>
        <?php if ($success): ?>
            <div class="alert alert-success mt-3"><?= e($success) ?></div>
        <?php endif; ?>

        <div class="row g-4 mt-1">
            <div class="col-lg-6">
                <article class="dashboard-card">
                    <h2 class="fs-5">Account details</h2>
                    <p><strong><?= e($user['fullname'] ?? 'NHRE User') ?></strong><br><?= e($user['email'] ?? '') ?><br><?= e($user['phone'] ?? '') ?></p>
                    <div class="d-flex flex-wrap gap-2">
                        <a class="btn btn-outline-nhre" href="profile.php">Edit profile</a>
                        <?php if ($userRole === 'Patient'): ?>
                            <a class="btn btn-outline-nhre" href="data_access.php">Privacy & data access</a>
                        <?php endif; ?>
                    </div>
                </article>
            </div>

            <div class="col-lg-6">
                <article class="dashboard-card">
                    <h2 class="fs-5">Change password</h2>
                    <form method="post" action="auth/settings_process.php">
                        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                        <input type="hidden" name="form_type" value="password_update">
                        <input class="form-control mb-2" type="password" name="current_password" placeholder="Current password" required>
                        <input class="form-control mb-2" type="password" name="new_password" placeholder="New password (8+ characters)" required>
                        <input class="form-control mb-3" type="password" name="confirm_password" placeholder="Confirm new password" required>
                        <button class="btn btn-solid-nhre" type="submit">Update password</button>
                    </form>
                </article>
            </div>

            <?php if ($userRole === 'Doctor'): ?>
                <div class="col-12">
                    <article class="dashboard-card">
                        <h2 class="fs-5">Professional preferences</h2>
                        <form method="post" action="auth/settings_process.php">
                            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                            <input type="hidden" name="form_type" value="doctor_preferences">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Specialization</label>
                                    <input class="form-control" type="text" value="<?= e($user['specialization'] ?: 'Not set') ?>" readonly>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="doctor_fee">Consultation fee</label>
                                    <input id="doctor_fee" class="form-control" type="number" min="0" name="consultation_fee" value="<?= e((string)($user['consultation_fee'] ?? 0)) ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="doctor_visibility">Profile visibility</label>
                                    <select id="doctor_visibility" class="form-select" name="profile_visibility">
                                        <option value="Public" <?= (strtolower((string)$readSetting('doctor_profile_visibility', 'Public')) === 'public') ? 'selected' : '' ?>>Public</option>
                                        <option value="Private" <?= (strtolower((string)$readSetting('doctor_profile_visibility', 'Public')) === 'private') ? 'selected' : '' ?>>Private</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="doctor_hours">Visiting hours</label>
                                    <input id="doctor_hours" class="form-control" type="text" name="visiting_hours" value="<?= e($user['visiting_hours'] ?? '') ?>" placeholder="e.g. Mon-Fri 9:00-17:00">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="doctor_bio">Professional bio</label>
                                    <textarea id="doctor_bio" class="form-control" rows="3" name="bio" placeholder="Brief overview of your practice"><?= e($user['bio'] ?? '') ?></textarea>
                                </div>
                            </div>
                            <div class="mt-3 d-flex flex-wrap gap-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="doctor_accept_new_patients" name="accept_new_patients" value="1" <?= $boolSetting('doctor_accept_new_patients') ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="doctor_accept_new_patients">Accept new patient bookings</label>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="doctor_appointment_reminders" name="appointment_reminders" value="1" <?= $boolSetting('doctor_appointment_reminders') ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="doctor_appointment_reminders">Appointment reminders</label>
                                </div>
                            </div>
                            <button class="btn btn-solid-nhre mt-3" type="submit">Save professional settings</button>
                        </form>
                    </article>
                </div>
            <?php elseif ($userRole === 'Patient'): ?>
                <div class="col-12">
                    <article class="dashboard-card">
                        <h2 class="fs-5">Patient privacy & alerts</h2>
                        <form method="post" action="auth/settings_process.php">
                            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                            <input type="hidden" name="form_type" value="patient_preferences">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label" for="patient_visibility">Privacy profile</label>
                                    <select id="patient_visibility" class="form-select" name="profile_visibility">
                                        <option value="Private" <?= (strtolower((string)$readSetting('patient_profile_visibility', 'Private')) === 'private') ? 'selected' : '' ?>>Private</option>
                                        <option value="Hospital only" <?= (strtolower((string)$readSetting('patient_profile_visibility', 'Private')) === 'hospital only') ? 'selected' : '' ?>>Hospital only</option>
                                        <option value="Shared" <?= (strtolower((string)$readSetting('patient_profile_visibility', 'Private')) === 'shared') ? 'selected' : '' ?>>Shared</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="patient_contact">Preferred alert channel</label>
                                    <select id="patient_contact" class="form-select" name="alert_channel">
                                        <option value="Email" <?= ((string)$readSetting('patient_alert_channel', 'Email') === 'Email') ? 'selected' : '' ?>>Email</option>
                                        <option value="SMS" <?= ((string)$readSetting('patient_alert_channel', 'Email') === 'SMS') ? 'selected' : '' ?>>SMS</option>
                                        <option value="Both" <?= ((string)$readSetting('patient_alert_channel', 'Email') === 'Both') ? 'selected' : '' ?>>Both</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="patient_emergency">Emergency contact</label>
                                    <input id="patient_emergency" class="form-control" type="text" name="emergency_contact" value="<?= e($readSetting('patient_emergency_contact', '')) ?>" placeholder="Name + phone number">
                                </div>
                            </div>
                            <div class="mt-3 d-flex flex-wrap gap-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="patient_appointment_reminders" name="appointment_reminders" value="1" <?= $boolSetting('patient_appointment_reminders') ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="patient_appointment_reminders">Appointment reminders</label>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="patient_lab_alerts" name="lab_alerts" value="1" <?= $boolSetting('patient_lab_alerts') ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="patient_lab_alerts">Lab result alerts</label>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="patient_data_access_updates" name="data_access_updates" value="1" <?= $boolSetting('patient_data_access_updates') ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="patient_data_access_updates">Data access updates</label>
                                </div>
                            </div>
                            <button class="btn btn-solid-nhre mt-3" type="submit">Save patient preferences</button>
                        </form>
                    </article>
                </div>
            <?php elseif ($userRole === 'Pharmacist'): ?>
                <div class="col-12">
                    <article class="dashboard-card">
                        <h2 class="fs-5">Pharmacy workflow settings</h2>
                        <form method="post" action="auth/settings_process.php">
                            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                            <input type="hidden" name="form_type" value="pharmacist_preferences">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="pharmacy_hours">Service hours</label>
                                    <input id="pharmacy_hours" class="form-control" type="text" name="service_hours" value="<?= e($readSetting('pharmacy_service_hours', '8:00 AM - 8:00 PM')) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="pharmacy_alert">Inventory alert threshold</label>
                                    <input id="pharmacy_alert" class="form-control" type="number" min="0" name="inventory_alert_level" value="<?= e($readSetting('pharmacy_inventory_alert_level', '10')) ?>">
                                </div>
                            </div>
                            <div class="mt-3 d-flex flex-wrap gap-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="pharmacy_low_stock" name="low_stock_alerts" value="1" <?= $boolSetting('pharmacy_low_stock_alerts') ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="pharmacy_low_stock">Low-stock alerts</label>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="pharmacy_refill" name="refill_notifications" value="1" <?= $boolSetting('pharmacy_refill_notifications') ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="pharmacy_refill">Prescription refill notifications</label>
                                </div>
                            </div>
                            <button class="btn btn-solid-nhre mt-3" type="submit">Save pharmacy settings</button>
                        </form>
                    </article>
                </div>
            <?php elseif ($userRole === 'Lab Technician'): ?>
                <div class="col-12">
                    <article class="dashboard-card">
                        <h2 class="fs-5">Lab operations settings</h2>
                        <form method="post" action="auth/settings_process.php">
                            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                            <input type="hidden" name="form_type" value="lab_preferences">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label" for="lab_section">Default section</label>
                                    <select id="lab_section" class="form-select" name="default_section">
                                        <option value="Biochemistry" <?= ((string)$readSetting('lab_default_section', 'Biochemistry') === 'Biochemistry') ? 'selected' : '' ?>>Biochemistry</option>
                                        <option value="Hematology" <?= ((string)$readSetting('lab_default_section', 'Biochemistry') === 'Hematology') ? 'selected' : '' ?>>Hematology</option>
                                        <option value="Microbiology" <?= ((string)$readSetting('lab_default_section', 'Biochemistry') === 'Microbiology') ? 'selected' : '' ?>>Microbiology</option>
                                        <option value="Pathology" <?= ((string)$readSetting('lab_default_section', 'Biochemistry') === 'Pathology') ? 'selected' : '' ?>>Pathology</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="lab_turnaround">Preferred turnaround</label>
                                    <input id="lab_turnaround" class="form-control" type="text" name="turnaround" value="<?= e($readSetting('lab_turnaround', 'Same Day')) ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="lab_status">Result notification</label>
                                    <select id="lab_status" class="form-select" name="result_notif">
                                        <option value="Email" <?= ((string)$readSetting('lab_result_notif', 'Email') === 'Email') ? 'selected' : '' ?>>Email</option>
                                        <option value="Dashboard" <?= ((string)$readSetting('lab_result_notif', 'Email') === 'Dashboard') ? 'selected' : '' ?>>Dashboard</option>
                                        <option value="Both" <?= ((string)$readSetting('lab_result_notif', 'Email') === 'Both') ? 'selected' : '' ?>>Both</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mt-3 d-flex flex-wrap gap-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="lab_auto_review" name="auto_review" value="1" <?= $boolSetting('lab_auto_review') ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="lab_auto_review">Auto-review completed samples</label>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="lab_priority_alerts" name="priority_alerts" value="1" <?= $boolSetting('lab_priority_alerts') ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="lab_priority_alerts">Urgent sample alerts</label>
                                </div>
                            </div>
                            <button class="btn btn-solid-nhre mt-3" type="submit">Save lab settings</button>
                        </form>
                    </article>
                </div>
            <?php elseif ($userRole === 'Hospital Admin'): ?>
                <div class="col-12">
                    <article class="dashboard-card">
                        <h2 class="fs-5">Administration preferences</h2>
                        <form method="post" action="auth/settings_process.php">
                            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                            <input type="hidden" name="form_type" value="admin_preferences">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label" for="admin_dashboard">Default dashboard focus</label>
                                    <select id="admin_dashboard" class="form-select" name="dashboard_focus">
                                        <option value="Overview" <?= ((string)$readSetting('hospital_dashboard_focus', 'Overview') === 'Overview') ? 'selected' : '' ?>>Overview</option>
                                        <option value="Appointments" <?= ((string)$readSetting('hospital_dashboard_focus', 'Overview') === 'Appointments') ? 'selected' : '' ?>>Appointments</option>
                                        <option value="Records" <?= ((string)$readSetting('hospital_dashboard_focus', 'Overview') === 'Records') ? 'selected' : '' ?>>Records</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="admin_notification">Escalation channel</label>
                                    <select id="admin_notification" class="form-select" name="escalation_channel">
                                        <option value="Email" <?= ((string)$readSetting('hospital_escalation_channel', 'Email') === 'Email') ? 'selected' : '' ?>>Email</option>
                                        <option value="In-app" <?= ((string)$readSetting('hospital_escalation_channel', 'Email') === 'In-app') ? 'selected' : '' ?>>In-app</option>
                                        <option value="Both" <?= ((string)$readSetting('hospital_escalation_channel', 'Email') === 'Both') ? 'selected' : '' ?>>Both</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="admin_schedule">Review cadence</label>
                                    <input id="admin_schedule" class="form-control" type="text" name="review_cadence" value="<?= e($readSetting('hospital_review_cadence', 'Weekly')) ?>">
                                </div>
                            </div>
                            <div class="mt-3 d-flex flex-wrap gap-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="admin_staff_alerts" name="staff_alerts" value="1" <?= $boolSetting('hospital_staff_alerts') ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="admin_staff_alerts">Staff activity alerts</label>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="admin_approval_reminders" name="approval_reminders" value="1" <?= $boolSetting('hospital_approval_reminders') ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="admin_approval_reminders">Access approval reminders</label>
                                </div>
                            </div>
                            <button class="btn btn-solid-nhre mt-3" type="submit">Save admin settings</button>
                        </form>
                    </article>
                </div>
            <?php elseif ($userRole === 'System Admin'): ?>
                <div class="col-12">
                    <article class="dashboard-card">
                        <h2 class="fs-5">System administration preferences</h2>
                        <form method="post" action="auth/settings_process.php">
                            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                            <input type="hidden" name="form_type" value="system_preferences">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label" for="system_alerts">Alert level</label>
                                    <select id="system_alerts" class="form-select" name="alert_level">
                                        <option value="Standard" <?= ((string)$readSetting('system_alert_level', 'Standard') === 'Standard') ? 'selected' : '' ?>>Standard</option>
                                        <option value="Priority" <?= ((string)$readSetting('system_alert_level', 'Standard') === 'Priority') ? 'selected' : '' ?>>Priority</option>
                                        <option value="Critical" <?= ((string)$readSetting('system_alert_level', 'Standard') === 'Critical') ? 'selected' : '' ?>>Critical</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="system_digest">Digest frequency</label>
                                    <select id="system_digest" class="form-select" name="digest_frequency">
                                        <option value="Daily" <?= ((string)$readSetting('system_digest_frequency', 'Daily') === 'Daily') ? 'selected' : '' ?>>Daily</option>
                                        <option value="Weekly" <?= ((string)$readSetting('system_digest_frequency', 'Daily') === 'Weekly') ? 'selected' : '' ?>>Weekly</option>
                                        <option value="Monthly" <?= ((string)$readSetting('system_digest_frequency', 'Daily') === 'Monthly') ? 'selected' : '' ?>>Monthly</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="system_contact">Ops contact alias</label>
                                    <input id="system_contact" class="form-control" type="text" name="ops_alias" value="<?= e($readSetting('system_ops_alias', 'NHRE Operations')) ?>">
                                </div>
                            </div>
                            <div class="mt-3 d-flex flex-wrap gap-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="system_maintenance" name="maintenance_alerts" value="1" <?= $boolSetting('system_maintenance_alerts') ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="system_maintenance">Maintenance alerts</label>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="system_audit" name="audit_digest" value="1" <?= $boolSetting('system_audit_digest') ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="system_audit">Audit trail digest</label>
                                </div>
                            </div>
                            <button class="btn btn-solid-nhre mt-3" type="submit">Save system settings</button>
                        </form>
                    </article>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>
<script src="assets/js/app.js?v=20260818-10"></script>
</body>
</html>
