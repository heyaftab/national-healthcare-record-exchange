<?php
require_once __DIR__.'/auth_check.php';
require_role(valid_roles());

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

function save_user_setting(int $userId, string $key, mixed $value): void
{
    $stmt = db()->prepare(
        'INSERT INTO user_settings (user_id, setting_key, setting_value) VALUES (?, ?, ?) 
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = CURRENT_TIMESTAMP'
    );
    $stmt->execute([$userId, $key, (string)$value]);
}

function is_checked(mixed $value): bool
{
    return in_array(strtolower((string)$value), ['1', 'true', 'on', 'yes'], true);
}

ensure_user_settings_table();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['_csrf'] ?? null)) {
    $_SESSION['errors'] = ['Invalid settings request.'];
    redirect('../settings.php');
}

$formType = (string)($_POST['form_type'] ?? 'password_update');
$userId = (int)($_SESSION['user_id'] ?? 0);

if ($formType === 'password_update') {
    $current = (string)($_POST['current_password'] ?? '');
    $new = (string)($_POST['new_password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');

    if (strlen($new) < 8 || $new !== $confirm) {
        $_SESSION['errors'] = ['New passwords must match and contain at least 8 characters.'];
        redirect('../settings.php');
    }

    $s = db()->prepare('SELECT password_hash FROM users WHERE id = ? LIMIT 1');
    $s->execute([$userId]);
    if (!password_verify($current, (string)$s->fetchColumn())) {
        $_SESSION['errors'] = ['Current password is incorrect.'];
        redirect('../settings.php');
    }

    $u = db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
    $u->execute([password_hash($new, PASSWORD_DEFAULT), $userId]);
    $_SESSION['success'] = 'Password updated successfully.';
    redirect('../settings.php');
}

if ($formType === 'doctor_preferences') {
    $consultationFee = max(0, (int)($_POST['consultation_fee'] ?? 0));
    $visitingHours = trim((string)($_POST['visiting_hours'] ?? ''));
    $bio = trim((string)($_POST['bio'] ?? ''));
    $profileVisibility = trim((string)($_POST['profile_visibility'] ?? 'Public'));
    $acceptNewPatients = is_checked($_POST['accept_new_patients'] ?? false) ? '1' : '0';
    $appointmentReminders = is_checked($_POST['appointment_reminders'] ?? false) ? '1' : '0';

    db()->prepare('UPDATE users SET consultation_fee = ?, visiting_hours = ?, bio = ? WHERE id = ?')
      ->execute([$consultationFee, $visitingHours !== '' ? $visitingHours : null, $bio !== '' ? $bio : null, $userId]);
    save_user_setting($userId, 'doctor_profile_visibility', $profileVisibility);
    save_user_setting($userId, 'doctor_accept_new_patients', $acceptNewPatients);
    save_user_setting($userId, 'doctor_appointment_reminders', $appointmentReminders);
    $_SESSION['success'] = 'Professional settings saved successfully.';
    redirect('../settings.php');
}

if ($formType === 'patient_preferences') {
    save_user_setting($userId, 'patient_profile_visibility', trim((string)($_POST['profile_visibility'] ?? 'Private')));
    save_user_setting($userId, 'patient_alert_channel', trim((string)($_POST['alert_channel'] ?? 'Email')));
    save_user_setting($userId, 'patient_emergency_contact', trim((string)($_POST['emergency_contact'] ?? '')));
    save_user_setting($userId, 'patient_appointment_reminders', is_checked($_POST['appointment_reminders'] ?? false) ? '1' : '0');
    save_user_setting($userId, 'patient_lab_alerts', is_checked($_POST['lab_alerts'] ?? false) ? '1' : '0');
    save_user_setting($userId, 'patient_data_access_updates', is_checked($_POST['data_access_updates'] ?? false) ? '1' : '0');
    $_SESSION['success'] = 'Patient preferences saved successfully.';
    redirect('../settings.php');
}

if ($formType === 'pharmacist_preferences') {
    save_user_setting($userId, 'pharmacy_service_hours', trim((string)($_POST['service_hours'] ?? '8:00 AM - 8:00 PM')));
    save_user_setting($userId, 'pharmacy_inventory_alert_level', trim((string)($_POST['inventory_alert_level'] ?? '10')));
    save_user_setting($userId, 'pharmacy_low_stock_alerts', is_checked($_POST['low_stock_alerts'] ?? false) ? '1' : '0');
    save_user_setting($userId, 'pharmacy_refill_notifications', is_checked($_POST['refill_notifications'] ?? false) ? '1' : '0');
    $_SESSION['success'] = 'Pharmacy settings saved successfully.';
    redirect('../settings.php');
}

if ($formType === 'lab_preferences') {
    save_user_setting($userId, 'lab_default_section', trim((string)($_POST['default_section'] ?? 'Biochemistry')));
    save_user_setting($userId, 'lab_turnaround', trim((string)($_POST['turnaround'] ?? 'Same Day')));
    save_user_setting($userId, 'lab_result_notif', trim((string)($_POST['result_notif'] ?? 'Email')));
    save_user_setting($userId, 'lab_auto_review', is_checked($_POST['auto_review'] ?? false) ? '1' : '0');
    save_user_setting($userId, 'lab_priority_alerts', is_checked($_POST['priority_alerts'] ?? false) ? '1' : '0');
    $_SESSION['success'] = 'Lab settings saved successfully.';
    redirect('../settings.php');
}

if ($formType === 'admin_preferences') {
    save_user_setting($userId, 'hospital_dashboard_focus', trim((string)($_POST['dashboard_focus'] ?? 'Overview')));
    save_user_setting($userId, 'hospital_escalation_channel', trim((string)($_POST['escalation_channel'] ?? 'Email')));
    save_user_setting($userId, 'hospital_review_cadence', trim((string)($_POST['review_cadence'] ?? 'Weekly')));
    save_user_setting($userId, 'hospital_staff_alerts', is_checked($_POST['staff_alerts'] ?? false) ? '1' : '0');
    save_user_setting($userId, 'hospital_approval_reminders', is_checked($_POST['approval_reminders'] ?? false) ? '1' : '0');
    $_SESSION['success'] = 'Administration settings saved successfully.';
    redirect('../settings.php');
}

if ($formType === 'system_preferences') {
    save_user_setting($userId, 'system_alert_level', trim((string)($_POST['alert_level'] ?? 'Standard')));
    save_user_setting($userId, 'system_digest_frequency', trim((string)($_POST['digest_frequency'] ?? 'Daily')));
    save_user_setting($userId, 'system_ops_alias', trim((string)($_POST['ops_alias'] ?? 'NHRE Operations')));
    save_user_setting($userId, 'system_maintenance_alerts', is_checked($_POST['maintenance_alerts'] ?? false) ? '1' : '0');
    save_user_setting($userId, 'system_audit_digest', is_checked($_POST['audit_digest'] ?? false) ? '1' : '0');
    $_SESSION['success'] = 'System settings saved successfully.';
    redirect('../settings.php');
}

$_SESSION['errors'] = ['Unsupported settings update.'];
redirect('../settings.php');
