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
function assign_missing_account_numbers(): void
{
    try {
        db()->exec("UPDATE users SET account_number = CONCAT('NHRE-', LPAD(id, 8, '0')) WHERE account_number IS NULL OR TRIM(account_number) = ''");
    } catch (PDOException $e) {
        // Account pages still work during initial schema setup; retry on the next request.
    }
}

function ensure_account_numbers(): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    assign_missing_account_numbers();
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

function notify_center_lab_technicians(int $center_id, string $title, string $message, string $type, ?string $section = null): void
{
    if ($center_id <= 0) {
        return;
    }

    try {
        $sql = 'SELECT DISTINCT lta.technician_id
                FROM lab_technician_assignments lta
                JOIN users u ON u.id = lta.technician_id AND u.role = \'Lab Technician\'
                WHERE lta.center_id = ?';
        $params = [$center_id];
        if ($section !== null && $section !== '') {
            $sql .= ' AND (lta.section_name IS NULL OR lta.section_name = ?)';
            $params[] = $section;
        } else {
            $sql .= ' AND lta.section_name IS NULL';
        }
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $technician_id) {
            create_notification((int)$technician_id, $title, $message, $type);
        }
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
    ensure_realistic_patient_clinical_data();
}

function generate_bangladesh_patient_name(int $patientNumber, string $gender): string
{
    $maleNames = ['Arif', 'Imran', 'Rahim', 'Sami', 'Rafi', 'Shuvo', 'Kabir', 'Mahmud', 'Maruf', 'Tahmid', 'Asif', 'Nabil', 'Akram', 'Anik', 'Sabbir', 'Rifat', 'Hasan', 'Ahsan', 'Atik', 'Rakib'];
    $femaleNames = ['Nadia', 'Sadia', 'Mariam', 'Ayesha', 'Nusrat', 'Shila', 'Faria', 'Tania', 'Ruma', 'Amina', 'Lamia', 'Mahi', 'Nabila', 'Tasnim', 'Sanzida', 'Farzana', 'Mehnaz', 'Zarin', 'Shampa', 'Jannat'];
    $maleSurnames = ['Rahman', 'Hossain', 'Ahmed', 'Karim', 'Islam', 'Ali', 'Chowdhury', 'Mahmud', 'Sarker', 'Hasan', 'Mia', 'Talukder', 'Ahamed', 'Siddique', 'Akter', 'Noor'];
    $femaleSurnames = ['Rahman', 'Ahmed', 'Hossain', 'Karim', 'Islam', 'Ali', 'Chowdhury', 'Mahmud', 'Sultana', 'Akter', 'Begum', 'Khatun', 'Ahamed', 'Talukder', 'Mou', 'Noor'];

    $namePool = $gender === 'Female' ? $femaleNames : $maleNames;
    $surnamePool = $gender === 'Female' ? $femaleSurnames : $maleSurnames;

    $first = $namePool[$patientNumber % count($namePool)];
    $last = $surnamePool[(($patientNumber * 7) + 3) % count($surnamePool)];

    return $first . ' ' . $last;
}

function get_bangladesh_patient_profile(int $patientNumber, string $profileKey = 'dhaka_tertiary'): array
{
    $profiles = [
        'dhaka_tertiary' => [
            'district_weights' => [
                'Dhaka' => 62,
                'Chattogram' => 12,
                'Khulna' => 8,
                'Rajshahi' => 7,
                'Sylhet' => 6,
                'Barishal' => 3,
                'Rangpur' => 1,
                'Mymensingh' => 1,
            ],
            'age_band_weights' => [
                '0-17' => 4,
                '18-29' => 18,
                '30-44' => 33,
                '45-59' => 28,
                '60+' => 17,
            ],
            'gender_bias' => ['Female' => 53, 'Male' => 47],
        ],
        'district_referral_mix' => [
            'district_weights' => [
                'Dhaka' => 28,
                'Chattogram' => 16,
                'Khulna' => 12,
                'Rajshahi' => 12,
                'Sylhet' => 10,
                'Barishal' => 8,
                'Rangpur' => 8,
                'Mymensingh' => 6,
            ],
            'age_band_weights' => [
                '0-17' => 10,
                '18-29' => 26,
                '30-44' => 32,
                '45-59' => 20,
                '60+' => 12,
            ],
            'gender_bias' => ['Female' => 51, 'Male' => 49],
        ],
        'elderly_chronic_care' => [
            'district_weights' => [
                'Dhaka' => 34,
                'Chattogram' => 14,
                'Khulna' => 10,
                'Rajshahi' => 10,
                'Sylhet' => 9,
                'Barishal' => 7,
                'Rangpur' => 9,
                'Mymensingh' => 7,
            ],
            'age_band_weights' => [
                '0-17' => 5,
                '18-29' => 10,
                '30-44' => 17,
                '45-59' => 28,
                '60+' => 40,
            ],
            'gender_bias' => ['Female' => 54, 'Male' => 46],
        ],
    ];

    $profile = $profiles[$profileKey] ?? $profiles['dhaka_tertiary'];

    $districts = [];
    foreach ($profile['district_weights'] as $district => $weight) {
        for ($i = 0; $i < $weight; $i++) {
            $districts[] = $district;
        }
    }

    $district = $districts[$patientNumber % count($districts)];

    $ageBands = [];
    foreach ($profile['age_band_weights'] as $band => $weight) {
        for ($i = 0; $i < $weight; $i++) {
            $ageBands[] = $band;
        }
    }

    $band = $ageBands[$patientNumber % count($ageBands)];
    $ageRanges = [
        '0-17' => [4, 17],
        '18-29' => [18, 29],
        '30-44' => [30, 44],
        '45-59' => [45, 59],
        '60+' => [60, 82],
    ];
    [$minAge, $maxAge] = $ageRanges[$band];
    $age = $minAge + (($patientNumber * 11 + 4) % ($maxAge - $minAge + 1));

    $gender = ($patientNumber % 100 < $profile['gender_bias']['Female']) ? 'Female' : 'Male';
    if ($band === '0-17') {
        $gender = (($patientNumber * 7) % 10 < 5) ? 'Female' : 'Male';
    }

    return ['district' => $district, 'age_band' => $band, 'age' => $age, 'gender' => $gender];
}

function generate_bangladesh_patient_birth_date(int $patientNumber, string $profileKey = 'dhaka_tertiary'): string
{
    $profile = get_bangladesh_patient_profile($patientNumber, $profileKey);
    $year = date('Y') - $profile['age'];
    $month = 1 + (($patientNumber * 5) % 12);
    $day = 1 + (($patientNumber * 9) % 28);

    return sprintf('%04d-%02d-%02d', $year, $month, $day);
}

function ensure_named_patient_test_accounts(): void
{
    try {
        $pdo = db();
        $accounts = [
            ['Farhana Rahman', 'patient@nhre.gov', '+8801710001001', 'Patient123!', 'Female', 'House 12, Road 3, Dhanmondi', '1994-03-12', '9000005101', 'Teacher'],
            ['Arif Hossain', 'patient002@nhre.demo', '+8801710001002', 'Patient123!', 'Male', 'House 21, Road 7, Gulshan', '1988-09-21', '9000005102', 'Software Engineer'],
            ['Nadia Islam', 'patient003@nhre.demo', '+8801710001003', 'Patient123!', 'Female', 'House 7, Road 5, Uttara', '2001-11-04', '9000005103', 'Student'],
        ];

        $check = $pdo->prepare('SELECT id FROM users WHERE email = ? AND role = ? LIMIT 1');
        $insert = $pdo->prepare(
            'INSERT INTO users (fullname, nid, email, phone, password_hash, role, gender, address, date_of_birth, blood_group, occupation)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        foreach ($accounts as $index => $account) {
            [$fullname, $email, $phone, $password, $gender, $address, $dob, $nid, $occupation] = $account;
            $check->execute([$email, 'Patient']);
            if ($check->fetch()) {
                continue;
            }

            $bloodGroup = ['A+', 'O+', 'B+', 'AB+', 'A-', 'O-', 'B-', 'AB-'][$index % 8];
            $insert->execute([
                $fullname,
                $nid,
                $email,
                $phone,
                password_hash($password, PASSWORD_DEFAULT),
                'Patient',
                $gender,
                $address,
                $dob,
                $bloodGroup,
                $occupation,
            ]);
        }
    } catch (PDOException $e) {
    }
}

function ensure_realistic_patient_population(string $profileKey = 'dhaka_tertiary'): void
{
    try {
        $pdo = db();
        $targetPatientCount = 280;
        $patientCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'Patient'")->fetchColumn();
        if ($patientCount >= $targetPatientCount) {
            ensure_named_patient_test_accounts();
            return;
        }

        $existing = $pdo->query("SELECT email FROM users WHERE role = 'Patient'")->fetchAll(PDO::FETCH_COLUMN);
        $existingSet = array_fill_keys($existing, true);
        $insert = $pdo->prepare(
            'INSERT INTO users (fullname, nid, email, phone, password_hash, role, gender, address, date_of_birth, blood_group, occupation)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        for ($patientNumber = $patientCount + 1; $patientNumber <= $targetPatientCount; $patientNumber++) {
            $profile = get_bangladesh_patient_profile($patientNumber, $profileKey);
            $gender = $profile['gender'];
            $fullname = generate_bangladesh_patient_name($patientNumber, $gender);
            $email = 'patient' . str_pad((string)$patientNumber, 3, '0', STR_PAD_LEFT) . '@nhre.local';
            $nid = (string)(9000000000 + $patientNumber);
            $phone = '+88017' . str_pad((string)(10000000 + $patientNumber), 8, '0', STR_PAD_LEFT);

            $candidateIndex = 0;
            while (true) {
                $candidateEmail = $candidateIndex === 0 ? $email : 'patient' . str_pad((string)$patientNumber, 3, '0', STR_PAD_LEFT) . '.' . $candidateIndex . '@nhre.local';
                $candidateNid = (string)(9000000000 + $patientNumber + $candidateIndex);
                $candidatePhone = '+88017' . str_pad((string)(10000000 + $patientNumber + $candidateIndex), 8, '0', STR_PAD_LEFT);
                $duplicate = $pdo->prepare('SELECT id FROM users WHERE email = ? OR nid = ? OR phone = ? LIMIT 1');
                $duplicate->execute([$candidateEmail, $candidateNid, $candidatePhone]);

                if (!$duplicate->fetch()) {
                    $email = $candidateEmail;
                    $nid = $candidateNid;
                    $phone = $candidatePhone;
                    break;
                }

                $candidateIndex++;
                if ($candidateIndex > 2000) {
                    break;
                }
            }

            if (isset($existingSet[$email])) {
                continue;
            }

            $dob = generate_bangladesh_patient_birth_date($patientNumber, $profileKey);
            $district = $profile['district'];
            $address = sprintf('House %d, Road %d, %s', 4 + ($patientNumber % 22), 2 + ($patientNumber % 11), $district);

            $insert->execute([
                $fullname,
                $nid,
                $email,
                $phone,
                password_hash('Patient123!', PASSWORD_DEFAULT),
                'Patient',
                $gender,
                $address,
                $dob,
                ['A+','O+','B+','AB+','A-','O-','B-','AB-'][$patientNumber % 8],
                ['Teacher', 'Engineer', 'Housewife', 'Business', 'Freelancer', 'Student', 'Farmer', 'Nurse'][$patientNumber % 8]
            ]);

            $existingSet[$email] = true;
        }

        ensure_named_patient_test_accounts();
    } catch (PDOException $e) {
    }
}

function ensure_realistic_patient_clinical_data(): void
{
    try {
        $pdo = db();
        ensure_realistic_patient_population();
        $patients = $pdo->query("SELECT id, fullname, email FROM users WHERE role = 'Patient' ORDER BY id ASC")->fetchAll();
        if (!$patients) {
            return;
        }

        $doctorIds = $pdo->query("SELECT id FROM users WHERE role = 'Doctor' ORDER BY id ASC")->fetchAll();
        $doctorIdList = array_map(static fn (array $row): int => (int)$row['id'], $doctorIds);

        $allergyCatalog = [
            ['Environmental', 'Dust', 'Sneezing and itchy eyes', 'Mild', 'Common household dust exposure.'],
            ['Environmental', 'Pollen', 'Seasonal congestion and watery eyes', 'Moderate', 'Most common seasonal inhalant allergen.'],
            ['Food', 'Peanuts', 'Lip swelling and hives', 'Severe', 'Often triggered by snacks or bakery foods.'],
            ['Food', 'Shellfish', 'Gut discomfort and rash', 'Moderate', 'Food-triggered allergic reaction.'],
            ['Drug', 'Penicillin', 'Rash and itching', 'Moderate', 'Medication allergy recorded during treatment.'],
            ['Drug', 'Ibuprofen', 'Stomach upset and wheezing', 'Mild', 'Non-steroidal anti-inflammatory sensitivity.'],
            ['Food', 'Milk protein', 'Abdominal cramps and rash', 'Mild', 'Common in children and some adults.'],
            ['Environmental', 'Latex', 'Skin irritation and swelling', 'Moderate', 'Common in healthcare or glove exposure.'],
            ['Insect', 'Bee sting', 'Localized swelling', 'Moderate', 'Requires prompt medical attention if severe.'],
            ['Food', 'Eggs', 'Skin rash and vomiting', 'Mild', 'Occurs in some patients with food sensitivity.'],
        ];

        $checkAllergy = $pdo->prepare('SELECT id FROM allergies WHERE patient_id = ? AND name = ? AND allergy_type = ? LIMIT 1');
        $insertAllergy = $pdo->prepare('INSERT INTO allergies (patient_id, allergy_type, name, reaction_text, severity, notes, recorded_at, is_active) VALUES (?, ?, ?, ?, ?, ?, CURDATE(), 1)');

        foreach ($patients as $index => $patient) {
            $patientId = (int)$patient['id'];
            $dob = (string)($patient['date_of_birth'] ?? '');
            $age = $dob !== '' ? age_from_dob($dob) : 35;
            $gender = (string)($patient['gender'] ?? 'Female');

            $baseAllergyProbability = 0.18;
            if ($age >= 60) {
                $baseAllergyProbability = 0.72;
            } elseif ($age >= 45) {
                $baseAllergyProbability = 0.58;
            } elseif ($age >= 30) {
                $baseAllergyProbability = 0.46;
            } elseif ($age >= 18) {
                $baseAllergyProbability = 0.34;
            } else {
                $baseAllergyProbability = 0.24;
            }

            if ($gender === 'Female') {
                $baseAllergyProbability += 0.04;
            }

            $allergyProbability = min(0.78, max(0.18, $baseAllergyProbability));
            $allergyRoll = (($patientId * 19 + $index * 11 + 7) % 100) + 1;
            $allergyCount = $allergyRoll <= (int)round($allergyProbability * 100) ? 1 + (($patientId * 13 + $index * 3) % 2) : 0;
            $selected = [];
            $used = [];
            for ($i = 0; $i < $allergyCount; $i++) {
                $pick = (($patientId * 13) + ($i * 29) + 5) % count($allergyCatalog);
                $pick = isset($used[$pick]) ? (($pick + 1) % count($allergyCatalog)) : $pick;
                $used[$pick] = true;
                $selected[] = $allergyCatalog[$pick];
            }

            foreach ($selected as $item) {
                [$type, $name, $reaction, $severity, $notes] = $item;
                $checkAllergy->execute([$patientId, $name, $type]);
                if ($checkAllergy->fetch()) {
                    continue;
                }
                $insertAllergy->execute([$patientId, $type, $name, $reaction, $severity, $notes]);
            }
        }

        $documentCatalog = [
            ['Prescription', 'prescription', 'Prescription', 'Primary care medication list'],
            ['Lab report', 'lab-report', 'CBC Report', 'Routine blood work summary'],
            ['Lab report', 'lab-report', 'Lipid Panel', 'Cholesterol and triglyceride profile'],
            ['Imaging report', 'imaging', 'Chest X-ray', 'Radiology review and interpretation'],
            ['Medical certificate', 'certificate', 'Medical Certificate', 'Sick leave and clinic note'],
            ['Vaccination document', 'vaccination', 'Vaccination Record', 'Immunization history summary'],
            ['Other', 'other', 'Follow-up Summary', 'Clinical summary and care plan'],
        ];

        $ensureDir = __DIR__ . '/../uploads/private_documents';
        if (!is_dir($ensureDir) && !mkdir($ensureDir, 0775, true) && !is_dir($ensureDir)) {
            return;
        }

        $checkDocument = $pdo->prepare('SELECT id FROM medical_documents WHERE patient_id = ? AND original_name = ? LIMIT 1');
        $insertDocument = $pdo->prepare('INSERT INTO medical_documents (patient_id, uploaded_by, category, original_name, stored_name, mime_type, file_size, notes, verification_status, verified_by, verified_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');

        foreach ($patients as $index => $patient) {
            $patientId = (int)$patient['id'];
            $dob = (string)($patient['date_of_birth'] ?? '');
            $age = $dob !== '' ? age_from_dob($dob) : 35;

            $docDistributionRoll = (($patientId * 23 + $index * 11 + 5) % 100) + 1;
            $docCount = 1;
            if ($age >= 60) {
                $docCount = $docDistributionRoll <= 10 ? 3 : ($docDistributionRoll <= 55 ? 4 : 5);
            } elseif ($age >= 45) {
                $docCount = $docDistributionRoll <= 15 ? 2 : ($docDistributionRoll <= 60 ? 3 : 4);
            } elseif ($age >= 30) {
                $docCount = $docDistributionRoll <= 18 ? 2 : ($docDistributionRoll <= 62 ? 3 : 4);
            } elseif ($age >= 18) {
                $docCount = $docDistributionRoll <= 22 ? 1 : ($docDistributionRoll <= 66 ? 2 : 3);
            } else {
                $docCount = $docDistributionRoll <= 35 ? 1 : 2;
            }

            $docCount = min(5, max(1, $docCount));

            for ($i = 0; $i < $docCount; $i++) {
                $catalogItem = $documentCatalog[(($patientId * 11) + ($i * 17) + 12) % count($documentCatalog)];
                [$category, $prefix, $name, $note] = $catalogItem;
                $suffix = date('Y-m', strtotime('-' . (($patientId + $i) % 18) . ' months'));
                $originalName = $prefix . '-' . $patientId . '-' . ($i + 1) . '-' . $suffix . '.pdf';
                $checkDocument->execute([$patientId, $originalName]);
                if ($checkDocument->fetch()) {
                    continue;
                }

                $storedName = bin2hex(random_bytes(16)) . '.pdf';
                $pdfContent = build_placeholder_pdf($patient['fullname'], $category, $name, $note, $suffix);
                $filePath = $ensureDir . '/' . $storedName;
                file_put_contents($filePath, $pdfContent);

                $verification = (($patientId * 7 + $i * 13) % 100) < 84 ? 'Verified' : 'Pending Verification';
                $verifiedBy = $verification === 'Verified' && $doctorIdList !== [] ? $doctorIdList[(($patientId + $i) % count($doctorIdList))] : null;

                $insertDocument->execute([
                    $patientId,
                    $patientId,
                    $category,
                    $originalName,
                    $storedName,
                    'application/pdf',
                    (int)strlen($pdfContent),
                    'Uploaded during routine care follow-up.',
                    $verification,
                    $verifiedBy,
                ]);
            }
        }
    } catch (PDOException $e) {
    }
}

function build_placeholder_pdf(string $patientName, string $category, string $documentName, string $note, string $period): string
{
    $text = sprintf("NHRE Medical Record\nPatient: %s\nDocument: %s\nCategory: %s\nPeriod: %s\nNote: %s\n",
        $patientName,
        $documentName,
        $category,
        $period,
        $note
    );
    $lines = explode("\n", $text);
    $content = "BT\n/F1 12 Tf\n20 760 Td\n";
    foreach ($lines as $line) {
        $content .= "/F1 12 Tf\n20 760 Td\n(" . str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $line) . ") Tj\n";
        $content .= "0 -18 Td\n";
    }
    $content .= "ET\n";

    $objects = [];
    $objects[] = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
    $objects[] = "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
    $objects[] = "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>\nendobj\n";
    $objects[] = "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n" . $content . "endstream\nendobj\n";
    $objects[] = "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";

    $pdf = "%PDF-1.4\n";
    $offsets = [0];
    foreach ($objects as $object) {
        $offsets[] = strlen($pdf);
        $pdf .= $object;
    }

    $xrefStart = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";
    for ($i = 1; $i <= count($objects); $i++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
    }
    $pdf .= "trailer\n";
    $pdf .= "<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
    $pdf .= "startxref\n";
    $pdf .= $xrefStart . "\n";
    $pdf .= "%%EOF\n";

    return $pdf;
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
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

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
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

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
                    password_hash('Doctor123!', PASSWORD_DEFAULT),
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

        }

        ensure_demo_accounts();
        ensure_realistic_patient_population();
        ensure_realistic_demo_appointments();
        ensure_seeded_doctor_credentials();
    } catch (PDOException $e) {
    }
}

function demo_patient_seed_emails(int $count = 25): array
{
    $emails = ['patient@nhre.gov'];
    for ($i = 2; $i <= $count; $i++) {
        $emails[] = 'patient' . str_pad((string)$i, 3, '0', STR_PAD_LEFT) . '@nhre.demo';
    }

    return $emails;
}

function seeded_patient_ids_for_demo_appointments(PDO $pdo, int $limit = 12): array
{
    $emails = demo_patient_seed_emails(25);
    $placeholders = implode(', ', array_fill(0, count($emails), '?'));
    $stmt = $pdo->prepare("SELECT id, email FROM users WHERE role = 'Patient' AND email IN ($placeholders)");
    $stmt->execute($emails);
    $byEmail = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $byEmail[strtolower((string)$row['email'])] = (int)$row['id'];
    }

    $ids = [];
    foreach ($emails as $email) {
        $key = strtolower($email);
        if (isset($byEmail[$key])) {
            $ids[] = $byEmail[$key];
        }
        if (count($ids) >= $limit) {
            break;
        }
    }

    if (count($ids) < $limit) {
        $fallback = $pdo->query("SELECT id FROM users WHERE role = 'Patient' ORDER BY id ASC LIMIT " . (int)$limit)->fetchAll(PDO::FETCH_COLUMN);
        foreach ($fallback as $id) {
            $id = (int)$id;
            if ($id > 0 && !in_array($id, $ids, true)) {
                $ids[] = $id;
            }
            if (count($ids) >= $limit) {
                break;
            }
        }
    }

    return $ids;
}

function ensure_realistic_demo_appointments(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    try {
        ensure_appointments_table_exists();
        ensure_demo_accounts();
        assign_missing_account_numbers();

        $pdo = db();
        $patients = seeded_patient_ids_for_demo_appointments($pdo, 12);
        $doctors = $pdo->query("SELECT id FROM users WHERE role = 'Doctor' ORDER BY id ASC LIMIT 12")->fetchAll(PDO::FETCH_COLUMN);
        if (count($patients) < 5 || count($doctors) < 5) {
            return;
        }

        $date = date('Y-m-d');
        $samples = [
            ['09:00:00', 'Pending', 'Migraine follow-up and medication review.', 'Please bring the last prescription.'],
            ['10:30:00', 'Approved', 'Diabetes follow-up with fasting glucose report.', 'Vitals and glucose log required.'],
            ['13:00:00', 'Pending', 'Thyroid profile review and fatigue assessment.', 'Review lab report before consult.'],
            ['15:30:00', 'Approved', 'Post-operative wound review.', 'Dressing change may be needed.'],
            ['17:00:00', 'Pending', 'Antenatal blood pressure and nutrition check.', 'Bring ultrasound report if available.'],
            ['18:30:00', 'Approved', 'Lower back pain assessment after physiotherapy.', 'Assess range of motion.'],
            ['09:30:00', 'Pending', 'Childhood fever and cough assessment.', 'Check temperature chart.'],
            ['11:30:00', 'Approved', 'Cardiology follow-up after ECG.', 'ECG report already uploaded.'],
            ['14:30:00', 'Pending', 'Skin allergy flare-up review.', 'Avoid new topical medicine before visit.'],
            ['16:30:00', 'Approved', 'Routine hypertension review.', 'Update blood pressure log.'],
            ['08:30:00', 'Pending', 'Gastroenterology consultation for abdominal pain.', 'Advise fasting if tests are needed.'],
            ['12:30:00', 'Approved', 'Pediatric vaccination catch-up counseling.', 'Review vaccine card.'],
        ];
        $legacyReasons = [
            'Needs follow-up on recurring headache.',
            'Annual blood pressure review.',
            'Chest discomfort and shortness of breath.',
            'Skin rash follow-up appointment.',
            'Post-surgery recovery review.',
            'Pediatric fever assessment.',
        ];
        $demoReasons = array_merge($legacyReasons, array_column($samples, 2));

        $findSlot = $pdo->prepare(
            'SELECT appointment_id, reason
               FROM appointments
              WHERE doctor_id = ? AND appointment_date = ? AND appointment_time = ?
              LIMIT 1'
        );
        $updateSlot = $pdo->prepare(
            'UPDATE appointments
                SET patient_id = ?, reason = ?, status = ?, doctor_notes = ?, created_at = ?
              WHERE appointment_id = ?'
        );
        $insertSlot = $pdo->prepare(
            'INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, reason, status, doctor_notes, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );

        foreach ($samples as $index => $sample) {
            [$time, $status, $reason, $notes] = $sample;
            $patientId = (int)$patients[$index % count($patients)];
            $doctorId = (int)$doctors[$index % count($doctors)];
            $createdAt = date('Y-m-d H:i:s', strtotime('-' . $index . ' minutes'));

            $findSlot->execute([$doctorId, $date, $time]);
            $existing = $findSlot->fetch(PDO::FETCH_ASSOC);
            if ($existing) {
                if (in_array((string)$existing['reason'], $demoReasons, true)) {
                    $updateSlot->execute([$patientId, $reason, $status, $notes, $createdAt, (int)$existing['appointment_id']]);
                }
                continue;
            }

            $insertSlot->execute([$patientId, $doctorId, $date, $time, $reason, $status, $notes, $createdAt]);
        }
    } catch (PDOException $e) {
    }
}

function nhre_district_names(): array
{
    return ['Dhaka', 'Chattogram', 'Rajshahi', 'Khulna', 'Barishal', 'Sylhet', 'Rangpur', 'Mymensingh'];
}

function ensure_missing_user_districts(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    try {
        ensure_doctor_profile_columns();
        ensure_doctor_catalog_tables();

        $districts = nhre_district_names();
        if ($districts === []) {
            return;
        }

        $cases = [];
        foreach ($districts as $index => $district) {
            $cases[] = "WHEN MOD(id, " . count($districts) . ") = " . $index . " THEN " . db()->quote($district);
        }
        $caseSql = implode(' ', $cases);
        db()->exec(
            "UPDATE users
                SET district = CASE $caseSql ELSE 'Dhaka' END
              WHERE role IN ('Patient', 'Doctor', 'Pharmacist', 'Lab Technician', 'Hospital Admin', 'System Admin')
                AND (district IS NULL OR TRIM(district) = '')"
        );

        try {
            db()->exec(
                "UPDATE users u
                 JOIN districts d ON d.name = u.district
                    SET u.district_id = d.id
                  WHERE (u.district_id IS NULL OR u.district_id = 0)
                    AND u.district IS NOT NULL
                    AND TRIM(u.district) <> ''"
            );
        } catch (PDOException $e) {
        }
    } catch (PDOException $e) {
    }
}

function ensure_realistic_exchange_data(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    try {
        ensure_access_tables_exists();
        ensure_demo_accounts();
        ensure_doctor_catalog_tables();
        ensure_missing_user_districts();

        $pdo = db();
        $patients = seeded_patient_ids_for_demo_appointments($pdo, 12);
        $providers = $pdo->query(
            "SELECT id, role
               FROM users
              WHERE role IN ('Doctor', 'Pharmacist', 'Lab Technician')
              ORDER BY FIELD(role, 'Doctor', 'Lab Technician', 'Pharmacist'), id ASC
              LIMIT 12"
        )->fetchAll(PDO::FETCH_ASSOC);
        if (count($patients) < 5 || count($providers) < 3) {
            return;
        }

        $seeds = [
            ['Doctor', 'Medical History,Prescriptions,Allergies', 'Active', '+30 days', '-42 minutes', 'Medical History', 'view'],
            ['Doctor', 'Lab Reports,Medical Documents', 'Active', '+14 days', '-1 hour', 'Lab Reports', 'download'],
            ['Lab Technician', 'Lab Reports', 'Active', '+7 days', '-2 hours', 'Lab Reports', 'upload'],
            ['Pharmacist', 'Prescriptions', 'Active', '+21 days', '-3 hours', 'Prescriptions', 'verify'],
            ['Doctor', 'Vaccinations,Allergies', 'Requested', '+10 days', '-5 hours', 'Vaccinations', 'view'],
            ['Doctor', 'Medical Documents', 'Active', '+45 days', '-1 day', 'Medical Documents', 'download'],
            ['Lab Technician', 'Lab Reports,Medical Documents', 'Active', '+18 days', '-1 day -2 hours', 'Lab Reports', 'view'],
            ['Pharmacist', 'Prescriptions,Allergies', 'Revoked', '-1 day', '-2 days', 'Prescriptions', 'view'],
            ['Doctor', 'Medical History,Lab Reports', 'Active', '+60 days', '-2 days -3 hours', 'Medical History', 'view'],
            ['Doctor', 'Prescriptions,Vaccinations', 'Requested', '+12 days', '-3 days', 'Prescriptions', 'view'],
        ];

        $providersByRole = [];
        foreach ($providers as $provider) {
            $providersByRole[(string)$provider['role']][] = (int)$provider['id'];
        }

        $findPermission = $pdo->prepare(
            'SELECT id FROM access_permissions
              WHERE patient_id = ? AND provider_id = ? AND provider_role = ? AND record_types = ?
              LIMIT 1'
        );
        $insertPermission = $pdo->prepare(
            'INSERT INTO access_permissions (patient_id, provider_id, provider_role, record_types, granted_at, expires_at, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $findLog = $pdo->prepare(
            'SELECT id FROM access_logs
              WHERE permission_id = ? AND patient_id = ? AND provider_id = ? AND record_type = ? AND action = ?
              LIMIT 1'
        );
        $insertLog = $pdo->prepare(
            'INSERT INTO access_logs (permission_id, patient_id, provider_id, record_type, action, accessed_at)
             VALUES (?, ?, ?, ?, ?, ?)'
        );

        foreach ($seeds as $index => $seed) {
            [$providerRole, $recordTypes, $status, $expiresOffset, $createdOffset, $recordType, $action] = $seed;
            $providerPool = $providersByRole[$providerRole] ?? [];
            if ($providerPool === []) {
                $providerPool = array_map(static fn (array $provider): int => (int)$provider['id'], $providers);
            }

            $patientId = (int)$patients[$index % count($patients)];
            $providerId = (int)$providerPool[$index % count($providerPool)];
            $createdAt = date('Y-m-d H:i:s', strtotime((string)$createdOffset));
            $expiresAt = date('Y-m-d H:i:s', strtotime((string)$expiresOffset));

            $findPermission->execute([$patientId, $providerId, $providerRole, $recordTypes]);
            $permissionId = (int)$findPermission->fetchColumn();
            if ($permissionId <= 0) {
                $insertPermission->execute([$patientId, $providerId, $providerRole, $recordTypes, $createdAt, $expiresAt, $status, $createdAt]);
                $permissionId = (int)$pdo->lastInsertId();
            }

            if ($permissionId > 0) {
                $findLog->execute([$permissionId, $patientId, $providerId, $recordType, $action]);
                if (!$findLog->fetchColumn()) {
                    $insertLog->execute([$permissionId, $patientId, $providerId, $recordType, $action, date('Y-m-d H:i:s', strtotime($createdAt . ' +8 minutes'))]);
                }
            }
        }
    } catch (PDOException $e) {
    }
}

function ensure_realistic_report_volumes(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    try {
        ensure_appointments_table_exists();
        ensure_doctor_catalog_tables();
        ensure_medical_test_tables_exists();
        ensure_vaccination_center_tables();
        ensure_access_tables_exists();
        ensure_missing_user_districts();
        assign_missing_account_numbers();

        $pdo = db();
        $documentCount = (int)$pdo->query('SELECT COUNT(*) FROM medical_documents')->fetchColumn();
        $anchor = max(360, $documentCount);
        $targets = [
            'appointments' => max(240, (int)round($anchor * 0.55)),
            'prescriptions' => max(180, (int)round($anchor * 0.34)),
            'lab_bookings' => max(150, (int)round($anchor * 0.28)),
            'vaccinations' => max(120, (int)round($anchor * 0.24)),
            'consents' => max(120, (int)round($anchor * 0.22)),
            'record_access' => max(190, (int)round($anchor * 0.42)),
        ];

        $patients = $pdo->query("SELECT id FROM users WHERE role = 'Patient' ORDER BY id ASC LIMIT 140")->fetchAll(PDO::FETCH_COLUMN);
        $doctors = $pdo->query("SELECT id FROM users WHERE role = 'Doctor' ORDER BY id ASC LIMIT 90")->fetchAll(PDO::FETCH_COLUMN);
        $pharmacists = $pdo->query("SELECT id FROM users WHERE role = 'Pharmacist' ORDER BY id ASC LIMIT 10")->fetchAll(PDO::FETCH_COLUMN);
        $labTechs = $pdo->query("SELECT id FROM users WHERE role = 'Lab Technician' ORDER BY id ASC LIMIT 10")->fetchAll(PDO::FETCH_COLUMN);
        if (!$patients || !$doctors) {
            return;
        }

        $appointmentCurrent = (int)$pdo->query('SELECT COUNT(*) FROM appointments')->fetchColumn();
        $appointmentInsert = $pdo->prepare(
            'INSERT IGNORE INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, reason, status, doctor_notes, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $appointmentReasons = [
            'Diabetes medication follow-up',
            'Hypertension monitoring review',
            'Lab report discussion',
            'Post-discharge follow-up',
            'Child fever assessment',
            'Antenatal care visit',
            'Respiratory symptom review',
            'Skin allergy consultation',
            'Cardiology follow-up',
            'Routine wellness check',
        ];
        $appointmentStatuses = ['Pending', 'Approved', 'Completed', 'Approved', 'Pending', 'Completed'];
        $slots = ['08:30:00', '09:00:00', '09:30:00', '10:00:00', '10:30:00', '11:30:00', '13:00:00', '14:30:00', '15:30:00', '16:30:00', '17:00:00', '18:30:00'];
        for ($attempt = 0; $appointmentCurrent < $targets['appointments'] && $attempt < $targets['appointments'] * 8; $attempt++) {
            $date = date('Y-m-d', strtotime('-' . (int)floor($attempt / max(1, count($doctors) * count($slots))) . ' days'));
            $doctorId = (int)$doctors[$attempt % count($doctors)];
            $patientId = (int)$patients[($attempt * 7) % count($patients)];
            $time = $slots[(int)floor($attempt / max(1, count($doctors))) % count($slots)];
            $reason = $appointmentReasons[$attempt % count($appointmentReasons)];
            $status = $appointmentStatuses[$attempt % count($appointmentStatuses)];
            $createdAt = date('Y-m-d H:i:s', strtotime($date . ' ' . $time . ' -' . (($attempt % 6) * 9) . ' minutes'));
            $appointmentInsert->execute([$patientId, $doctorId, $date, $time, $reason, $status, $status === 'Completed' ? 'Follow-up plan documented.' : '', $createdAt]);
            $appointmentCurrent += $appointmentInsert->rowCount() > 0 ? 1 : 0;
        }

        $medicines = $pdo->query('SELECT id FROM medicines WHERE is_active = 1 ORDER BY id ASC LIMIT 24')->fetchAll(PDO::FETCH_COLUMN);
        if ($medicines) {
            $prescriptionCurrent = (int)$pdo->query('SELECT COUNT(*) FROM prescriptions')->fetchColumn();
            $rxInsert = $pdo->prepare(
                'INSERT IGNORE INTO prescriptions (prescription_no, patient_id, doctor_id, status, notes, verified_by, verified_at, expires_at, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $itemInsert = $pdo->prepare(
                'INSERT INTO prescription_items (prescription_id, medicine_id, quantity_prescribed, dosage, frequency, duration_days, instructions)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $rxStatuses = ['PENDING', 'VERIFIED', 'READY', 'DISPENSED', 'PARTIALLY_DISPENSED'];
            for ($i = 1; $prescriptionCurrent < $targets['prescriptions'] && $i <= $targets['prescriptions'] * 3; $i++) {
                $rxNo = 'RX-OPS-' . str_pad((string)$i, 5, '0', STR_PAD_LEFT);
                $status = $rxStatuses[$i % count($rxStatuses)];
                $doctorId = (int)$doctors[$i % count($doctors)];
                $patientId = (int)$patients[($i * 5) % count($patients)];
                $createdAt = date('Y-m-d H:i:s', strtotime('-' . ($i % 90) . ' days +' . ($i % 9) . ' hours'));
                $verifiedBy = $status === 'PENDING' ? null : $doctorId;
                $verifiedAt = $status === 'PENDING' ? null : $createdAt;
                $rxInsert->execute([
                    $rxNo,
                    $patientId,
                    $doctorId,
                    $status,
                    'Operational demo prescription seeded for platform reporting balance.',
                    $verifiedBy,
                    $verifiedAt,
                    date('Y-m-d H:i:s', strtotime($createdAt . ' +90 days')),
                    $createdAt,
                ]);
                if ($rxInsert->rowCount() > 0) {
                    $prescriptionCurrent++;
                    $rxId = (int)$pdo->lastInsertId();
                    $itemInsert->execute([
                        $rxId,
                        (int)$medicines[$i % count($medicines)],
                        10 + ($i % 50),
                        ($i % 2 === 0) ? '500mg' : '10mg',
                        ($i % 3 === 0) ? 'twice daily' : 'once daily',
                        7 + ($i % 30),
                        'Generated as realistic reporting workload.',
                    ]);
                }
            }
        }

        $tests = $pdo->query('SELECT id FROM medical_tests ORDER BY id ASC')->fetchAll(PDO::FETCH_COLUMN);
        if ($tests) {
            $labCurrent = (int)$pdo->query('SELECT COUNT(*) FROM medical_test_bookings')->fetchColumn();
            $labInsert = $pdo->prepare(
                'INSERT INTO medical_test_bookings (test_id, user_id, booking_date, booking_time, status, result_notes, result_date, technician_id, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $labStatuses = ['Pending', 'Confirmed', 'Completed', 'Completed', 'Cancelled'];
            for ($i = 0; $labCurrent < $targets['lab_bookings']; $i++) {
                $status = $labStatuses[$i % count($labStatuses)];
                $date = date('Y-m-d', strtotime('-' . ($i % 75) . ' days'));
                $createdAt = $date . ' ' . sprintf('%02d:%02d:00', 8 + ($i % 10), ($i * 7) % 60);
                $labInsert->execute([
                    (int)$tests[$i % count($tests)],
                    (int)$patients[($i * 3) % count($patients)],
                    $date,
                    sprintf('%02d:%02d:00', 9 + ($i % 8), ($i * 5) % 60),
                    $status,
                    $status === 'Completed' ? 'Result reviewed and shared through NHRE.' : 'Operational booking record.',
                    $status === 'Completed' ? $date : null,
                    $labTechs ? (int)$labTechs[$i % count($labTechs)] : null,
                    $createdAt,
                    $createdAt,
                ]);
                $labCurrent += $labInsert->rowCount() > 0 ? 1 : 0;
            }
        }

        $centers = $pdo->query('SELECT id FROM vaccination_centers ORDER BY id ASC LIMIT 80')->fetchAll(PDO::FETCH_COLUMN);
        if ($centers) {
            $vaccinationCurrent = (int)$pdo->query('SELECT COUNT(*) FROM vaccination_bookings')->fetchColumn();
            $vaccinationInsert = $pdo->prepare(
                'INSERT INTO vaccination_bookings (user_id, vaccine_name, dose_number, center_id, booking_date, booking_time, contact_phone, notes, status, technician_id, status_notes, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $vaccines = vaccination_names();
            $vaccineStatuses = ['Pending', 'Confirmed', 'Completed', 'Completed', 'Cancelled'];
            for ($i = 0; $vaccinationCurrent < $targets['vaccinations']; $i++) {
                $status = $vaccineStatuses[$i % count($vaccineStatuses)];
                $date = date('Y-m-d', strtotime('-' . ($i % 110) . ' days'));
                $createdAt = $date . ' ' . sprintf('%02d:%02d:00', 8 + ($i % 9), ($i * 11) % 60);
                $vaccinationInsert->execute([
                    (int)$patients[($i * 4) % count($patients)],
                    $vaccines[$i % count($vaccines)],
                    1 + ($i % 3),
                    (int)$centers[$i % count($centers)],
                    $date,
                    sprintf('%02d:%02d:00', 9 + ($i % 7), ($i * 3) % 60),
                    '+88017' . str_pad((string)(50000000 + $i), 8, '0', STR_PAD_LEFT),
                    'Routine vaccination workflow record.',
                    $status,
                    $labTechs ? (int)$labTechs[$i % count($labTechs)] : null,
                    $status === 'Completed' ? 'Dose administered and recorded.' : null,
                    $createdAt,
                    $createdAt,
                ]);
                $vaccinationCurrent += $vaccinationInsert->rowCount() > 0 ? 1 : 0;
            }
        }

        $providerRows = $pdo->query("SELECT id, role FROM users WHERE role IN ('Doctor', 'Pharmacist', 'Lab Technician') ORDER BY id ASC LIMIT 120")->fetchAll(PDO::FETCH_ASSOC);
        if ($providerRows) {
            $consentCurrent = (int)$pdo->query('SELECT COUNT(*) FROM access_permissions')->fetchColumn();
            $permissionInsert = $pdo->prepare(
                'INSERT INTO access_permissions (patient_id, provider_id, provider_role, record_types, granted_at, expires_at, status, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $recordTypeSets = [
                'Medical History,Prescriptions',
                'Lab Reports,Medical Documents',
                'Vaccinations,Allergies',
                'Medical History,Lab Reports,Prescriptions',
                'Prescriptions',
                'Medical Documents',
            ];
            $permissionStatuses = ['Active', 'Active', 'Requested', 'Active', 'Revoked'];
            for ($i = 0; $consentCurrent < $targets['consents']; $i++) {
                $provider = $providerRows[$i % count($providerRows)];
                $createdAt = date('Y-m-d H:i:s', strtotime('-' . ($i % 120) . ' days +' . ($i % 11) . ' hours'));
                $permissionInsert->execute([
                    (int)$patients[($i * 6) % count($patients)],
                    (int)$provider['id'],
                    (string)$provider['role'],
                    $recordTypeSets[$i % count($recordTypeSets)],
                    $createdAt,
                    date('Y-m-d H:i:s', strtotime($createdAt . ' +' . (15 + ($i % 60)) . ' days')),
                    $permissionStatuses[$i % count($permissionStatuses)],
                    $createdAt,
                ]);
                $consentCurrent += $permissionInsert->rowCount() > 0 ? 1 : 0;
            }

            $permissions = $pdo->query('SELECT id, patient_id, provider_id, record_types FROM access_permissions ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC);
            $accessCurrent = (int)$pdo->query('SELECT COUNT(*) FROM access_logs')->fetchColumn();
            $logInsert = $pdo->prepare(
                'INSERT INTO access_logs (permission_id, patient_id, provider_id, record_type, action, accessed_at)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $actions = ['view', 'view', 'download', 'verify', 'upload'];
            for ($i = 0; $permissions && $accessCurrent < $targets['record_access']; $i++) {
                $permission = $permissions[$i % count($permissions)];
                $types = access_record_type_list($permission);
                $recordType = $types ? $types[$i % count($types)] : 'Medical History';
                $logInsert->execute([
                    (int)$permission['id'],
                    (int)$permission['patient_id'],
                    (int)$permission['provider_id'],
                    $recordType,
                    $actions[$i % count($actions)],
                    date('Y-m-d H:i:s', strtotime('-' . ($i % 90) . ' days +' . ($i % 13) . ' hours')),
                ]);
                $accessCurrent += $logInsert->rowCount() > 0 ? 1 : 0;
            }
        }
    } catch (PDOException $e) {
    }
}

function nhre_format_metric_value(int|float|string $value): string
{
    if (!is_numeric($value)) {
        return (string)$value;
    }

    $number = (float)$value;
    if ($number >= 1000000) {
        $millions = $number / 1000000;
        return rtrim(rtrim(number_format($millions, $millions >= 10 ? 0 : 1), '0'), '.') . 'M+';
    }
    if ($number >= 1000) {
        $thousands = $number / 1000;
        return rtrim(rtrim(number_format($thousands, $thousands >= 10 ? 0 : 1), '0'), '.') . 'K+';
    }

    return number_format($number);
}

function nhre_national_platform_metrics(): array
{
    return [
        'patients' => ['label' => 'Patients', 'value' => '10M+', 'icon' => 'fa-user-injured'],
        'hospitals' => ['label' => 'Hospitals', 'value' => '5000+', 'icon' => 'fa-hospital'],
        'doctors' => ['label' => 'Doctors', 'value' => '25000+', 'icon' => 'fa-user-doctor'],
        'staff' => ['label' => 'Operational staff', 'value' => '38K+', 'icon' => 'fa-user-nurse'],
        'medical_records' => ['label' => 'Medical records', 'value' => '100M+', 'icon' => 'fa-file-medical'],
        'availability' => ['label' => 'Availability', 'value' => '99.99%', 'icon' => 'fa-shield-heart'],
        'active_consents' => ['label' => 'Active consents', 'value' => '3.7M+', 'icon' => 'fa-shield-heart'],
        'record_exchanges' => ['label' => 'Record exchanges', 'value' => '42M+', 'icon' => 'fa-right-left'],
        'connected_providers' => ['label' => 'Connected providers', 'value' => '38K+', 'icon' => 'fa-user-doctor'],
    ];
}

function nhre_national_report_cards(): array
{
    $metrics = nhre_national_platform_metrics();
    return [
        $metrics['patients'],
        $metrics['hospitals'],
        $metrics['doctors'],
        $metrics['medical_records'],
        $metrics['availability'],
    ];
}

function nhre_national_report_items(): array
{
    return [
        ['label' => 'Appointments', 'count' => 12400000],
        ['label' => 'Prescriptions', 'count' => 8600000],
        ['label' => 'Lab bookings', 'count' => 6200000],
        ['label' => 'Documents', 'count' => 100000000],
        ['label' => 'Consent grants', 'count' => 3700000],
        ['label' => 'Record access', 'count' => 42000000],
        ['label' => 'Vaccinations', 'count' => 5100000],
    ];
}

/**
 * Demo patient seeding is intentionally disabled to preserve real patient
 * accounts and prevent synthetic records from being regenerated.
 */
function ensure_demo_patients_and_records(): void
{
    return;
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

        $pdo->prepare('UPDATE users SET password_hash = ? WHERE role = ? AND email LIKE ?')
            ->execute([password_hash('Doctor123!', PASSWORD_DEFAULT), 'Doctor', 'doctor%@nhre.dev']);

        $pdo->prepare('INSERT INTO application_settings (setting_key, setting_value) VALUES (?, ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)')->execute([
                'seeded_doctor_credentials_v1',
                date('c'),
            ]);
    } catch (PDOException $e) {
    }
}


/**
 * Idempotently seed one demo account for every role that cannot self-register
 * (Pharmacist and Lab Technician already can self-register; these just provide
 * ready-made demo credentials). Passwords match the defaults shown on the login
 * page and in the README demo accounts table.
 */
function default_patient_id_for_demo_seed(): ?int
{
    try {
        $pdo = db();
        $preferred = [
            'patient@nhre.gov',
            'patient002@nhre.demo',
            'patient003@nhre.demo',
        ];

        foreach ($preferred as $email) {
            $id = (int)$pdo->query("SELECT id FROM users WHERE role = 'Patient' AND email = '$email' LIMIT 1")->fetchColumn();
            if ($id > 0) {
                return $id;
            }
        }

        $fallback = (int)$pdo->query("SELECT id FROM users WHERE role = 'Patient' ORDER BY id ASC LIMIT 1")->fetchColumn();
        return $fallback > 0 ? $fallback : null;
    } catch (PDOException $e) {
        return null;
    }
}

function ensure_unique_user_identity_data(): void
{
    try {
        $pdo = db();
        $rows = $pdo->query('SELECT id, email, phone, nid FROM users ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC);
        $seenEmails = [];
        $seenPhones = [];
        $seenNids = [];

        foreach ($rows as $row) {
            $id = (int)($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $email = strtolower(trim((string)($row['email'] ?? '')));
            if ($email !== '' && isset($seenEmails[$email])) {
                $email = 'user' . $id . '@nhre.local';
                $pdo->prepare('UPDATE users SET email = ? WHERE id = ?')->execute([$email, $id]);
            }
            $seenEmails[$email] = true;

            $phone = trim((string)($row['phone'] ?? ''));
            $candidatePhone = $phone;
            $counter = 1;
            while ($candidatePhone !== '' && isset($seenPhones[$candidatePhone])) {
                $candidatePhone = '+88017' . str_pad((string)(30000000 + $id + $counter), 8, '0', STR_PAD_LEFT);
                $counter++;
            }
            if ($candidatePhone !== $phone && $candidatePhone !== '') {
                $pdo->prepare('UPDATE users SET phone = ? WHERE id = ?')->execute([$candidatePhone, $id]);
            }
            $seenPhones[$candidatePhone !== '' ? $candidatePhone : $phone] = true;

            $nid = trim((string)($row['nid'] ?? ''));
            if ($nid !== '' && isset($seenNids[$nid])) {
                $replacement = (string)(9000000000 + $id + 1000000);
                $pdo->prepare('UPDATE users SET nid = ? WHERE id = ?')->execute([$replacement, $id]);
                $nid = $replacement;
            }
            if ($nid !== '') {
                $seenNids[$nid] = true;
            }
        }
    } catch (PDOException $e) {
    }
}

function ensure_demo_accounts(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    try {
        ensure_unique_user_identity_data();
    } catch (PDOException $e) {
    }
    $accounts = [
        ['Demo Patient',          '0000000001', 'patient@nhre.gov', '+8801000000001', 'Patient123!', 'Patient'],
        ['Hospital Administrator', '0000000002', 'admin@nhre.gov', '+8801000000002', 'Admin123!', 'Hospital Admin'],
        ['System Administrator',   '0000000003', 'sysadmin@nhre.gov', '+8801000000003', 'SysAdmin123!', 'System Admin'],
        ['Demo Pharmacist',        '0000000004', 'pharmacist@nhre.gov', '+8801000000004', 'Pharmacist123!', 'Pharmacist'],
        ['Demo Lab Technician',    '0000000005', 'lab@nhre.gov', '+8801000000005', 'Lab123!', 'Lab Technician'],
        ['Nusrat Jahan',           '0000000006', 'nusrat.jahan.lab@nhre.gov', '+8801712345606', 'LabTech123!', 'Lab Technician'],
        ['Farhana Rahman', '1000000001', 'patient@nhre.gov', '+8801710001001', 'Patient123!', 'Patient'],
        ['Arif Hossain', '1000000002', 'patient002@nhre.demo', '+8801710001002', 'Patient123!', 'Patient'],
        ['Nadia Islam', '1000000003', 'patient003@nhre.demo', '+8801710001003', 'Patient123!', 'Patient'],
        ['Sami Ahmed', '1000000004', 'patient004@nhre.demo', '+8801710001004', 'Patient123!', 'Patient'],
        ['Mariam Sultana', '1000000005', 'patient005@nhre.demo', '+8801710001005', 'Patient123!', 'Patient'],
        ['Kabir Rahman', '1000000006', 'patient006@nhre.demo', '+8801710001006', 'Patient123!', 'Patient'],
        ['Ayesha Karim', '1000000007', 'patient007@nhre.demo', '+8801710001007', 'Patient123!', 'Patient'],
        ['Rafi Chowdhury', '1000000008', 'patient008@nhre.demo', '+8801710001008', 'Patient123!', 'Patient'],
        ['Tasnim Akter', '1000000009', 'patient009@nhre.demo', '+8801710001009', 'Patient123!', 'Patient'],
        ['Imran Hasan', '1000000010', 'patient010@nhre.demo', '+8801710001010', 'Patient123!', 'Patient'],
        ['Nusrat Jahan', '1000000011', 'patient011@nhre.demo', '+8801710001011', 'Patient123!', 'Patient'],
        ['Mahmud Hossain', '1000000012', 'patient012@nhre.demo', '+8801710001012', 'Patient123!', 'Patient'],
        ['Faria Ahmed', '1000000013', 'patient013@nhre.demo', '+8801710001013', 'Patient123!', 'Patient'],
        ['Tahmid Islam', '1000000014', 'patient014@nhre.demo', '+8801710001014', 'Patient123!', 'Patient'],
        ['Sadia Rahman', '1000000015', 'patient015@nhre.demo', '+8801710001015', 'Patient123!', 'Patient'],
        ['Nabil Karim', '1000000016', 'patient016@nhre.demo', '+8801710001016', 'Patient123!', 'Patient'],
        ['Lamia Hossain', '1000000017', 'patient017@nhre.demo', '+8801710001017', 'Patient123!', 'Patient'],
        ['Asif Mahmood', '1000000018', 'patient018@nhre.demo', '+8801710001018', 'Patient123!', 'Patient'],
        ['Mehnaz Chowdhury', '1000000019', 'patient019@nhre.demo', '+8801710001019', 'Patient123!', 'Patient'],
        ['Rakib Ahmed', '1000000020', 'patient020@nhre.demo', '+8801710001020', 'Patient123!', 'Patient'],
        ['Jannat Sultana', '1000000021', 'patient021@nhre.demo', '+8801710001021', 'Patient123!', 'Patient'],
        ['Ahsan Talukder', '1000000022', 'patient022@nhre.demo', '+8801710001022', 'Patient123!', 'Patient'],
        ['Farzana Begum', '1000000023', 'patient023@nhre.demo', '+8801710001023', 'Patient123!', 'Patient'],
        ['Maruf Islam', '1000000024', 'patient024@nhre.demo', '+8801710001024', 'Patient123!', 'Patient'],
        ['Zarin Noor', '1000000025', 'patient025@nhre.demo', '+8801710001025', 'Patient123!', 'Patient'],
        ['Dr. Mohammad Ashraf Karim', '0000000002', 'admin@nhre.gov', '+8801710002002', 'Admin123!', 'Hospital Admin'],
        ['Nusrat Jahan Chowdhury',   '0000000003', 'sysadmin@nhre.gov', '+8801710002003', 'SysAdmin123!', 'System Admin'],
        ['Ahsanul Haque',            '0000000004', 'pharmacist@nhre.gov', '+8801710002004', 'Pharmacist123!', 'Pharmacist'],
        ['Maksudul Islam',           '0000000005', 'lab@nhre.gov', '+8801710002005', 'Lab123!', 'Lab Technician'],
    ];

    try {
        $pdo = db();
        $find = $pdo->prepare('SELECT id, fullname, email, phone, nid FROM users WHERE email = ? OR phone = ? OR nid = ? LIMIT 1');
        $update = $pdo->prepare('UPDATE users SET fullname = ?, role = ?, nid = ?, phone = ?, password_hash = ? WHERE email = ? LIMIT 1');
        $insert = $pdo->prepare(
            'INSERT INTO users (fullname, nid, email, phone, password_hash, role)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        foreach ($accounts as $account) {
            [$fullname, $nid, $email, $phone, $password, $role] = $account;
            $find->execute([$email, $phone, $nid]);
            $existing = $find->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                $sameEmail = strtolower((string)($existing['email'] ?? '')) === strtolower($email);
                if ($sameEmail) {
                    $update->execute([$fullname, $role, $nid, $phone, password_hash($password, PASSWORD_DEFAULT), $email]);
                }
                continue;
            }

            $insert->execute([
                $fullname,
                $nid,
                $email,
                $phone,
                password_hash($password, PASSWORD_DEFAULT),
                $role,
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

function patient_has_completed_appointment_with_doctor(int $patient_id, int $doctor_id): bool
{
    try {
        $stmt = db()->prepare(
            'SELECT 1 FROM appointments
             WHERE patient_id = ? AND doctor_id = ? AND status = ?
             LIMIT 1'
        );
        $stmt->execute([$patient_id, $doctor_id, 'Completed']);
        return (bool)$stmt->fetchColumn();
    } catch (PDOException $e) {
        return false;
    }
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
        $sharedCenter = db()->prepare("SELECT id FROM vaccination_centers WHERE name = 'Green Crescent Diagnostic & Vaccination Centre' LIMIT 1");
        $sharedCenter->execute();
        $sharedCenterId = (int)$sharedCenter->fetchColumn();
        if ($sharedCenterId > 0) {
            $mapServices = db()->prepare('UPDATE medical_tests SET center_id = ?, place = ? WHERE name IN (?, ?, ?)');
            $mapServices->execute([$sharedCenterId, 'Mirpur, Dhaka', 'Blood Sugar Test', 'CBC Test', 'Lipid Profile']);
        }
        $patientId = (int)db()->query("SELECT id FROM users WHERE email = 'patient@nhre.gov' AND role = 'Patient' LIMIT 1")->fetchColumn();
        if ($patientId > 0) {
        $patientId = default_patient_id_for_demo_seed();
        if ($patientId !== null) {
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
        ['Green Crescent Diagnostic & Vaccination Centre', 'Dhaka', 'Dhaka', 'Private', 'Mirpur 10, Dhaka', '+880-2-55012345'],
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

    $newTechnicianStmt = db()->prepare("SELECT id FROM users WHERE email = 'nusrat.jahan.lab@nhre.gov' AND role = 'Lab Technician' LIMIT 1");
    $newTechnicianStmt->execute();
    $newTechnicianId = (int)$newTechnicianStmt->fetchColumn();
    $sharedCenterStmt = db()->prepare("SELECT id FROM vaccination_centers WHERE name = 'Green Crescent Diagnostic & Vaccination Centre' LIMIT 1");
    $sharedCenterStmt->execute();
    $sharedCenterId = (int)$sharedCenterStmt->fetchColumn();
    if ($newTechnicianId > 0 && $sharedCenterId > 0) {
        $assignment = db()->prepare('INSERT IGNORE INTO lab_technician_assignments (technician_id, center_id, section_name) VALUES (?, ?, NULL)');
        $assignment->execute([$newTechnicianId, $sharedCenterId]);
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
                 ) AND vc.name <> \'Green Crescent Diagnostic & Vaccination Centre\''
            );
            $assignment->execute([$techId, $techId]);
              $removeSharedCenterAssignment = db()->prepare(
                 'DELETE lta FROM lab_technician_assignments lta
                  JOIN vaccination_centers vc ON vc.id = lta.center_id
                  WHERE lta.technician_id = ? AND vc.name = ? AND lta.section_name IS NULL'
              );
              $removeSharedCenterAssignment->execute([$techId, 'Green Crescent Diagnostic & Vaccination Centre']);
        }
        $patientId = default_patient_id_for_demo_seed();
        if ($patientId !== null) {
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

function access_record_type_list(?array $access): array
{
    if ($access === null) {
        return [];
    }

    return array_values(array_filter(
        array_map('trim', explode(',', (string)($access['record_types'] ?? ''))),
        static fn (string $type): bool => $type !== ''
    ));
}

function access_allows_record_type(?array $access, string $record_type): bool
{
    return in_array($record_type, access_record_type_list($access), true);
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

/**
 * Return an active permission only when it includes the requested record type.
 * @return array{id:int,record_types:string,expires_at:string}|null
 */
function active_access_for_record_type(int $patient_id, int $provider_id, string $record_type): ?array
{
    $access = active_access($patient_id, $provider_id);
    return access_allows_record_type($access, $record_type) ? $access : null;
}

function user_hospital_id(int $user_id): ?int
{
    if ($user_id <= 0) {
        return null;
    }

    try {
        $stmt = db()->prepare('SELECT hospital_id FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$user_id]);
        $value = $stmt->fetchColumn();
        return $value !== false && $value !== null ? (int)$value : null;
    } catch (PDOException $e) {
        return null;
    }
}

function hospital_admin_can_manage_user(int $admin_id, int $managed_user_id): bool
{
    $admin_hospital_id = user_hospital_id($admin_id);
    $managed_hospital_id = user_hospital_id($managed_user_id);
    return $admin_hospital_id !== null
        && $admin_hospital_id > 0
        && $managed_hospital_id === $admin_hospital_id;
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
