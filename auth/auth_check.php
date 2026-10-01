<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

function session_start_secure(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path' => '/',
        'domain' => '',
        'secure' => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

session_start_secure();

function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/** Return an uploaded/saved avatar, or a stable generated portrait for the account. */
function user_avatar_url(?string $profilePhoto, int $userId, string $role = ''): string
{
    $photo = trim((string)$profilePhoto);
    if ($photo !== '') {
        return $photo;
    }

    // Keep the established patient-facing doctor portrait for the doctor's own account too.
    $seed = $role === 'Doctor' ? 'nhre-doctor-' . $userId : 'nhre-user-' . $userId;
    return 'https://api.dicebear.com/9.x/avataaars/svg?seed=' . rawurlencode($seed)
        . '&backgroundColor=b6e3f4,c0aede,d1d4f9&radius=50';
}

/** Whole years elapsed since a Y-m-d date of birth. */
function age_from_dob(string $dateOfBirth): int
{
    $dob = DateTimeImmutable::createFromFormat('Y-m-d', $dateOfBirth);
    if ($dob === false) {
        return 0;
    }
    return $dob->diff(new DateTimeImmutable('today'))->y;
}

function session_pull(string $key, mixed $default = null): mixed
{
    $value = $_SESSION[$key] ?? $default;
    unset($_SESSION[$key]);
    return $value;
}

function valid_roles(): array
{
    return ['Patient', 'Doctor', 'Pharmacist', 'Lab Technician', 'Hospital Admin', 'System Admin'];
}

/** Ensure every account has one stable, non-empty NHRE identifier. */
function ensure_account_numbers(): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    try {
        db()->exec("UPDATE users SET account_number = CONCAT('NHRE-', LPAD(id, 8, '0')) WHERE account_number IS NULL OR TRIM(account_number) = ''");
    } catch (PDOException $e) {
        // Account pages still work during initial schema setup; retry on the next request.
    }
}

/** Roles a visitor may self-select at registration. Administrative roles are provisioned only. */
function self_service_roles(): array
{
    return ['Patient', 'Doctor', 'Pharmacist', 'Lab Technician'];
}

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

/** Resolve a same-level page path relative to the current script's directory (handles /auth/ subfolder). */
function sibling_path(string $page): string
{
    $dir = str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '')));
    return str_ends_with($dir, '/auth') ? '../' . $page : $page;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_check(?string $token): bool
{
    return is_string($token)
        && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function require_auth(): void
{
    if (empty($_SESSION['user_id'])) {
        redirect(sibling_path('login.php'));
    }
}

/** @param list<string> $roles */
function require_role(array $roles): void
{
    require_auth();
    if (!in_array((string)($_SESSION['role'] ?? ''), $roles, true)) {
        $_SESSION['errors'] = ['You do not have permission to access that workspace.'];
        redirect(sibling_path('dashboard.php'));
    }
}

function redirect_if_authenticated(): void
{
    if (!empty($_SESSION['user_id'])) {
        redirect('dashboard.php');
    }
}

function login_user(array $user, bool $remember = false): void
{
    session_regenerate_id(true);

    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['fullname'] = $user['fullname'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role'] = $user['role'];

    if ($remember) {
        $raw = bin2hex(random_bytes(32));
        $hash = hash('sha256', $raw);

        $stmt = db()->prepare('DELETE FROM auth_tokens WHERE user_id = ?');
        $stmt->execute([(int)$user['id']]);

        $stmt = db()->prepare(
            'INSERT INTO auth_tokens (user_id, token_hash, expires_at)
             VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? DAY))'
        );
        $stmt->execute([(int)$user['id'], $hash, COOKIE_REMEMBER_DAYS]);

        setcookie(COOKIE_REMEMBER, $raw, [
            'expires' => time() + COOKIE_REMEMBER_DAYS * 86400,
            'path' => '/',
            'domain' => '',
            'secure' => is_https(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}

function remember_me_login(): void
{
    if (!empty($_SESSION['user_id'])) {
        return;
    }

    $raw = $_COOKIE[COOKIE_REMEMBER] ?? null;
    if (!is_string($raw) || $raw === '') {
        return;
    }

    try {
        $stmt = db()->prepare(
            'SELECT t.user_id, u.fullname, u.email, u.role
             FROM auth_tokens t
             JOIN users u ON u.id = t.user_id
             WHERE t.token_hash = ? AND t.expires_at > NOW()
             LIMIT 1'
        );
        $stmt->execute([hash('sha256', $raw)]);
        $user = $stmt->fetch();

        if ($user) {
            login_user($user);
        }
    } catch (PDOException $e) {
    }
}

function unread_notification_count(int $user_id): int
{
    try {
        $stmt = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
        $stmt->execute([$user_id]);
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

function ensure_notification_links_column(): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    try {
        $column = db()->query("SHOW COLUMNS FROM notifications LIKE 'target_path'")->fetch();
        if (!$column) {
            db()->exec('ALTER TABLE notifications ADD COLUMN target_path VARCHAR(255) NULL AFTER notification_type');
        }
    } catch (PDOException $e) {
    }
}

/** Return a page that the given role is permitted to open for a notification type. */
function notification_destination(string $type, string $role): string
{
    $type = strtolower(trim($type));
    return match ($type) {
        'appointment' => in_array($role, ['Patient', 'Doctor', 'Hospital Admin', 'System Admin'], true) ? 'appointments.php' : 'dashboard.php',
        'medical_test' => in_array($role, ['Patient', 'Doctor', 'Lab Technician', 'Hospital Admin', 'System Admin'], true) ? 'medical_tests.php' : 'dashboard.php',
        'vaccination' => in_array($role, ['Patient', 'Lab Technician'], true) ? 'vaccination.php' : 'dashboard.php',
        'pharmacy' => in_array($role, ['Patient', 'Pharmacist'], true) ? 'pharmacy.php' : 'dashboard.php',
        'prescription' => in_array($role, ['Patient', 'Doctor', 'Pharmacist'], true) ? 'prescriptions.php' : 'dashboard.php',
        'stock' => $role === 'Pharmacist' ? 'stock.php' : 'dashboard.php',
        'access' => $role === 'Patient' ? 'data_access.php' : ($role === 'Doctor' ? 'access_requests.php' : 'dashboard.php'),
        default => 'dashboard.php',
    };
}

function create_notification(int $user_id, string $title, string $message, string $type = 'general'): void
{
    try {
        ensure_notification_links_column();
        $roleStmt = db()->prepare('SELECT role FROM users WHERE id = ? LIMIT 1');
        $roleStmt->execute([$user_id]);
        $targetPath = notification_destination($type, (string)$roleStmt->fetchColumn());
        $stmt = db()->prepare(
            'INSERT INTO notifications (user_id, title, message, notification_type, target_path)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$user_id, $title, $message, $type, $targetPath]);
    } catch (PDOException $e) {
    }
}

/** Create the clinical tables used by records/document pages without replacing existing data. */
function ensure_clinical_tables(): void
{
    $pdo = db();
    $pdo->exec("CREATE TABLE IF NOT EXISTS allergies (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, patient_id INT UNSIGNED NOT NULL,
        allergy_type VARCHAR(40) NOT NULL, name VARCHAR(150) NOT NULL, reaction_text VARCHAR(255) NULL,
        severity VARCHAR(20) NOT NULL DEFAULT 'Moderate', notes TEXT NULL, recorded_at DATE NOT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_allergies_patient (patient_id), CONSTRAINT fk_allergies_patient FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS medical_documents (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, patient_id INT UNSIGNED NOT NULL, uploaded_by INT UNSIGNED NOT NULL,
        category VARCHAR(60) NOT NULL, original_name VARCHAR(255) NOT NULL, stored_name VARCHAR(100) NOT NULL,
        mime_type VARCHAR(100) NOT NULL, file_size INT UNSIGNED NOT NULL, source_name VARCHAR(150) NULL, notes TEXT NULL,
        verification_status VARCHAR(30) NOT NULL DEFAULT 'Pending Verification', verification_note TEXT NULL,
        verified_by INT UNSIGNED NULL, verified_at DATETIME NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_documents_patient (patient_id), CONSTRAINT fk_documents_patient FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS clinical_encounters (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, appointment_id INT UNSIGNED NULL, patient_id INT UNSIGNED NOT NULL, doctor_id INT UNSIGNED NOT NULL,
        diagnosis TEXT NULL, clinical_notes TEXT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_encounter_appointment (appointment_id), KEY idx_encounter_patient (patient_id),
        CONSTRAINT fk_encounter_patient FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE,
        CONSTRAINT fk_encounter_doctor FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    try { $pdo->exec("ALTER TABLE notifications ADD COLUMN related_url VARCHAR(255) NULL, ADD COLUMN event_key VARCHAR(120) NULL, ADD UNIQUE KEY uq_notifications_event (user_id, event_key)"); } catch (PDOException $e) {}
    try { $pdo->exec("ALTER TABLE appointments ADD COLUMN status_updated_at DATETIME NULL, ADD COLUMN rejection_reason TEXT NULL"); } catch (PDOException $e) {}
}

function create_event_notification(PDO $pdo, int $userId, string $title, string $message, string $type, string $eventKey, string $url): void
{
    $stmt = $pdo->prepare('INSERT INTO notifications (user_id, title, message, notification_type, target_path, related_url, event_key) VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE title = VALUES(title), message = VALUES(message), created_at = CURRENT_TIMESTAMP, is_read = 0, target_path = VALUES(target_path), related_url = VALUES(related_url)');
    $stmt->execute([$userId, $title, $message, $type, $url, $url, $eventKey]);
}

function ensure_appointments_table_exists(): void
{
    db()->exec(
        'CREATE TABLE IF NOT EXISTS `appointments` (
          `appointment_id`   INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `patient_id`       INT UNSIGNED NOT NULL,
          `doctor_id`        INT UNSIGNED NOT NULL,
          `appointment_date` DATE            NOT NULL,
          `appointment_time` TIME            NOT NULL,
          `reason`           TEXT            NOT NULL,
          `status`           VARCHAR(30)     NOT NULL DEFAULT \'Pending\',
          `doctor_notes`     TEXT            NULL,
          `created_at`       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`appointment_id`),
          KEY `idx_appointments_patient` (`patient_id`),
          KEY `idx_appointments_doctor` (`doctor_id`),
          CONSTRAINT `fk_appointments_patient`
            FOREIGN KEY (`patient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
          CONSTRAINT `fk_appointments_doctor`
            FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
    );

    try {
        db()->exec('CREATE UNIQUE INDEX `uq_appointments_slot` ON `appointments` (`doctor_id`, `appointment_date`, `appointment_time`)');
    } catch (PDOException $e) {
    }
}

function ensure_doctor_profile_columns(): void
{
    try {
        $stmt = db()->query('SHOW COLUMNS FROM users');
        $cols = [];
        foreach ($stmt->fetchAll() as $col) {
            $cols[] = $col['Field'];
        }

        $add = [];
        if (!in_array('district', $cols, true)) {
            $add[] = 'ADD COLUMN `district` VARCHAR(100) NULL DEFAULT NULL';
        }
        if (!in_array('hospital_name', $cols, true)) {
            $add[] = 'ADD COLUMN `hospital_name` VARCHAR(190) NULL DEFAULT NULL';
        }
        if (!in_array('specialization', $cols, true)) {
            $add[] = 'ADD COLUMN `specialization` VARCHAR(100) NULL DEFAULT NULL';
        }
        if (!in_array('qualification', $cols, true)) {
            $add[] = 'ADD COLUMN `qualification` VARCHAR(255) NULL DEFAULT NULL';
        }
        if (!in_array('experience_years', $cols, true)) {
            $add[] = 'ADD COLUMN `experience_years` INT UNSIGNED NULL DEFAULT NULL';
        }
        if (!in_array('consultation_fee', $cols, true)) {
            $add[] = 'ADD COLUMN `consultation_fee` INT UNSIGNED NULL DEFAULT NULL';
        }
        if (!in_array('rating', $cols, true)) {
            $add[] = 'ADD COLUMN `rating` DECIMAL(2,1) NULL DEFAULT NULL';
        }
        if (!in_array('reviews_count', $cols, true)) {
            $add[] = 'ADD COLUMN `reviews_count` INT UNSIGNED NULL DEFAULT NULL';
        }
        if (!in_array('district_id', $cols, true)) {
            $add[] = 'ADD COLUMN `district_id` INT UNSIGNED NULL DEFAULT NULL';
        }
        if (!in_array('hospital_id', $cols, true)) {
            $add[] = 'ADD COLUMN `hospital_id` INT UNSIGNED NULL DEFAULT NULL';
        }
        if (!in_array('specialization_id', $cols, true)) {
            $add[] = 'ADD COLUMN `specialization_id` INT UNSIGNED NULL DEFAULT NULL';
        }
        if (!in_array('bio', $cols, true)) {
            $add[] = 'ADD COLUMN `bio` TEXT NULL DEFAULT NULL';
        }
        if (!in_array('visiting_hours', $cols, true)) {
            $add[] = 'ADD COLUMN `visiting_hours` VARCHAR(255) NULL DEFAULT NULL';
        }
        if (!in_array('awards', $cols, true)) {
            $add[] = 'ADD COLUMN `awards` TEXT NULL DEFAULT NULL';
        }
        if (!in_array('is_featured', $cols, true)) {
            $add[] = 'ADD COLUMN `is_featured` TINYINT(1) NOT NULL DEFAULT 0';
        }

        foreach ($add as $sql) {
            db()->exec('ALTER TABLE users ' . $sql);
        }
    } catch (PDOException $e) {
    }
}

function ensure_doctor_catalog_tables(): void
{
    db()->exec(
        'CREATE TABLE IF NOT EXISTS `districts` (
          `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `name`        VARCHAR(100) NOT NULL,
          `description` VARCHAR(255) NULL DEFAULT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uq_districts_name` (`name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
    );

    db()->exec(
        'CREATE TABLE IF NOT EXISTS `specializations` (
          `id`   INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `name` VARCHAR(120) NOT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uq_specializations_name` (`name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
    );

    db()->exec(
        'CREATE TABLE IF NOT EXISTS `hospitals` (
          `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `name`          VARCHAR(190) NOT NULL,
          `district_id`   INT UNSIGNED NULL DEFAULT NULL,
          `address`       VARCHAR(255) NULL DEFAULT NULL,
          `phone`         VARCHAR(30) NULL DEFAULT NULL,
          `email`         VARCHAR(190) NULL DEFAULT NULL,
          `is_active`     TINYINT(1) NOT NULL DEFAULT 1,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uq_hospitals_name` (`name`),
          KEY `idx_hospitals_district` (`district_id`),
          CONSTRAINT `fk_hospitals_district`
            FOREIGN KEY (`district_id`) REFERENCES `districts` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
    );

    try {
        $districtCount = (int)db()->query('SELECT COUNT(*) FROM districts')->fetchColumn();
        if ($districtCount === 0) {
            $districts = ['Dhaka', 'Chattogram', 'Rajshahi', 'Khulna', 'Barishal', 'Sylhet', 'Rangpur', 'Mymensingh'];
            $stmt = db()->prepare('INSERT INTO districts (name, description) VALUES (?, ?)');
            foreach ($districts as $district) {
                $stmt->execute([$district, 'Seeded district']);
            }
        }

        $specializationCount = (int)db()->query('SELECT COUNT(*) FROM specializations')->fetchColumn();
        if ($specializationCount === 0) {
            $specializations = [
                'Cardiology', 'Orthopedics', 'Neurology', 'Dermatology', 'Gynecology', 'Pediatrics',
                'General Medicine', 'General Surgery', 'ENT', 'Ophthalmology', 'Psychiatry', 'Urology',
                'Gastroenterology', 'Nephrology', 'Endocrinology', 'Oncology', 'Pulmonology', 'Rheumatology',
                'Hematology', 'Infectious Disease', 'Neurosurgery', 'Cardiothoracic Surgery', 'Plastic Surgery',
                'Pediatric Surgery', 'Vascular Surgery', 'Oral & Maxillofacial Surgery', 'Anesthesiology', 'Radiology',
                'Pathology', 'Physical Medicine', 'Rehabilitation', 'Emergency Medicine', 'Family Medicine',
                'Internal Medicine', 'Dental Surgery', 'Periodontology', 'Prosthodontics', 'Orthodontics', 'Oral Medicine',
                'Clinical Psychology', 'Nutrition', 'Physiotherapy', 'Pain Medicine', 'Critical Care', 'Sleep Medicine',
                'Allergy & Immunology', 'Hepatology', 'Neonatology', 'Maternal-Fetal Medicine', 'Sports Medicine', 'Geriatric Medicine'
            ];
            $stmt = db()->prepare('INSERT INTO specializations (name) VALUES (?)');
            foreach ($specializations as $specialization) {
                $stmt->execute([$specialization]);
            }
        }

        $hospitalCount = (int)db()->query('SELECT COUNT(*) FROM hospitals')->fetchColumn();
        if ($hospitalCount === 0) {
            $districtRows = db()->query('SELECT id, name FROM districts ORDER BY id')->fetchAll();
            $districtIds = array_column($districtRows, 'id');
            $hospitalNames = [
                'Square Hospital', 'Labaid Specialized Hospital', 'United Hospital', 'Apollo Hospitals', 'Evercare Hospital',
                'Central Hospital', 'Popular Diagnostic Centre', 'Delta Medical College Hospital', 'Ibn Sina Hospital', 'Holy Family Red Crescent',
                'Bangladesh Specialized Hospital', 'CMB Hospital', 'Renata Hospital', 'Anwar Khan Modern Medical College Hospital', 'MIR Dental Hospital',
                'Sunrise Hospital', 'North City Hospital', 'Nightingale Hospital', 'BIRDEM General Hospital', 'Sajida Hospital', 'Ahsania Mission Hospital',
                'Purbachal General Hospital', 'Dhanmondi Medical Center', 'Gulshan Diagnostic Hospital', 'Banani Clinic', 'Uttara Clinical Services',
                'Savar Community Hospital', 'Shahbagh Medical Centre', 'Mohakhali Hospital', 'Bogra General Hospital', 'Khulna Medical College Hospital',
                'Barishal General Hospital', 'Sylhet Womens Medical College Hospital', 'Rangpur Community Hospital', 'Mymensingh General Hospital',
                'Chattogram Medical Center', 'Comilla General Hospital', 'Noakhali Community Hospital', 'Pabna Diagnostic Center', 'Narayanganj General Hospital'
            ];
            $stmt = db()->prepare('INSERT INTO hospitals (name, district_id, address, phone, email) VALUES (?, ?, ?, ?, ?)');
            foreach ($hospitalNames as $index => $name) {
                $districtId = $districtIds[$index % count($districtIds)];
                $districtIndex = array_search($districtId, $districtIds, true);
                $districtName = $districtIndex !== false ? ($districtRows[$districtIndex]['name'] ?? '') : '';
                $stmt->execute([
                    $name,
                    $districtId,
                    $name . ', ' . $districtName,
                    '+880171' . sprintf('%08d', $index + 1000),
                    strtolower(str_replace(' ', '', $name)) . '@nhre.dev'
                ]);
            }
        }

        // Keep the bundled Hospital Admin usable while preserving hospital-level scope.
        db()->exec("UPDATE users SET hospital_id = (SELECT id FROM hospitals ORDER BY id LIMIT 1)
                    WHERE email = 'admin@nhre.gov' AND role = 'Hospital Admin' AND hospital_id IS NULL");

        $doctorCount = (int)db()->query("SELECT COUNT(*) FROM users WHERE role = 'Doctor'")->fetchColumn();
        if ($doctorCount < 100) {
            $districtRows = db()->query('SELECT id, name FROM districts ORDER BY id')->fetchAll();
            $specializationRows = db()->query('SELECT id, name FROM specializations ORDER BY id')->fetchAll();
            $hospitalRows = db()->query('SELECT id, name FROM hospitals ORDER BY id')->fetchAll();
            $firstNames = ['Afsana', 'Arif', 'Nadia', 'Rahim', 'Sadia', 'Tamim', 'Farah', 'Imran', 'Muna', 'Khaled', 'Rafi', 'Shila', 'Nabil', 'Zarin', 'Asif', 'Mahir', 'Tanjin', 'Ruma', 'Sami', 'Jahan', 'Riaz', 'Miftah', 'Sohana', 'Pranto', 'Amina', 'Hasan', 'Mourin', 'Rayhan', 'Tasnima', 'Lamia', 'Nafi', 'Rony', 'Maliha', 'Shuvo', 'Bithi', 'Tareq', 'Mita', 'Ishrat', 'Shafiq', 'Nazia', 'Anik', 'Faria', 'Rifat', 'Moushumi', 'Sajid', 'Prapti', 'Atik', 'Nusrat', 'Maruf', 'Nishat'];
            $lastNames = ['Ahmed', 'Rahman', 'Hossain', 'Karim', 'Islam', 'Chowdhury', 'Haque', 'Mahmud', 'Akter', 'Ali', 'Sultana', 'Khan', 'Banu', 'Siddique', 'Talukder', 'Mia', 'Noor', 'Begum', 'Das', 'Paul', 'Ferdous', 'Jahan', 'Rafiq', 'Mou', 'Ahamed', 'Yasmin', 'Hassan', 'Chowdhury', 'Hasan', 'Salam'];
            $qualifications = ['MBBS, FCPS', 'MBBS, MD', 'MBBS, MRCP', 'MBBS, FRCS', 'MBBS, MCPS', 'MBBS, MPH', 'MBBS, DM'];
            $bios = [
                'Dedicated clinician focused on preventive care and patient education.',
                'Known for compassionate care and evidence-based treatment plans.',
                'Specializes in advanced diagnostics and long-term chronic disease management.',
                'Committed to accessible care with a strong community health focus.'
            ];
            $awards = [
                'Best Physician Award 2024',
                'Excellence in Care Award 2023',
                'National Medical Leadership Award',
                'Community Health Service Award'
            ];
            $visitingHours = ['09:00,10:30,11:30,15:00', '10:00,11:00,14:00,16:00', '09:30,11:00,15:30,17:00', '08:30,10:00,14:30,17:30'];
            $stmt = db()->prepare(
                'INSERT INTO users (fullname, nid, email, phone, password_hash, role, gender, address, district, hospital_name, specialization, qualification, experience_years, consultation_fee, rating, reviews_count, district_id, hospital_id, specialization_id, bio, visiting_hours, awards, is_featured)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            for ($index = $doctorCount + 1; $index <= 100; $index++) {
                $firstName = $firstNames[($index - 1) % count($firstNames)];
                $lastName = $lastNames[($index - 1) % count($lastNames)];
                $fullname = $firstName . ' ' . $lastName;
                $specialization = $specializationRows[($index - 1) % count($specializationRows)];
                $hospital = $hospitalRows[($index - 1) % count($hospitalRows)];
                $district = $districtRows[($index - 1) % count($districtRows)];
                $gender = $index % 2 === 0 ? 'Female' : 'Male';
                $qualification = $qualifications[($index - 1) % count($qualifications)];
                $experience = 5 + (($index - 1) % 18) + ($index % 3 === 0 ? 3 : 0);
                $fee = 600 + (($index % 10) * 100) + ($index % 3 === 0 ? 150 : 0);
                $rating = round(4.2 + (($index % 7) * 0.1), 1);
                $reviews = 45 + ($index * 7);
                $stmt->execute([
                    $fullname,
                    '100000000' . str_pad((string)$index, 2, '0', STR_PAD_LEFT),
                    'doctor' . str_pad((string)$index, 3, '0', STR_PAD_LEFT) . '@nhre.dev',
                    '+88017' . str_pad((string)(10000000 + $index), 8, '0', STR_PAD_LEFT),
                    password_hash('Doctor' . str_pad((string)$index, 3, '0', STR_PAD_LEFT) . '!', PASSWORD_DEFAULT),
                    'Doctor',
                    $gender,
                    $district['name'] . ' Medical Center',
                    $district['name'],
                    $hospital['name'],
                    $specialization['name'],
                    $qualification,
                    $experience,
                    $fee,
                    $rating,
                    $reviews,
                    $district['id'],
                    $hospital['id'],
                    $specialization['id'],
                    $bios[($index - 1) % count($bios)],
                    $visitingHours[($index - 1) % count($visitingHours)],
                    $awards[($index - 1) % count($awards)],
                    $index % 5 === 0 ? 1 : 0
                ]);
            }

            $patientCount = (int)db()->query("SELECT COUNT(*) FROM users WHERE email = 'patient@nhre.gov'")->fetchColumn();
            if ($patientCount === 0) {
                db()->prepare(
                    'INSERT INTO users (fullname, nid, email, phone, password_hash, role, gender, address, district, hospital_name, specialization, qualification, experience_years, consultation_fee, rating, reviews_count)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                )->execute([
                    'Demo Patient',
                    '2000000000',
                    'patient@nhre.gov',
                    '+8801712345678',
                    password_hash('Patient123!', PASSWORD_DEFAULT),
                    'Patient',
                    'Female',
                    'House 12, Road 4, Dhanmondi',
                    'Dhaka',
                    '',
                    '',
                    '',
                    null,
                    null,
                    null,
                    null
                ]);
            }

            $patientRow = db()->query("SELECT id FROM users WHERE email = 'patient@nhre.gov' LIMIT 1")->fetch();
            $patientId = $patientRow ? (int)$patientRow['id'] : 0;
            if ($patientId > 0) {
                $appointmentCount = (int)db()->query('SELECT COUNT(*) FROM appointments')->fetchColumn();
                if ($appointmentCount === 0) {
                    $doctorRows = db()->query("SELECT id FROM users WHERE role = 'Doctor' ORDER BY id ASC LIMIT 6")->fetchAll();
                    $sampleAppointments = [
                        ['Pending', 'Needs follow-up on recurring headache.'],
                        ['Approved', 'Annual blood pressure review.'],
                        ['Pending', 'Chest discomfort and shortness of breath.'],
                        ['Approved', 'Skin rash follow-up appointment.'],
                        ['Pending', 'Post-surgery recovery review.'],
                        ['Approved', 'Pediatric fever assessment.']
                    ];
                    $insertAppointment = db()->prepare(
                        'INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, reason, status, doctor_notes)
                         VALUES (?, ?, ?, ?, ?, ?, ?)'
                    );
                    $date = date('Y-m-d');
                    $timeSlots = ['09:00:00', '10:30:00', '13:00:00', '15:30:00', '17:00:00', '18:30:00'];
                    foreach ($doctorRows as $index => $doctorRow) {
                        $insertAppointment->execute([
                            $patientId,
                            (int)$doctorRow['id'],
                            $date,
                            $timeSlots[$index % count($timeSlots)],
                            $sampleAppointments[$index][1],
                            $sampleAppointments[$index][0],
                            $index % 2 === 0 ? 'Please bring recent reports.' : ''
                        ]);
                    }
                }
            }
        }

        ensure_seeded_doctor_credentials();
        ensure_demo_patients_and_records();
    } catch (PDOException $e) {
    }
}

/**
 * Give every generated doctor account an individual demo password.  Existing
 * accounts are migrated only when they still use the former shared password.
 */
function ensure_seeded_doctor_credentials(): void
{
    try {
        $pdo = db();
        $pdo->exec('CREATE TABLE IF NOT EXISTS `application_settings` (
            `setting_key` VARCHAR(100) NOT NULL,
            `setting_value` VARCHAR(255) NULL DEFAULT NULL,
            PRIMARY KEY (`setting_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $completed = $pdo->prepare('SELECT 1 FROM application_settings WHERE setting_key = ? LIMIT 1');
        $completed->execute(['seeded_doctor_credentials_v1']);
        if ($completed->fetchColumn()) {
            return;
        }

        $doctors = $pdo->query("SELECT id, email, password_hash FROM users WHERE role = 'Doctor' AND email LIKE 'doctor%@nhre.dev'")->fetchAll();
        $update = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');

        foreach ($doctors as $doctor) {
            if (!preg_match('/^doctor(\\d{3})@nhre\\.dev$/', (string)$doctor['email'], $matches)
                || !password_verify('Doctor123!', (string)$doctor['password_hash'])) {
                continue;
            }

            $password = 'Doctor' . $matches[1] . '!';
            $update->execute([password_hash($password, PASSWORD_DEFAULT), (int)$doctor['id']]);
        }

        $pdo->prepare('INSERT INTO application_settings (setting_key, setting_value) VALUES (?, ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)')->execute([
                'seeded_doctor_credentials_v1',
                date('c'),
            ]);
    } catch (PDOException $e) {
    }
}

/** Seed 25 clearly labelled, repeatable demo patients and varied basic clinical records. */
function ensure_demo_patients_and_records(): void
{
    ensure_clinical_tables();
    $names = ['Amina Sultana','Rafiq Hasan','Nusrat Jahan','Shafiq Ahmed','Farzana Akter','Imran Hossain','Maliha Noor','Tanvir Islam','Sadia Rahman','Kamal Uddin','Lamia Chowdhury','Arif Mahmud','Ruma Khan','Nabil Karim','Ishrat Banu','Samiul Haque','Tasnima Das','Mahir Paul','Bithi Begum','Rony Mia','Faria Yasmin','Atik Rahman','Moushumi Akter','Sajid Hasan','Prapti Sultana'];
    $districts = ['Dhaka','Chattogram','Rajshahi','Khulna','Sylhet','Barishal','Rangpur','Mymensingh'];
    $blood = ['A+','B+','O+','AB+','A-','B-','O-','AB-'];
    $pdo = db();
    $exists = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $insert = $pdo->prepare('INSERT INTO users (fullname,nid,email,phone,password_hash,role,account_number,date_of_birth,gender,address,district,blood_group,emergency_contact,occupation) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $allergy = $pdo->prepare('INSERT INTO allergies (patient_id,allergy_type,name,reaction_text,severity,notes,recorded_at) SELECT ?,?,?,?,?,?,CURDATE() WHERE NOT EXISTS (SELECT 1 FROM allergies WHERE patient_id = ? AND name = ?)');
    foreach ($names as $offset => $name) {
        $n = $offset + 1; $email = $n === 1 ? 'patient@nhre.gov' : 'patient' . str_pad((string)$n, 3, '0', STR_PAD_LEFT) . '@nhre.demo';
        $exists->execute([$email]); $id = (int)$exists->fetchColumn();
        if ($id === 0) {
            $insert->execute([$n === 1 ? 'Demo Patient A — ' . $name : 'DEMO Patient ' . $name, '2000000' . str_pad((string)$n, 3, '0', STR_PAD_LEFT), $email, '+88018' . str_pad((string)(10000000 + $n), 8, '0', STR_PAD_LEFT), password_hash('Patient123!', PASSWORD_DEFAULT), 'Patient', 'NHRE-P-' . str_pad((string)$n, 6, '0', STR_PAD_LEFT), sprintf('%04d-%02d-%02d', 1980 + ($n % 22), 1 + ($n % 12), 1 + ($n % 27)), $n % 2 ? 'Female' : 'Male', 'DEMO address, ' . $districts[$offset % count($districts)], $districts[$offset % count($districts)], $blood[$offset % count($blood)], '+88019' . str_pad((string)(20000000 + $n), 8, '0', STR_PAD_LEFT), $n % 2 ? 'Teacher' : 'Engineer']);
            $id = (int)$pdo->lastInsertId();
        }
        $allergens = [['Drug','Penicillin','Rash','Moderate'],['Food','Peanuts','Hives','Severe'],['Environmental','Dust','Sneezing','Mild'],['Drug','Ibuprofen','Stomach upset','Moderate']];
        $item = $allergens[$offset % count($allergens)];
        $allergy->execute([$id,$item[0],$item[1],$item[2],$item[3],'DEMO clinical record only.',$id,$item[1]]);
    }
    // Keep the original demo patient's seeded requests visible after the calendar advances.
    $pdo->exec("UPDATE appointments a JOIN users p ON p.id = a.patient_id SET a.appointment_date = CURDATE() WHERE p.email = 'patient@nhre.gov' AND a.appointment_date < CURDATE()");
}

/**
 * Idempotently seed one demo account for every role that cannot self-register
 * (Pharmacist and Lab Technician already can self-register; these just provide
 * ready-made demo credentials). Passwords match the defaults shown on the login
 * page and in the README demo accounts table.
 */
function ensure_demo_accounts(): void
{
    $accounts = [
        ['Demo Patient',          '0000000001', 'patient@nhre.gov', '+8801000000001', 'Patient123!', 'Patient'],
        ['Hospital Administrator', '0000000002', 'admin@nhre.gov', '+8801000000002', 'Admin123!', 'Hospital Admin'],
        ['System Administrator',   '0000000003', 'sysadmin@nhre.gov', '+8801000000003', 'SysAdmin123!', 'System Admin'],
        ['Demo Pharmacist',        '0000000004', 'pharmacist@nhre.gov', '+8801000000004', 'Pharmacist123!', 'Pharmacist'],
        ['Demo Lab Technician',    '0000000005', 'lab@nhre.gov', '+8801000000005', 'Lab123!', 'Lab Technician'],
    ];

    try {
        $stmt = db()->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $insert = db()->prepare(
            'INSERT INTO users (fullname, nid, email, phone, password_hash, role)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        foreach ($accounts as $account) {
            $stmt->execute([$account[2]]);
            if ($stmt->fetch()) {
                continue;
            }
            $insert->execute([
                $account[0],
                $account[1],
                $account[2],
                $account[3],
                password_hash($account[4], PASSWORD_DEFAULT),
                $account[5],
            ]);
        }
    } catch (PDOException $e) {
    }
}

function get_doctor_time_slots(int $doctor_id, ?string $appointment_date = null, ?string $visiting_hours = null): array
{
    $default_slots = ['09:00', '10:00', '11:00', '14:00', '15:00', '16:00', '17:00'];
    $slots = [];
    if (is_string($visiting_hours) && trim($visiting_hours) !== '') {
        foreach (preg_split('/[;,]+/', $visiting_hours) as $slot) {
            $slot = trim($slot);
            if (preg_match('/^(\d{1,2}):(\d{2})$/', $slot, $matches)) {
                $hours = (int)$matches[1];
                $minutes = (int)$matches[2];
                if ($hours >= 0 && $hours <= 23 && $minutes >= 0 && $minutes <= 59) {
                    $slots[] = sprintf('%02d:%02d', $hours, $minutes);
                }
            }
        }
    }
    if ($slots === []) {
        $slots = $default_slots;
    }

    if ($appointment_date !== null && $appointment_date !== '') {
        $stmt = db()->prepare(
            'SELECT appointment_time FROM appointments WHERE doctor_id = ? AND appointment_date = ?'
        );
        $stmt->execute([$doctor_id, $appointment_date]);
        $booked = array_map(
            static fn ($time): string => substr((string)$time, 0, 5),
            array_column($stmt->fetchAll(), 'appointment_time')
        );
        $slots = array_values(array_filter($slots, static function (string $slot) use ($booked): bool {
            return !in_array($slot, $booked, true);
        }));
    }

    return $slots;
}

function ensure_doctor_ratings_table(): void
{
    db()->exec(
        'CREATE TABLE IF NOT EXISTS `doctor_ratings` (
          `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `doctor_id`   INT UNSIGNED NOT NULL,
          `patient_id`  INT UNSIGNED NOT NULL,
          `rating`      TINYINT UNSIGNED NOT NULL,
          `review`      TEXT NULL DEFAULT NULL,
          `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uq_ratings_doctor_patient` (`doctor_id`, `patient_id`),
          KEY `idx_ratings_doctor` (`doctor_id`),
          KEY `idx_ratings_patient` (`patient_id`),
          CONSTRAINT `fk_ratings_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
          CONSTRAINT `fk_ratings_patient` FOREIGN KEY (`patient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
    );
}

/** Most recent patient reviews for a doctor. */
function get_doctor_reviews(int $doctor_id, int $limit = 30): array
{
    ensure_doctor_ratings_table();
    $stmt = db()->prepare(
        'SELECT r.id, r.rating, r.review, r.created_at, r.updated_at, u.fullname AS patient_name
         FROM doctor_ratings r
         JOIN users u ON u.id = r.patient_id
         WHERE r.doctor_id = ?
         ORDER BY r.updated_at DESC, r.id DESC
         LIMIT ' . max(1, (int)$limit)
    );
    $stmt->execute([$doctor_id]);
    return $stmt->fetchAll();
}

/** The logged-in patient's own review for a doctor, if any. */
function get_patient_review(int $doctor_id, int $patient_id): ?array
{
    ensure_doctor_ratings_table();
    $stmt = db()->prepare(
        'SELECT id, rating, review FROM doctor_ratings WHERE doctor_id = ? AND patient_id = ? LIMIT 1'
    );
    $stmt->execute([$doctor_id, $patient_id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/** Font Awesome star row for a doctor's aggregate rating. */
function render_rating_stars(?float $rating): string
{
    $rating = (float)$rating;
    $out = '<span class="rating-stars" aria-label="' . e((string)round($rating, 1)) . ' out of 5 stars">';
    for ($i = 1; $i <= 5; $i++) {
        if ($rating >= $i - 0.25) {
            $icon = 'fa-solid fa-star';
        } elseif ($rating >= $i - 0.75) {
            $icon = 'fa-solid fa-star-half-stroke';
        } else {
            $icon = 'fa-regular fa-star';
        }
        $out .= '<i class="' . $icon . '"></i>';
    }
    return $out . '</span>';
}

function ensure_medical_test_tables_exists(): void
{
    ensure_vaccination_center_tables();
    db()->exec(
        'CREATE TABLE IF NOT EXISTS `medical_tests` (
          `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `name`              VARCHAR(190) NOT NULL,
          `description`       TEXT NULL,
          `test_type`         VARCHAR(100) NOT NULL,
          `price`             DECIMAL(10,2) NOT NULL DEFAULT 0.00,
          `place`             VARCHAR(120) NOT NULL,
          `department`        VARCHAR(120) NULL,
          `result_time`       VARCHAR(60) NOT NULL,
          `availability`      TINYINT(1) NOT NULL DEFAULT 1,
          `home_collection`   TINYINT(1) NOT NULL DEFAULT 0,
          `center_id`         INT UNSIGNED NULL,
          `created_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `idx_medical_tests_place` (`place`),
          KEY `idx_medical_tests_type` (`test_type`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
    );

    db()->exec(
        'CREATE TABLE IF NOT EXISTS `medical_test_bookings` (
          `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `test_id`           INT UNSIGNED NOT NULL,
          `user_id`           INT UNSIGNED NOT NULL,
          `booking_date`      DATE NOT NULL,
          `booking_time`      TIME NULL,
          `status`            VARCHAR(30) NOT NULL DEFAULT \'Pending\',
          `result_file`       VARCHAR(255) NULL,
          `result_notes`      TEXT NULL,
          `result_date`       DATE NULL,
          `technician_id`     INT UNSIGNED NULL,
          `created_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `idx_test_bookings_user` (`user_id`),
          KEY `idx_test_bookings_test` (`test_id`),
          CONSTRAINT `fk_test_bookings_test`
            FOREIGN KEY (`test_id`) REFERENCES `medical_tests` (`id`) ON DELETE CASCADE,
          CONSTRAINT `fk_test_bookings_user`
            FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
          CONSTRAINT `fk_test_bookings_technician`
            FOREIGN KEY (`technician_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
    );

    $count = (int)db()->query('SELECT COUNT(*) FROM medical_tests')->fetchColumn();
    if ($count === 0) {
        $seed = [
            ['CBC Test', 'A complete blood count check for overall health screening.', 'Blood Test', 500, 'Dhaka', 'Pathology', 'Same Day', 1, 1],
            ['Blood Sugar Test', 'Checks fasting and random glucose levels for diabetes screening.', 'Biochemistry', 300, 'Mirpur', 'Lab', 'Same Day', 1, 0],
            ['Lipid Profile', 'Measures cholesterol and triglyceride levels to assess heart risk.', 'Biochemistry', 700, 'Uttara', 'Biochemistry', '1 Day', 1, 1],
            ['X-Ray', 'Radiology imaging service for bones and chest evaluation.', 'Imaging', 1200, 'Dhanmondi', 'Radiology', '2 Days', 1, 0],
            ['COVID/Flu Test', 'Rapid screening for COVID-19 and flu-related symptoms.', 'COVID/Flu Test', 900, 'Banani', 'Pathology', 'Same Day', 1, 1],
            ['Thyroid Profile', 'Measures thyroid hormones for metabolic and hormonal balance.', 'Hormone Test', 850, 'Gulshan', 'Endocrinology', '1 Day', 1, 0],
        ];

        $stmt = db()->prepare(
            'INSERT INTO medical_tests (name, description, test_type, price, place, department, result_time, availability, home_collection) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        foreach ($seed as $row) {
            $stmt->execute($row);
        }
    }

    try {
        db()->exec('UPDATE medical_tests mt JOIN vaccination_centers vc ON vc.district = mt.place SET mt.center_id = vc.id WHERE mt.center_id IS NULL');
        $patientId = (int)db()->query("SELECT id FROM users WHERE email = 'patient@nhre.gov' AND role = 'Patient' LIMIT 1")->fetchColumn();
        if ($patientId > 0) {
            $seedBooking = db()->prepare('INSERT INTO medical_test_bookings (test_id, user_id, booking_date, booking_time, status, result_notes, created_at, updated_at) SELECT id, ?, ?, ?, ?, ?, NOW(), NOW() FROM medical_tests WHERE center_id IS NOT NULL ORDER BY id LIMIT 1');
            $statusCount = db()->prepare('SELECT COUNT(*) FROM medical_test_bookings WHERE user_id = ? AND status = ?');
            foreach ([
                ['Pending', '+2 days', '10:30:00', 'Demo pathology booking awaiting review.'],
                ['Completed', '-2 days', '11:00:00', 'Demo CBC result: values are within the expected range.'],
                ['Cancelled', '-5 days', '09:00:00', 'Demo cancelled request for test-history review.'],
            ] as [$status, $dateOffset, $time, $notes]) {
                $statusCount->execute([$patientId, $status]);
                if ((int)$statusCount->fetchColumn() === 0) {
                    $seedBooking->execute([$patientId, date('Y-m-d', strtotime($dateOffset)), $time, $status, $notes]);
                }
            }
        }
    } catch (PDOException $e) {
    }
}

function ensure_pharmacy_requests_table_exists(): void
{
    db()->exec(
        'CREATE TABLE IF NOT EXISTS `pharmacy_requests` (
          `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `user_id`       INT UNSIGNED NOT NULL,
          `medicine_name` VARCHAR(190) NOT NULL,
          `notes`         TEXT NULL,
          `status`        VARCHAR(30)  NOT NULL DEFAULT \'Pending\',
          `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `idx_pharmacy_requests_user` (`user_id`),
          CONSTRAINT `fk_pharmacy_requests_user`
            FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
    );
}

/** Vaccine names tracked by the vaccination schedule. */
function vaccination_names(): array
{
    return ['BCG', 'DPT', 'Polio', 'Hepatitis B', 'Measles', 'MMR', 'Typhoid', 'Rabies', 'COVID-19', 'Influenza', 'HPV', 'Tetanus'];
}

function ensure_vaccination_center_tables(): void
{
    ensure_demo_accounts();
    db()->exec(
        'CREATE TABLE IF NOT EXISTS `vaccination_centers` (
          `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `name`        VARCHAR(190) NOT NULL,
          `district`    VARCHAR(100) NOT NULL,
          `division`    VARCHAR(100) NOT NULL,
          `center_type` VARCHAR(20)  NOT NULL DEFAULT \'Public\',
          `address`     VARCHAR(255) NULL,
          `phone`       VARCHAR(30)  NULL,
          `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uq_vaccination_centers_name` (`name`),
          KEY `idx_vaccination_centers_type` (`center_type`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
    );

    db()->exec(
        'CREATE TABLE IF NOT EXISTS `vaccination_center_prices` (
          `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `center_id`    INT UNSIGNED NOT NULL,
          `vaccine_name` VARCHAR(100) NOT NULL,
          `price`        INT UNSIGNED NOT NULL DEFAULT 0,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uq_center_vaccine` (`center_id`, `vaccine_name`),
          KEY `idx_vaccine_prices_vaccine` (`vaccine_name`),
          CONSTRAINT `fk_vaccination_center_prices_center`
            FOREIGN KEY (`center_id`) REFERENCES `vaccination_centers` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
    );

    try {
        $hasCenter = db()->query("SHOW COLUMNS FROM medical_tests LIKE 'center_id'")->fetch();
        if (!$hasCenter) {
            db()->exec('ALTER TABLE medical_tests ADD COLUMN center_id INT UNSIGNED NULL AFTER home_collection');
        }
        db()->exec('UPDATE medical_tests mt JOIN vaccination_centers vc ON vc.district = mt.place SET mt.center_id = vc.id WHERE mt.center_id IS NULL');
    } catch (PDOException $e) {
    }

    db()->exec(
        'CREATE TABLE IF NOT EXISTS `vaccination_bookings` (
          `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `user_id`         INT UNSIGNED NOT NULL,
          `vaccine_name`    VARCHAR(100) NOT NULL,
          `dose_number`     TINYINT UNSIGNED NOT NULL DEFAULT 1,
          `center_id`       INT UNSIGNED NULL,
          `booking_date`    DATE NOT NULL,
          `booking_time`    TIME NULL,
          `contact_phone`   VARCHAR(30) NOT NULL,
          `notes`           TEXT NULL,
          `status`          VARCHAR(30) NOT NULL DEFAULT \'Pending\',
          `technician_id`   INT UNSIGNED NULL,
          `status_notes`    TEXT NULL,
          `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `idx_vaccination_bookings_user` (`user_id`),
          KEY `idx_vaccination_bookings_status` (`status`),
          KEY `idx_vaccination_bookings_date` (`booking_date`),
          CONSTRAINT `fk_vaccination_bookings_user`
            FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
          CONSTRAINT `fk_vaccination_bookings_center`
            FOREIGN KEY (`center_id`) REFERENCES `vaccination_centers` (`id`) ON DELETE SET NULL,
          CONSTRAINT `fk_vaccination_bookings_technician`
            FOREIGN KEY (`technician_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
    );

    $centers = [
        ['ICDC Hospital EPI Centre', 'Dhaka', 'Dhaka', 'Public', 'Matuail, Demra', '+880-2-7561811'],
        ['Shaheed Suhrawardy Medical College Hospital', 'Dhaka', 'Dhaka', 'Public', 'Sher-e-Bangla Nagar', '+880-2-8121686'],
        ['Chattogram Medical College Hospital EPI Unit', 'Chattogram', 'Chattogram', 'Public', 'K.B. Fazlul Kader Road', '+880-31-632337'],
        ['Rajshahi Medical College Hospital EPI Unit', 'Rajshahi', 'Rajshahi', 'Public', 'Laxmipur, Rajshahi', '+880-721-772027'],
        ['Khulna Medical College Hospital EPI Unit', 'Khulna', 'Khulna', 'Public', 'Sonadanga', '+880-41-721204'],
        ['MAG Osmani Medical College Hospital EPI Unit', 'Sylhet', 'Sylhet', 'Public', 'Osmani Medical College Rd', '+880-821-713195'],
        ['Rangpur Medical College Hospital EPI Unit', 'Rangpur', 'Rangpur', 'Public', 'Medical College Road', '+880-521-51002'],
        ['Mymensingh Medical College Hospital EPI Unit', 'Mymensingh', 'Mymensingh', 'Public', 'Main Campus, Mymensingh', '+880-91-53612'],
        ['Barishal Medical College Hospital EPI Unit', 'Barishal', 'Barishal', 'Public', 'Nathullabad', '+880-431-64041'],
        ['Square Hospitals Ltd', 'Dhaka', 'Dhaka', 'Private', '18/F West Panthapath', '+880-2-8144400'],
        ['United Hospital', 'Dhaka', 'Dhaka', 'Private', 'Plot 15, Road 71, Gulshan', '+880-2-8836000'],
        ['Evercare Hospital Dhaka', 'Dhaka', 'Dhaka', 'Private', 'Plot 81, Block E, Bashundhara', '+880-9666781'],
        ['Labaid Specialized Hospital', 'Dhaka', 'Dhaka', 'Private', 'House 1, Road 4, Dhanmondi', '+880-2-9676301'],
        ['Popular Diagnostic Centre', 'Dhaka', 'Dhaka', 'Private', 'House 40, Road 11, Dhanmondi', '+880-2-8154197'],
        ['Ibn Sina Diagnostic & Consultation Centre', 'Dhaka', 'Dhaka', 'Private', 'House 48, Road 9/A, Dhanmondi', '+880-2-9128835'],
        ['Chattogram Metropolitan Hospital', 'Chattogram', 'Chattogram', 'Private', 'Khulshi', '+880-31-655600'],
        ['Rajshahi Diagnostic Centre', 'Rajshahi', 'Rajshahi', 'Private', 'Saheb Bazar', '+880-721-774060'],
        ['Khulna City Medical College Hospital', 'Khulna', 'Khulna', 'Private', 'Boyra', '+880-41-720084'],
        ['Sylhet Women\'s Medical College Hospital', 'Sylhet', 'Sylhet', 'Private', 'Mirboxtula', '+880-821-720022'],
        ['Rangpur Community Medical College Hospital', 'Rangpur', 'Rangpur', 'Private', 'Dhap', '+880-521-61478'],
        ['Mymensingh Medical Centre', 'Mymensingh', 'Mymensingh', 'Private', 'Shesh More', '+880-91-66965'],
        ['Barishal General Hospital', 'Barishal', 'Barishal', 'Private', 'Sadar Road', '+880-431-63527'],
        ['Tangail Sadar Hospital', 'Tangail', 'Dhaka', 'Public', 'Tangail Sadar', '+880-921-62399'],
        ['Kalihati Upazila Health Complex', 'Tangail', 'Dhaka', 'Public', 'Kalihati', '+880-921-64022'],
        ['Madhupur Upazila Health Complex', 'Tangail', 'Dhaka', 'Public', 'Madhupur', '+880-921-63088'],
        ['Ghatail Upazila Health Complex', 'Tangail', 'Dhaka', 'Public', 'Ghatail', '+880-921-65233'],
        ['Gazaria Upazila Health Complex', 'Munshiganj', 'Dhaka', 'Public', 'Gazaria', '+880-2-7620145'],
        ['Cumilla Sadar Hospital', 'Cumilla', 'Chattogram', 'Public', 'Cumilla Sadar', '+880-81-76001'],
        ['Cox\'s Bazar Sadar Hospital', 'Cox\'s Bazar', 'Chattogram', 'Public', 'Cox\'s Bazar Sadar', '+880-341-64344'],
        ['Feni Sadar Hospital', 'Feni', 'Chattogram', 'Public', 'Feni Sadar', '+880-331-73122'],
        ['Brahmanbaria Sadar Hospital', 'Brahmanbaria', 'Chattogram', 'Public', 'Brahmanbaria Sadar', '+880-851-53205'],
        ['Hathazari Upazila Health Complex', 'Chattogram', 'Chattogram', 'Public', 'Hathazari', '+880-31-628055'],
        ['Patiya Upazila Health Complex', 'Chattogram', 'Chattogram', 'Public', 'Patiya', '+880-31-628033'],
        ['Fatikchari Upazila Health Complex', 'Chattogram', 'Chattogram', 'Public', 'Fatikchari', '+880-31-628044'],
        ['Jashore General Hospital', 'Jashore', 'Khulna', 'Public', 'Jashore Sadar', '+880-421-64281'],
        ['Kushtia General Hospital', 'Kushtia', 'Khulna', 'Public', 'Kushtia Sadar', '+880-71-61155'],
        ['Bagerhat Sadar Hospital', 'Bagerhat', 'Khulna', 'Public', 'Bagerhat Sadar', '+880-468-64004'],
        ['Mongla Upazila Health Complex', 'Bagerhat', 'Khulna', 'Public', 'Mongla', '+880-468-64088'],
        ['Satkhira Sadar Hospital', 'Satkhira', 'Khulna', 'Public', 'Satkhira Sadar', '+880-471-64100'],
        ['Bogura Shaheed Ziaur Rahman Medical College Hospital', 'Bogura', 'Rajshahi', 'Public', 'Jaleswaritola', '+880-51-61021'],
        ['Pabna General Hospital', 'Pabna', 'Rajshahi', 'Public', 'Pabna Sadar', '+880-731-61130'],
        ['Sirajganj General Hospital', 'Sirajganj', 'Rajshahi', 'Public', 'Sirajganj Sadar', '+880-751-61111'],
        ['Natore Sadar Hospital', 'Natore', 'Rajshahi', 'Public', 'Natore Sadar', '+880-771-66300'],
        ['Puthia Upazila Health Complex', 'Rajshahi', 'Rajshahi', 'Public', 'Puthia', '+880-721-640088'],
        ['Shibganj Upazila Health Complex', 'Bogura', 'Rajshahi', 'Public', 'Shibganj', '+880-51-640022'],
        ['Dinajpur District Hospital', 'Dinajpur', 'Rangpur', 'Public', 'Dinajpur Sadar', '+880-531-61044'],
        ['Gaibandha District Hospital', 'Gaibandha', 'Rangpur', 'Public', 'Gaibandha Sadar', '+880-541-61177'],
        ['Kurigram General Hospital', 'Kurigram', 'Rangpur', 'Public', 'Kurigram Sadar', '+880-581-61200'],
        ['Thakurgaon Sadar Hospital', 'Thakurgaon', 'Rangpur', 'Public', 'Thakurgaon Sadar', '+880-561-62011'],
        ['Parbatipur Upazila Health Complex', 'Dinajpur', 'Rangpur', 'Public', 'Parbatipur', '+880-531-640055'],
        ['Pirganj Upazila Health Complex', 'Rangpur', 'Rangpur', 'Public', 'Pirganj', '+880-521-640066'],
        ['Jamalpur General Hospital', 'Jamalpur', 'Mymensingh', 'Public', 'Jamalpur Sadar', '+880-981-61055'],
        ['Netrakona General Hospital', 'Netrakona', 'Mymensingh', 'Public', 'Netrakona Sadar', '+880-951-61022'],
        ['Sherpur District Hospital', 'Sherpur', 'Mymensingh', 'Public', 'Sherpur Sadar', '+880-931-61233'],
        ['Gofargaon Upazila Health Complex', 'Mymensingh', 'Mymensingh', 'Public', 'Gofargaon', '+880-91-640077'],
        ['Trishal Upazila Health Complex', 'Mymensingh', 'Mymensingh', 'Public', 'Trishal', '+880-91-640088'],
        ['Islampur Upazila Health Complex', 'Jamalpur', 'Mymensingh', 'Public', 'Islampur', '+880-981-640099'],
        ['Habiganj District Hospital', 'Habiganj', 'Sylhet', 'Public', 'Habiganj Sadar', '+880-831-52011'],
        ['Maulvibazar District Hospital', 'Maulvibazar', 'Sylhet', 'Public', 'Maulvibazar Sadar', '+880-861-52022'],
        ['Sunamganj District Hospital', 'Sunamganj', 'Sylhet', 'Public', 'Sunamganj Sadar', '+880-871-56033'],
        ['Golapganj Upazila Health Complex', 'Sylhet', 'Sylhet', 'Public', 'Golapganj', '+880-821-640044'],
        ['Beanibazar Upazila Health Complex', 'Sylhet', 'Sylhet', 'Public', 'Beanibazar', '+880-821-640055'],
        ['Kulaura Upazila Health Complex', 'Maulvibazar', 'Sylhet', 'Public', 'Kulaura', '+880-861-640066'],
        ['Patuakhali General Hospital', 'Patuakhali', 'Barishal', 'Public', 'Patuakhali Sadar', '+880-441-61144'],
        ['Bhola District Hospital', 'Bhola', 'Barishal', 'Public', 'Bhola Sadar', '+880-491-61255'],
        ['Barguna Sadar Hospital', 'Barguna', 'Barishal', 'Public', 'Barguna Sadar', '+880-448-64077'],
        ['Pirojpur Sadar Hospital', 'Pirojpur', 'Barishal', 'Public', 'Pirojpur Sadar', '+880-461-64088'],
        ['Mathbaria Upazila Health Complex', 'Pirojpur', 'Barishal', 'Public', 'Mathbaria', '+880-461-64099'],
        ['Kalapara Upazila Health Complex', 'Patuakhali', 'Barishal', 'Public', 'Kalapara', '+880-441-64110'],
        ['BIRDEM General Hospital', 'Dhaka', 'Dhaka', 'Private', 'Shahbag, 122 Kazi Nazrul Islam Ave', '+880-2-8616641'],
        ['Green Life Medical College Hospital', 'Dhaka', 'Dhaka', 'Private', '32-35 Bir Uttam Qazi Nuruzzaman Sarak', '+880-2-9612233'],
        ['Anwer Khan Modern Hospital', 'Dhaka', 'Dhaka', 'Private', 'House 17, Road 8, Dhanmondi', '+880-2-9660995'],
        ['Asgar Ali Hospital', 'Dhaka', 'Dhaka', 'Private', '111/1/A Distillery Road, Gandaria', '+880-2-2334000'],
        ['Islami Bank Central Hospital', 'Dhaka', 'Dhaka', 'Private', '30 Kakrail Road', '+880-2-8331190'],
        ['Holy Family Red Crescent Medical College Hospital', 'Dhaka', 'Dhaka', 'Private', '1 Eskaton Garden Road', '+880-2-8313351'],
        ['Evercare Hospital Chattogram', 'Chattogram', 'Chattogram', 'Private', 'Kattaltoli, Chandgaon', '+880-31-2551180'],
        ['Prime Medical College Hospital', 'Rangpur', 'Rangpur', 'Private', 'Biplob Crossing', '+880-521-61335'],
        ['Gazi Medical College Hospital', 'Khulna', 'Khulna', 'Private', 'KDA Avenue', '+880-41-721801'],
        ['North East Medical College Hospital', 'Sylhet', 'Sylhet', 'Private', 'South Surma', '+880-821-720033'],
        ['Islami Bank Medical College Hospital', 'Rajshahi', 'Rajshahi', 'Private', 'Nachole Para', '+880-721-772450'],
        ['Community Medical College Hospital', 'Mymensingh', 'Mymensingh', 'Private', 'Biddaganj', '+880-91-66533'],
    ];

    $privatePrices = [
        'BCG' => 300, 'DPT' => 1200, 'Polio' => 1500, 'Hepatitis B' => 1000, 'Measles' => 900,
        'MMR' => 1600, 'Typhoid' => 1100, 'Rabies' => 3200, 'COVID-19' => 2000, 'Influenza' => 1800,
        'HPV' => 5500, 'Tetanus' => 800,
    ];

    $checkStmt = db()->prepare('SELECT id FROM vaccination_centers WHERE name = ? LIMIT 1');
    $stmt = db()->prepare(
        'INSERT INTO vaccination_centers (name, district, division, center_type, address, phone)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $priceStmt = db()->prepare(
        'INSERT IGNORE INTO vaccination_center_prices (center_id, vaccine_name, price) VALUES (?, ?, ?)'
    );

    foreach ($centers as $center) {
        $checkStmt->execute([$center[0]]);
        $centerId = (int)$checkStmt->fetchColumn();
        if ($centerId === 0) {
            $stmt->execute($center);
            $centerId = (int)db()->lastInsertId();
        }
        if ($centerId > 0) {
            $isPublic = $center[3] === 'Public';
            foreach (vaccination_names() as $vaccineName) {
                $priceStmt->execute([
                    $centerId,
                    $vaccineName,
                    $isPublic ? 0 : (int)($privatePrices[$vaccineName] ?? 0),
                ]);
            }
        }
    }

    db()->exec(
        'CREATE TABLE IF NOT EXISTS `lab_technician_assignments` (
          `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `technician_id` INT UNSIGNED NOT NULL,
          `center_id` INT UNSIGNED NOT NULL,
          `section_name` VARCHAR(120) NULL,
          `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uq_technician_center_section` (`technician_id`, `center_id`, `section_name`),
          KEY `idx_lab_assignments_technician` (`technician_id`),
          CONSTRAINT `fk_lab_assignments_technician` FOREIGN KEY (`technician_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
          CONSTRAINT `fk_lab_assignments_center` FOREIGN KEY (`center_id`) REFERENCES `vaccination_centers` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
    );

    try {
        $techStmt = db()->prepare("SELECT id FROM users WHERE email = 'lab@nhre.gov' AND role = 'Lab Technician' LIMIT 1");
        $techStmt->execute();
        $techId = (int)$techStmt->fetchColumn();
        if ($techId > 0) {
            /* The demo technician is intentionally assigned to every demo hospital.
               Other technicians remain limited to rows explicitly assigned to them. */
            $assignment = db()->prepare(
                'INSERT INTO lab_technician_assignments (technician_id, center_id, section_name)
                 SELECT ?, vc.id, NULL FROM vaccination_centers vc
                 WHERE NOT EXISTS (
                   SELECT 1 FROM lab_technician_assignments lta
                   WHERE lta.technician_id = ? AND lta.center_id = vc.id AND lta.section_name IS NULL
                 )'
            );
            $assignment->execute([$techId, $techId]);
        }
        $patientId = (int)db()->query("SELECT id FROM users WHERE email = 'patient@nhre.gov' AND role = 'Patient' LIMIT 1")->fetchColumn();
        if ($patientId > 0) {
            $seedBooking = db()->prepare('INSERT INTO vaccination_bookings (user_id, vaccine_name, dose_number, center_id, booking_date, booking_time, contact_phone, notes, status, created_at, updated_at) SELECT ?, ?, 1, id, ?, ?, ?, ?, ?, NOW(), NOW() FROM vaccination_centers WHERE name = ? LIMIT 1');
            $statusCount = db()->prepare('SELECT COUNT(*) FROM vaccination_bookings WHERE user_id = ? AND status = ?');
            foreach ([
                ['Pending', 'Hepatitis B', '+3 days', '09:30:00', 'Demo vaccination booking for technician workflow.'],
                ['Completed', 'Influenza', '-1 day', '10:00:00', 'Demo completed vaccination booking for technician workflow.'],
            ] as [$status, $vaccine, $dateOffset, $time, $notes]) {
                $statusCount->execute([$patientId, $status]);
                if ((int)$statusCount->fetchColumn() === 0) {
                    $seedBooking->execute([$patientId, $vaccine, date('Y-m-d', strtotime($dateOffset)), $time, '+8801000000001', $notes, $status, 'ICDC Hospital EPI Centre']);
                }
            }
        }
    } catch (PDOException $e) {
    }
}

function technician_can_manage_center(int $technicianId, int $centerId, ?string $section = null): bool
{
    if ($technicianId <= 0 || $centerId <= 0) {
        return false;
    }
    $sql = 'SELECT 1 FROM lab_technician_assignments WHERE technician_id = ? AND center_id = ?';
    $params = [$technicianId, $centerId];
    if ($section !== null && $section !== '') {
        $sql .= ' AND (section_name IS NULL OR section_name = ?)';
        $params[] = $section;
    }
    $sql .= ' LIMIT 1';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return (bool)$stmt->fetchColumn();
}

function ensure_access_tables_exists(): void
{
    db()->exec(
        'CREATE TABLE IF NOT EXISTS `access_permissions` (
          `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `patient_id`     INT UNSIGNED NOT NULL,
          `provider_id`    INT UNSIGNED NOT NULL,
          `provider_role`  VARCHAR(50)  NOT NULL,
          `record_types`   TEXT NOT NULL,
          `granted_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `expires_at`     DATETIME NULL,
          `status`         VARCHAR(20) NOT NULL DEFAULT \'Active\',
          `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `idx_access_permissions_patient` (`patient_id`),
          KEY `idx_access_permissions_provider` (`provider_id`),
          CONSTRAINT `fk_access_permissions_patient`
            FOREIGN KEY (`patient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
    );

    db()->exec(
        'CREATE TABLE IF NOT EXISTS `access_logs` (
          `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `permission_id` INT UNSIGNED NULL,
          `patient_id`    INT UNSIGNED NOT NULL,
          `provider_id`   INT UNSIGNED NOT NULL,
          `record_type`   VARCHAR(100) NOT NULL,
          `action`        VARCHAR(50) NOT NULL DEFAULT \'view\',
          `accessed_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `idx_access_logs_patient` (`patient_id`),
          KEY `idx_access_logs_provider` (`provider_id`),
          CONSTRAINT `fk_access_logs_patient`
            FOREIGN KEY (`patient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
    );
}

/** Record types a patient may grant to a provider. */
function access_record_types(): array
{
    return ['Medical History', 'Lab Reports', 'Prescriptions', 'Vaccinations', 'Allergies', 'Medical Documents'];
}

/**
 * Return the active access permission for a patient/provider pair, or null.
 * @return array{id:int,record_types:string,expires_at:string}|null
 */
function active_access(int $patient_id, int $provider_id): ?array
{
    try {
        $stmt = db()->prepare(
            'SELECT id, record_types, expires_at FROM access_permissions
             WHERE patient_id = ? AND provider_id = ? AND status = \'Active\' AND expires_at > NOW()
             ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([$patient_id, $provider_id]);
        $row = $stmt->fetch();
        return $row ? ['id' => (int)$row['id'], 'record_types' => (string)$row['record_types'], 'expires_at' => (string)$row['expires_at']] : null;
    } catch (PDOException $e) {
        return null;
    }
}

/** Record a provider's read access in the audit log and notify the patient. */
function log_record_access(?int $permission_id, int $patient_id, int $provider_id, string $record_type, bool $notify = true): void
{
    try {
        $stmt = db()->prepare(
            'INSERT INTO access_logs (permission_id, patient_id, provider_id, record_type)
             VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$permission_id, $patient_id, $provider_id, $record_type]);
    } catch (PDOException $e) {
    }

    if (!$notify) {
        return;
    }

    try {
        $stmt = db()->prepare('SELECT fullname FROM users WHERE id = ?');
        $stmt->execute([$provider_id]);
        $provider_name = (string)$stmt->fetchColumn();
    } catch (PDOException $e) {
        $provider_name = 'A healthcare provider';
    }

    create_notification(
        $patient_id,
        'Medical record accessed',
        $provider_name . ' viewed your authorized ' . $record_type . ' records.',
        'access'
    );
}

ensure_account_numbers();
remember_me_login();
