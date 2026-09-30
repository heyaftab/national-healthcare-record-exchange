-- =====================================================
-- NHRE - National Healthcare Record Exchange
-- Database schema for MySQL (run via phpMyAdmin or CLI):
--   mysql -u root -p < database/nhre.sql
-- =====================================================

CREATE DATABASE IF NOT EXISTS `nhre`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `nhre`;

-- -----------------------------------------------------
-- Registered accounts
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id`                INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `fullname`          VARCHAR(150)     NOT NULL,
  `nid`               VARCHAR(20)      NOT NULL,
  `email`             VARCHAR(190)     NOT NULL,
  `phone`             VARCHAR(20)      NOT NULL,
  `password_hash`     VARCHAR(255)     NOT NULL,
  `role`              VARCHAR(50)      NOT NULL DEFAULT 'Patient',
  `account_number`    VARCHAR(50)      NULL DEFAULT NULL,
  `date_of_birth`     DATE             NULL DEFAULT NULL,
  `nationality`       VARCHAR(100)     NULL DEFAULT NULL,
  `gender`            VARCHAR(20)      NULL DEFAULT NULL,
  `address`           TEXT             NULL DEFAULT NULL,
  `emergency_contact` VARCHAR(100)     NULL DEFAULT NULL,
  `blood_group`       VARCHAR(10)      NULL DEFAULT NULL,
  `marital_status`    VARCHAR(30)      NULL DEFAULT NULL,
  `occupation`        VARCHAR(100)     NULL DEFAULT NULL,
  `profile_photo`     VARCHAR(255)     NULL DEFAULT NULL,
  `district_id`       INT UNSIGNED     NULL DEFAULT NULL,
  `hospital_id`       INT UNSIGNED     NULL DEFAULT NULL,
  `specialization_id` INT UNSIGNED     NULL DEFAULT NULL,
  `qualification`     VARCHAR(255)     NULL DEFAULT NULL,
  `experience_years`  INT UNSIGNED     NULL DEFAULT NULL,
  `consultation_fee`  INT UNSIGNED     NULL DEFAULT NULL,
  `rating`            DECIMAL(2,1)    NULL DEFAULT NULL,
  `reviews_count`     INT UNSIGNED     NULL DEFAULT NULL,
  `bio`               TEXT             NULL DEFAULT NULL,
  `visiting_hours`    VARCHAR(255)     NULL DEFAULT NULL,
  `awards`            TEXT             NULL DEFAULT NULL,
  `is_featured`       TINYINT(1)       NOT NULL DEFAULT 0,
  `created_at`        TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_nid`    (`nid`),
  UNIQUE KEY `uq_users_email`  (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Doctor catalog and appointment discovery tables
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `districts` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(100) NOT NULL,
  `description` VARCHAR(255) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_districts_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `specializations` (
  `id`   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_specializations_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hospitals` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Failed login attempts (brute-force / rate limiting)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `email`        VARCHAR(190)  NOT NULL,
  `ip_address`   VARCHAR(45)   NOT NULL,
  `attempted_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_login_attempts_lookup` (`email`, `ip_address`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- "Remember me" authentication tokens
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `auth_tokens` (
  `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED  NOT NULL,
  `token_hash` CHAR(64)      NOT NULL,
  `expires_at` DATETIME      NOT NULL,
  `created_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_auth_tokens_hash` (`token_hash`),
  KEY `idx_auth_tokens_user` (`user_id`),
  CONSTRAINT `fk_auth_tokens_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Password reset links
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `password_resets` (
  `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `user_id`      INT UNSIGNED  NOT NULL,
  `token_hash`   CHAR(64)      NOT NULL,
  `expires_at`   DATETIME      NOT NULL,
  `created_at`   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_password_resets_hash` (`token_hash`),
  KEY `idx_password_resets_user` (`user_id`),
  CONSTRAINT `fk_password_resets_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Blood donation donors and requests
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `blood_donors` (
  `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`             INT UNSIGNED NOT NULL,
  `blood_group`         VARCHAR(10)  NOT NULL,
  `phone`               VARCHAR(20)  NOT NULL,
  `district`            VARCHAR(100) NOT NULL,
  `available`           TINYINT(1)   NOT NULL DEFAULT 1,
  `last_donation_date`  DATETIME     NULL DEFAULT NULL,
  `next_eligible_date`  DATETIME     NULL DEFAULT NULL,
  `created_at`          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_blood_donors_user` (`user_id`),
  CONSTRAINT `fk_blood_donors_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `blood_requests` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `requester_name`  VARCHAR(150) NOT NULL,
  `phone`           VARCHAR(20)  NOT NULL,
  `blood_group`     VARCHAR(10)  NOT NULL,
  `district`        VARCHAR(100) NOT NULL,
  `notes`           TEXT         NULL,
  `created_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Vaccination and notification modules
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `doctor_reports` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`       INT UNSIGNED NOT NULL,
  `report_type`   VARCHAR(100) NOT NULL,
  `title`         VARCHAR(190) NOT NULL,
  `details`       TEXT         NULL,
  `uploaded_by`   VARCHAR(150) NOT NULL,
  `uploaded_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_viewed`     TINYINT(1)   NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_doctor_reports_user` (`user_id`),
  CONSTRAINT `fk_doctor_reports_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `notifications` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`           INT UNSIGNED NOT NULL,
  `title`             VARCHAR(190) NOT NULL,
  `message`           TEXT         NOT NULL,
  `notification_type` VARCHAR(100) NOT NULL,
  `target_path`       VARCHAR(255) NULL,
  `created_at`        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_read`           TINYINT(1)   NOT NULL DEFAULT 0,
  `related_url`       VARCHAR(255) NULL DEFAULT NULL,
  `event_key`         VARCHAR(120) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_notifications_user` (`user_id`),
  UNIQUE KEY `uq_notifications_event` (`user_id`, `event_key`),
  CONSTRAINT `fk_notifications_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Appointment scheduling
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `appointments` (
  `appointment_id`   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `patient_id`       INT UNSIGNED NOT NULL,
  `doctor_id`        INT UNSIGNED NOT NULL,
  `appointment_date` DATE            NOT NULL,
  `appointment_time` TIME            NOT NULL,
  `reason`           TEXT            NOT NULL,
  `status`           VARCHAR(30)     NOT NULL DEFAULT 'Pending',
  `doctor_notes`     TEXT            NULL,
  `rejection_reason` TEXT            NULL,
  `status_updated_at` DATETIME       NULL,
  `created_at`       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`appointment_id`),
  KEY `idx_appointments_patient` (`patient_id`),
  KEY `idx_appointments_doctor` (`doctor_id`),
  CONSTRAINT `fk_appointments_patient`
    FOREIGN KEY (`patient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_appointments_doctor`
    FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  UNIQUE KEY `uq_appointments_slot` (`doctor_id`, `appointment_date`, `appointment_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Clinical record additions (safe alongside existing modules)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `allergies` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `patient_id` INT UNSIGNED NOT NULL,
  `allergy_type` VARCHAR(40) NOT NULL, `name` VARCHAR(150) NOT NULL, `reaction_text` VARCHAR(255) NULL,
  `severity` VARCHAR(20) NOT NULL DEFAULT 'Moderate', `notes` TEXT NULL, `recorded_at` DATE NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1, `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`), KEY `idx_allergies_patient` (`patient_id`),
  CONSTRAINT `fk_allergies_patient` FOREIGN KEY (`patient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `medical_documents` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `patient_id` INT UNSIGNED NOT NULL, `uploaded_by` INT UNSIGNED NOT NULL,
  `category` VARCHAR(60) NOT NULL, `original_name` VARCHAR(255) NOT NULL, `stored_name` VARCHAR(100) NOT NULL,
  `mime_type` VARCHAR(100) NOT NULL, `file_size` INT UNSIGNED NOT NULL, `source_name` VARCHAR(150) NULL, `notes` TEXT NULL,
  `verification_status` VARCHAR(30) NOT NULL DEFAULT 'Pending Verification', `verification_note` TEXT NULL,
  `verified_by` INT UNSIGNED NULL, `verified_at` DATETIME NULL, `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`), KEY `idx_documents_patient` (`patient_id`),
  CONSTRAINT `fk_documents_patient` FOREIGN KEY (`patient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Doctor ratings & reviews
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `doctor_ratings` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `doctor_id`   INT UNSIGNED NOT NULL,
  `patient_id`  INT UNSIGNED NOT NULL,
  `rating`      TINYINT UNSIGNED NOT NULL,
  `review`      TEXT         NULL,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ratings_doctor_patient` (`doctor_id`, `patient_id`),
  KEY `idx_ratings_doctor` (`doctor_id`),
  KEY `idx_ratings_patient` (`patient_id`),
  CONSTRAINT `fk_ratings_doctor`
    FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ratings_patient`
    FOREIGN KEY (`patient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Medical test marketplace
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `medical_tests` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `medical_test_bookings` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `test_id`           INT UNSIGNED NOT NULL,
  `user_id`           INT UNSIGNED NOT NULL,
  `booking_date`      DATE NOT NULL,
  `booking_time`      TIME NULL,
  `status`            VARCHAR(30) NOT NULL DEFAULT 'Pending',
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Pharmacy support requests
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `pharmacy_requests` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`       INT UNSIGNED NOT NULL,
  `medicine_name` VARCHAR(190) NOT NULL,
  `notes`         TEXT NULL,
  `status`        VARCHAR(30)  NOT NULL DEFAULT 'Pending',
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pharmacy_requests_user` (`user_id`),
  CONSTRAINT `fk_pharmacy_requests_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Vaccination centers & per-vaccine prices
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `vaccination_centers` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(190) NOT NULL,
  `district`    VARCHAR(100) NOT NULL,
  `division`    VARCHAR(100) NOT NULL,
  `center_type` VARCHAR(20)  NOT NULL DEFAULT 'Public',
  `address`     VARCHAR(255) NULL,
  `phone`       VARCHAR(30)  NULL,
  `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_vaccination_centers_name` (`name`),
  KEY `idx_vaccination_centers_type` (`center_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `vaccination_center_prices` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `center_id`    INT UNSIGNED NOT NULL,
  `vaccine_name` VARCHAR(100) NOT NULL,
  `price`        INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_center_vaccine` (`center_id`, `vaccine_name`),
  KEY `idx_vaccine_prices_vaccine` (`vaccine_name`),
  CONSTRAINT `fk_vaccination_center_prices_center`
    FOREIGN KEY (`center_id`) REFERENCES `vaccination_centers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Patient vaccination booking requests and Lab Technician workflow
CREATE TABLE IF NOT EXISTS `vaccination_bookings` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`         INT UNSIGNED NOT NULL,
  `vaccine_name`    VARCHAR(100) NOT NULL,
  `dose_number`     TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `center_id`       INT UNSIGNED NULL,
  `booking_date`    DATE NOT NULL,
  `booking_time`    TIME NULL,
  `contact_phone`   VARCHAR(30) NOT NULL,
  `notes`           TEXT NULL,
  `status`          VARCHAR(30) NOT NULL DEFAULT 'Pending',
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Hospital/section assignments restrict the requests a Lab Technician can manage.
CREATE TABLE IF NOT EXISTS `lab_technician_assignments` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `technician_id`  INT UNSIGNED NOT NULL,
  `center_id`      INT UNSIGNED NOT NULL,
  `section_name`   VARCHAR(120) NULL,
  `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_technician_center_section` (`technician_id`, `center_id`, `section_name`),
  KEY `idx_lab_assignments_technician` (`technician_id`),
  CONSTRAINT `fk_lab_assignments_technician` FOREIGN KEY (`technician_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_lab_assignments_center` FOREIGN KEY (`center_id`) REFERENCES `vaccination_centers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Patient-controlled record access (consent) and audit
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `access_permissions` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `patient_id`     INT UNSIGNED NOT NULL,
  `provider_id`    INT UNSIGNED NOT NULL,
  `provider_role`  VARCHAR(50)  NOT NULL,
  `record_types`   TEXT NOT NULL,
  `granted_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at`     DATETIME NULL,
  `status`         VARCHAR(20) NOT NULL DEFAULT 'Active',
  `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_access_permissions_patient` (`patient_id`),
  KEY `idx_access_permissions_provider` (`provider_id`),
  CONSTRAINT `fk_access_permissions_patient`
    FOREIGN KEY (`patient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `access_logs` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `permission_id` INT UNSIGNED NULL,
  `patient_id`    INT UNSIGNED NOT NULL,
  `provider_id`   INT UNSIGNED NOT NULL,
  `record_type`   VARCHAR(100) NOT NULL,
  `action`        VARCHAR(50) NOT NULL DEFAULT 'view',
  `accessed_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_access_logs_patient` (`patient_id`),
  KEY `idx_access_logs_provider` (`provider_id`),
  CONSTRAINT `fk_access_logs_patient`
    FOREIGN KEY (`patient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Optional demo accounts (passwords below)
--   Hospital Admin: admin@nhre.gov / Admin123!
--   System Admin:   sysadmin@nhre.gov / SysAdmin123!
-- Uncomment to seed default administrators:
-- -----------------------------------------------------
-- INSERT INTO `users` (`fullname`, `nid`, `email`, `phone`, `password_hash`, `role`)
-- VALUES ('System Administrator', '0000000000', 'admin@nhre.gov', '+8801000000000',
--         '$2y$10$/V9DSSpZKBW.wsXH3rfMueFtjlKlNX0RB4PStg8JGh9KSFG2wq1xm', 'Hospital Admin');
--
-- INSERT INTO `users` (`fullname`, `nid`, `email`, `phone`, `password_hash`, `role`)
-- VALUES ('System Administrator', '0000000001', 'sysadmin@nhre.gov', '+8801000000001',
--         '$2y$10$A7kvhcLKiCGHAp/uR9/zGeFhUp2SGZR.6skOPA/XziQ0yUe4oVi.e', 'System Admin');
