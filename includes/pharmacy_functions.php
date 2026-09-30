<?php
/**
 * Pharmacy module helpers for NHRE.
 * The parent page MUST load auth/auth_check.php first (provides db(), e(),
 * create_notification(), csrf_*(), client_ip(), session helpers).
 */
declare(strict_types=1);

function ensure_pharmacy_tables(): void
{
    db()->exec(
        'CREATE TABLE IF NOT EXISTS `medicines` (
          `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `name`          VARCHAR(190) NOT NULL,
          `generic_name`  VARCHAR(190) NULL DEFAULT NULL,
          `category`      VARCHAR(100) NULL DEFAULT NULL,
          `unit`          VARCHAR(50)  NOT NULL DEFAULT \'unit\',
          `reorder_level` INT UNSIGNED NOT NULL DEFAULT 5,
          `price`         DECIMAL(10,2) NOT NULL DEFAULT 0.00,
          `is_active`     TINYINT(1)   NOT NULL DEFAULT 1,
          `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uq_medicines_name` (`name`),
          KEY `idx_medicines_category` (`category`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
    );

    db()->exec(
        'CREATE TABLE IF NOT EXISTS `medicine_batches` (
          `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `medicine_id`       INT UNSIGNED NOT NULL,
          `batch_no`          VARCHAR(100) NOT NULL,
          `expiry_date`       DATE NOT NULL,
          `quantity_remaining` INT UNSIGNED NOT NULL DEFAULT 0,
          `hospital_id`       INT UNSIGNED NULL DEFAULT NULL,
          `created_by`        INT UNSIGNED NOT NULL,
          `created_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uq_batches_medicine_no` (`medicine_id`, `batch_no`),
          KEY `idx_batches_medicine` (`medicine_id`),
          KEY `idx_batches_expiry` (`medicine_id`, `expiry_date`),
          CONSTRAINT `fk_batches_medicine`
            FOREIGN KEY (`medicine_id`) REFERENCES `medicines` (`id`) ON DELETE CASCADE,
          CONSTRAINT `fk_batches_creator`
            FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
    );

    db()->exec(
        'CREATE TABLE IF NOT EXISTS `prescriptions` (
          `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `prescription_no` VARCHAR(30)  NOT NULL,
          `patient_id`      INT UNSIGNED NOT NULL,
          `doctor_id`       INT UNSIGNED NOT NULL,
          `status`          VARCHAR(30)  NOT NULL DEFAULT \'PENDING\',
          `notes`           TEXT NULL,
          `rejection_reason` TEXT NULL,
          `verified_by`     INT UNSIGNED NULL DEFAULT NULL,
          `verified_at`     DATETIME NULL DEFAULT NULL,
          `expires_at`      DATETIME NOT NULL,
          `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uq_prescriptions_no` (`prescription_no`),
          KEY `idx_prescriptions_patient` (`patient_id`),
          KEY `idx_prescriptions_doctor` (`doctor_id`),
          KEY `idx_prescriptions_status` (`status`),
          CONSTRAINT `fk_prescriptions_patient`
            FOREIGN KEY (`patient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
          CONSTRAINT `fk_prescriptions_doctor`
            FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
          CONSTRAINT `fk_prescriptions_verified`
            FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
    );

    db()->exec(
        'CREATE TABLE IF NOT EXISTS `prescription_items` (
          `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `prescription_id`   INT UNSIGNED NOT NULL,
          `medicine_id`       INT UNSIGNED NOT NULL,
          `quantity_prescribed` DECIMAL(10,2) NOT NULL,
          `dosage`            VARCHAR(100) NOT NULL,
          `frequency`         VARCHAR(100) NOT NULL,
          `duration_days`     INT UNSIGNED NULL DEFAULT NULL,
          `instructions`      TEXT NULL,
          PRIMARY KEY (`id`),
          KEY `idx_prescription_items_rx` (`prescription_id`),
          KEY `idx_prescription_items_medicine` (`medicine_id`),
          CONSTRAINT `fk_prescription_items_rx`
            FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions` (`id`) ON DELETE CASCADE,
          CONSTRAINT `fk_prescription_items_medicine`
            FOREIGN KEY (`medicine_id`) REFERENCES `medicines` (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
    );

    db()->exec(
        'CREATE TABLE IF NOT EXISTS `dispensings` (
          `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `dispensing_no`  VARCHAR(30)  NOT NULL,
          `prescription_id` INT UNSIGNED NOT NULL,
          `patient_id`     INT UNSIGNED NOT NULL,
          `pharmacist_id`  INT UNSIGNED NOT NULL,
          `status`         VARCHAR(30)  NOT NULL DEFAULT \'COMPLETED\',
          `notes`          TEXT NULL,
          `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uq_dispensings_no` (`dispensing_no`),
          KEY `idx_dispensings_rx` (`prescription_id`),
          KEY `idx_dispensings_patient` (`patient_id`),
          KEY `idx_dispensings_pharmacist` (`pharmacist_id`),
          CONSTRAINT `fk_dispensings_rx`
            FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions` (`id`) ON DELETE CASCADE,
          CONSTRAINT `fk_dispensings_patient`
            FOREIGN KEY (`patient_id`) REFERENCES `users` (`id`),
          CONSTRAINT `fk_dispensings_pharmacist`
            FOREIGN KEY (`pharmacist_id`) REFERENCES `users` (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
    );

    db()->exec(
        'CREATE TABLE IF NOT EXISTS `dispensing_items` (
          `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `dispensing_id`       INT UNSIGNED NOT NULL,
          `prescription_item_id` INT UNSIGNED NOT NULL,
          `medicine_id`         INT UNSIGNED NOT NULL,
          `batch_id`            INT UNSIGNED NOT NULL,
          `quantity_given`      DECIMAL(10,2) NOT NULL,
          PRIMARY KEY (`id`),
          KEY `idx_dispensing_items_dispensing` (`dispensing_id`),
          KEY `idx_dispensing_items_prescription_item` (`prescription_item_id`),
          CONSTRAINT `fk_dispensing_items_dispensing`
            FOREIGN KEY (`dispensing_id`) REFERENCES `dispensings` (`id`) ON DELETE CASCADE,
          CONSTRAINT `fk_dispensing_items_prescription_item`
            FOREIGN KEY (`prescription_item_id`) REFERENCES `prescription_items` (`id`) ON DELETE CASCADE,
          CONSTRAINT `fk_dispensing_items_medicine`
            FOREIGN KEY (`medicine_id`) REFERENCES `medicines` (`id`),
          CONSTRAINT `fk_dispensing_items_batch`
            FOREIGN KEY (`batch_id`) REFERENCES `medicine_batches` (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
    );

    db()->exec(
        'CREATE TABLE IF NOT EXISTS `audit_logs` (
          `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `user_id`     INT UNSIGNED NULL DEFAULT NULL,
          `user_role`   VARCHAR(50)  NULL DEFAULT NULL,
          `action`      VARCHAR(60)  NOT NULL,
          `entity_type` VARCHAR(60)  NULL DEFAULT NULL,
          `entity_id`   INT UNSIGNED NULL DEFAULT NULL,
          `details`     TEXT NULL,
          `ip_address`  VARCHAR(45)  NULL DEFAULT NULL,
          `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `idx_audit_action` (`action`),
          KEY `idx_audit_user` (`user_id`),
          KEY `idx_audit_entity` (`entity_type`, `entity_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
    );

    pharmacy_seed_catalog();
    ensure_demo_pharmacist_data();
}

function ensure_demo_pharmacist_data(): void
{
    try {
        $pdo = db();

        $patientId = (int)$pdo->query("SELECT id FROM users WHERE email = 'patient@nhre.gov' LIMIT 1")->fetchColumn();
        $doctorId = (int)$pdo->query("SELECT id FROM users WHERE email = 'doctor001@nhre.dev' LIMIT 1")->fetchColumn();
        $pharmacistId = (int)$pdo->query("SELECT id FROM users WHERE role = 'Pharmacist' ORDER BY id ASC LIMIT 1")->fetchColumn();
        if ($patientId <= 0 || $doctorId <= 0 || $pharmacistId <= 0) {
            return;
        }

        $medicines = $pdo->query("SELECT id, name FROM medicines WHERE name IN ('Paracetamol 500mg', 'Amoxicillin 500mg', 'Metformin 500mg', 'Amlodipine 5mg', 'Cetirizine 10mg') ORDER BY id ASC")->fetchAll();
        $medicineMap = [];
        foreach ($medicines as $medicine) {
            $medicineMap[(string)$medicine['name']] = (int)$medicine['id'];
        }
        if ($medicineMap === []) {
            return;
        }

        $batchInsert = $pdo->prepare(
            'INSERT INTO medicine_batches (medicine_id, batch_no, expiry_date, quantity_remaining, hospital_id, created_by)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE quantity_remaining = quantity_remaining'
        );
        $batchInsert->execute([$medicineMap['Paracetamol 500mg'], 'PAR-DEMO-2401', date('Y-m-d', strtotime('+180 days')), 180, null, $pharmacistId]);
        $batchInsert->execute([$medicineMap['Amoxicillin 500mg'], 'AMX-DEMO-2309', date('Y-m-d', strtotime('+120 days')), 95, null, $pharmacistId]);
        $batchInsert->execute([$medicineMap['Metformin 500mg'], 'MET-DEMO-2407', date('Y-m-d', strtotime('+210 days')), 140, null, $pharmacistId]);

        $rxInsert = $pdo->prepare(
            'INSERT INTO prescriptions (prescription_no, patient_id, doctor_id, status, notes, verified_by, verified_at, expires_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 90 DAY))
             ON DUPLICATE KEY UPDATE status = VALUES(status)'
        );
        $rxInsert->execute([
            'RX-DEMO-001',
            $patientId,
            $doctorId,
            'VERIFIED',
            'Demo prescription for pharmacist preview and dispensing testing.',
            $doctorId,
        ]);
        $prescriptionId = (int)$pdo->query("SELECT id FROM prescriptions WHERE prescription_no = 'RX-DEMO-001' LIMIT 1")->fetchColumn();

        $existingItemCount = (int)$pdo->query("SELECT COUNT(*) FROM prescription_items WHERE prescription_id = {$prescriptionId}")->fetchColumn();
        if ($existingItemCount === 0) {
            $itemInsert = $pdo->prepare(
                'INSERT INTO prescription_items (prescription_id, medicine_id, quantity_prescribed, dosage, frequency, duration_days, instructions)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $itemInsert->execute([$prescriptionId, $medicineMap['Paracetamol 500mg'], 30, '500mg', '1 tablet twice daily', 15, 'Take after meals as needed.']);
            $itemInsert->execute([$prescriptionId, $medicineMap['Amoxicillin 500mg'], 20, '500mg', '1 capsule twice daily', 10, 'Complete the course.']);
        }

        $batchLookup = $pdo->prepare('SELECT id FROM medicine_batches WHERE medicine_id = ? ORDER BY expiry_date ASC, id ASC LIMIT 1');
        $batchLookup->execute([$medicineMap['Paracetamol 500mg']]);
        $paracetamolBatchId = (int)$batchLookup->fetchColumn();
        $batchLookup->execute([$medicineMap['Amoxicillin 500mg']]);
        $amoxBatchId = (int)$batchLookup->fetchColumn();

        $demoDispenseCount = (int)$pdo->query("SELECT COUNT(*) FROM dispensings WHERE dispensing_no IN ('DSP-DEMO-001', 'DSP-DEMO-002')")->fetchColumn();
        if ($demoDispenseCount === 0) {
            $dispensingInsert = $pdo->prepare(
                'INSERT INTO dispensings (dispensing_no, prescription_id, patient_id, pharmacist_id, status, notes)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $dispensingInsert->execute(['DSP-DEMO-001', $prescriptionId, $patientId, $pharmacistId, 'COMPLETED', 'Demo dispensing record for pharmacist preview.']);
            $dispensingId = (int)$pdo->lastInsertId();

            $dispenseItemInsert = $pdo->prepare(
                'INSERT INTO dispensing_items (dispensing_id, prescription_item_id, medicine_id, batch_id, quantity_given)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $itemRows = $pdo->query("SELECT id, medicine_id FROM prescription_items WHERE prescription_id = {$prescriptionId}")->fetchAll();
            foreach ($itemRows as $item) {
                $batchId = ((int)$item['medicine_id'] === $medicineMap['Paracetamol 500mg']) ? $paracetamolBatchId : $amoxBatchId;
                $quantity = ((int)$item['medicine_id'] === $medicineMap['Paracetamol 500mg']) ? 14 : 10;
                $dispenseItemInsert->execute([$dispensingId, (int)$item['id'], (int)$item['medicine_id'], $batchId, $quantity]);
                $pdo->prepare('UPDATE medicine_batches SET quantity_remaining = quantity_remaining - ? WHERE id = ?')->execute([$quantity, $batchId]);
            }

            $secondRx = $pdo->prepare(
                'INSERT INTO prescriptions (prescription_no, patient_id, doctor_id, status, notes, verified_by, verified_at, expires_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 75 DAY))'
            );
            $secondRx->execute([
                'RX-DEMO-002',
                $patientId,
                $doctorId,
                'READY',
                'Second demo prescription for continued pharmacist dashboard preview.',
                $doctorId,
            ]);
            $secondPrescriptionId = (int)$pdo->lastInsertId();

            $secondItem = $pdo->prepare(
                'INSERT INTO prescription_items (prescription_id, medicine_id, quantity_prescribed, dosage, frequency, duration_days, instructions)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $secondItem->execute([$secondPrescriptionId, $medicineMap['Metformin 500mg'], 30, '500mg', '1 tablet daily', 30, 'Continue with meals and monitor blood sugar.']);

            $secondDispensingInsert = $pdo->prepare(
                'INSERT INTO dispensings (dispensing_no, prescription_id, patient_id, pharmacist_id, status, notes)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $secondDispensingInsert->execute(['DSP-DEMO-002', $secondPrescriptionId, $patientId, $pharmacistId, 'COMPLETED', 'Second demo dispensing record for the pharmacist timeline.']);
            $secondDispensingId = (int)$pdo->lastInsertId();
            $secondItemLookup = $pdo->query("SELECT id, medicine_id FROM prescription_items WHERE prescription_id = {$secondPrescriptionId} LIMIT 1")->fetch();
            if ($secondItemLookup) {
                $metforminBatchId = (int)$pdo->query("SELECT id FROM medicine_batches WHERE medicine_id = {$medicineMap['Metformin 500mg']} ORDER BY expiry_date ASC, id ASC LIMIT 1")->fetchColumn();
                $pdo->prepare('INSERT INTO dispensing_items (dispensing_id, prescription_item_id, medicine_id, batch_id, quantity_given) VALUES (?, ?, ?, ?, ?)')->execute([$secondDispensingId, (int)$secondItemLookup['id'], (int)$secondItemLookup['medicine_id'], $metforminBatchId, 15]);
                $pdo->prepare('UPDATE medicine_batches SET quantity_remaining = quantity_remaining - ? WHERE id = ?')->execute([15, $metforminBatchId]);
            }
        }

        $extraPrescriptionSeeds = [
            [
                'RX-DEMO-003',
                'VERIFIED',
                'Follow-up prescription for blood pressure management and monitoring.',
                [
                    ['Amlodipine 5mg', 30, '5mg', '1 tablet daily', 30, 'Take in the morning with water.'],
                    ['Metformin 500mg', 60, '500mg', '1 tablet twice daily', 30, 'Take after meals to improve tolerance.']
                ]
            ],
            [
                'RX-DEMO-004',
                'READY',
                'Seasonal allergy medication refill for pharmacist review.',
                [
                    ['Cetirizine 10mg', 20, '10mg', '1 tablet daily', 20, 'Use daily for symptom control.'],
                    ['Paracetamol 500mg', 12, '500mg', '1 tablet as needed', 6, 'Use only if fever or pain recurs.']
                ]
            ],
        ];

        foreach ($extraPrescriptionSeeds as $seed) {
            [$rxNo, $status, $notes, $items] = $seed;

            $pdo->prepare(
                'INSERT INTO prescriptions (prescription_no, patient_id, doctor_id, status, notes, verified_by, verified_at, expires_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 90 DAY))
                 ON DUPLICATE KEY UPDATE status = VALUES(status), notes = VALUES(notes)'
            )->execute([$rxNo, $patientId, $doctorId, $status, $notes, $doctorId]);

            $extraPrescriptionId = (int)$pdo->query("SELECT id FROM prescriptions WHERE prescription_no = '{$rxNo}' LIMIT 1")->fetchColumn();
            if ($extraPrescriptionId > 0) {
                $itemCheck = (int)$pdo->query("SELECT COUNT(*) FROM prescription_items WHERE prescription_id = {$extraPrescriptionId}")->fetchColumn();
                if ($itemCheck === 0) {
                    $extraItemInsert = $pdo->prepare(
                        'INSERT INTO prescription_items (prescription_id, medicine_id, quantity_prescribed, dosage, frequency, duration_days, instructions)
                         VALUES (?, ?, ?, ?, ?, ?, ?)'
                    );

                    foreach ($items as $item) {
                        [$medicineName, $quantity, $dosage, $frequency, $durationDays, $instructions] = $item;
                        $medicineId = $medicineMap[$medicineName] ?? null;
                        if ($medicineId === null) {
                            continue;
                        }

                        $extraItemInsert->execute([$extraPrescriptionId, $medicineId, $quantity, $dosage, $frequency, $durationDays, $instructions]);
                    }
                }

                $dispenseNo = 'DSP-DEMO-' . substr($rxNo, -3);
                $existingDispense = (int)$pdo->query("SELECT COUNT(*) FROM dispensings WHERE dispensing_no = '{$dispenseNo}'")->fetchColumn();
                if ($existingDispense === 0) {
                    $extraDispensingInsert = $pdo->prepare(
                        'INSERT INTO dispensings (dispensing_no, prescription_id, patient_id, pharmacist_id, status, notes)
                         VALUES (?, ?, ?, ?, ?, ?)'
                    );
                    $extraDispensingInsert->execute([$dispenseNo, $extraPrescriptionId, $patientId, $pharmacistId, 'COMPLETED', 'Additional demo dispensing record for pharmacist preview.']);
                    $extraDispensingId = (int)$pdo->lastInsertId();

                    $itemRows = $pdo->query("SELECT id, medicine_id FROM prescription_items WHERE prescription_id = {$extraPrescriptionId}")->fetchAll();
                    foreach ($itemRows as $row) {
                        $medicine_id = (int)$row['medicine_id'];
                        $batchId = (int)$pdo->query("SELECT id FROM medicine_batches WHERE medicine_id = {$medicine_id} ORDER BY expiry_date ASC, id ASC LIMIT 1")->fetchColumn();
                        if ($batchId <= 0) {
                            continue;
                        }
                        $quantityGiven = ($medicine_id === $medicineMap['Amlodipine 5mg']) ? 20 : 10;
                        $pdo->prepare('INSERT INTO dispensing_items (dispensing_id, prescription_item_id, medicine_id, batch_id, quantity_given) VALUES (?, ?, ?, ?, ?)')->execute([$extraDispensingId, (int)$row['id'], $medicine_id, $batchId, $quantityGiven]);
                        $pdo->prepare('UPDATE medicine_batches SET quantity_remaining = quantity_remaining - ? WHERE id = ?')->execute([$quantityGiven, $batchId]);
                    }
                }
            }
        }

        $pdo->prepare(
            'INSERT INTO audit_logs (user_id, user_role, action, entity_type, entity_id, details, ip_address)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([$pharmacistId, 'Pharmacist', 'SEED_DEMO_DATA', 'prescription', $prescriptionId, 'Inserted expanded pharmacist demo stock, prescription, and dispensing data.', '127.0.0.1']);

        create_notification($pharmacistId, 'Demo pharmacy data expanded', 'Additional sample pharmacist data is now available for dashboard and workflow testing.', 'stock');
    } catch (PDOException $e) {
    }
}

function pharmacy_seed_catalog(): void
{
    try {
        $seed = [
            // name, generic name, category, unit, reorder level, price (BDT per unit)
            ['Paracetamol 500mg', 'Paracetamol', 'Analgesic / Antipyretic', 'tablet', 100, 2.00],
            ['Paracetamol 650mg', 'Paracetamol', 'Analgesic / Antipyretic', 'tablet', 100, 2.50],
            ['Paracetamol Syrup 120mg/5ml', 'Paracetamol', 'Analgesic / Antipyretic', 'bottle', 40, 35.00],
            ['Naproxen 250mg', 'Naproxen', 'Analgesic / Anti-inflammatory', 'tablet', 60, 4.00],
            ['Naproxen 500mg', 'Naproxen', 'Analgesic / Anti-inflammatory', 'tablet', 60, 6.00],
            ['Ibuprofen 200mg', 'Ibuprofen', 'Anti-inflammatory', 'tablet', 80, 2.00],
            ['Ibuprofen 400mg', 'Ibuprofen', 'Anti-inflammatory', 'tablet', 80, 2.50],
            ['Ibuprofen Syrup 100mg/5ml', 'Ibuprofen', 'Anti-inflammatory', 'bottle', 30, 45.00],
            ['Diclofenac 50mg', 'Diclofenac Sodium', 'Analgesic / Anti-inflammatory', 'tablet', 100, 2.50],
            ['Diclofenac Gel 1%', 'Diclofenac Diethylamine', 'Topical', 'tube', 20, 60.00],
            ['Aceclofenac 100mg', 'Aceclofenac', 'Analgesic / Anti-inflammatory', 'tablet', 60, 5.00],
            ['Piroxicam 20mg', 'Piroxicam', 'Analgesic / Anti-inflammatory', 'capsule', 60, 4.00],
            ['Ketorolac 10mg', 'Ketorolac', 'Analgesic / Anti-inflammatory', 'tablet', 40, 8.00],
            ['Nimesulide 100mg', 'Nimesulide', 'Analgesic / Anti-inflammatory', 'tablet', 60, 5.00],
            ['Celecoxib 200mg', 'Celecoxib', 'Analgesic / Anti-inflammatory', 'capsule', 40, 12.00],
            ['Lornoxicam 8mg', 'Lornoxicam', 'Analgesic / Anti-inflammatory', 'tablet', 40, 7.00],
            ['Tramadol 50mg', 'Tramadol', 'Pain Relief', 'tablet', 50, 10.00],
            ['Amoxicillin 500mg', 'Amoxicillin', 'Antibiotic', 'capsule', 50, 9.00],
            ['Amoxicillin Suspension 250mg/5ml', 'Amoxicillin', 'Antibiotic', 'bottle', 20, 90.00],
            ['Amoxicillin + Clavulanate 500/125mg', 'Amoxicillin + Clavulanic Acid', 'Antibiotic', 'tablet', 40, 32.00],
            ['Azithromycin 500mg', 'Azithromycin', 'Antibiotic', 'tablet', 40, 40.00],
            ['Ciprofloxacin 500mg', 'Ciprofloxacin', 'Antibiotic', 'tablet', 40, 12.00],
            ['Doxycycline 100mg', 'Doxycycline', 'Antibiotic', 'capsule', 40, 6.00],
            ['Cefixime 200mg', 'Cefixime', 'Antibiotic', 'capsule', 40, 25.00],
            ['Cefuroxime 250mg', 'Cefuroxime Axetil', 'Antibiotic', 'tablet', 40, 18.00],
            ['Cephalexin 500mg', 'Cephalexin', 'Antibiotic', 'capsule', 40, 15.00],
            ['Clarithromycin 500mg', 'Clarithromycin', 'Antibiotic', 'tablet', 40, 20.00],
            ['Levofloxacin 500mg', 'Levofloxacin', 'Antibiotic', 'tablet', 40, 15.00],
            ['Metronidazole 400mg', 'Metronidazole', 'Antibiotic', 'tablet', 100, 3.00],
            ['Co-trimoxazole 800/160mg', 'Sulfamethoxazole + Trimethoprim', 'Antibiotic', 'tablet', 60, 5.00],
            ['Cloxacillin 500mg', 'Cloxacillin', 'Antibiotic', 'capsule', 40, 12.00],
            ['Erythromycin 250mg', 'Erythromycin', 'Antibiotic', 'tablet', 40, 6.00],
            ['Nitrofurantoin 100mg', 'Nitrofurantoin', 'Antibiotic', 'capsule', 40, 12.00],
            ['Tinidazole 500mg', 'Tinidazole', 'Antibiotic', 'tablet', 40, 6.00],
            ['Fluconazole 150mg', 'Fluconazole', 'Antifungal', 'capsule', 30, 20.00],
            ['Itraconazole 100mg', 'Itraconazole', 'Antifungal', 'capsule', 30, 15.00],
            ['Terbinafine 250mg', 'Terbinafine', 'Antifungal', 'tablet', 30, 35.00],
            ['Acyclovir 400mg', 'Acyclovir', 'Antiviral', 'tablet', 30, 12.00],
            ['Omeprazole 20mg', 'Omeprazole', 'Gastrointestinal', 'capsule', 80, 6.00],
            ['Pantoprazole 40mg', 'Pantoprazole Sodium', 'Gastrointestinal', 'tablet', 80, 7.00],
            ['Esomeprazole 20mg', 'Esomeprazole', 'Gastrointestinal', 'capsule', 80, 7.00],
            ['Rabeprazole 20mg', 'Rabeprazole', 'Gastrointestinal', 'tablet', 60, 8.00],
            ['Lansoprazole 30mg', 'Lansoprazole', 'Gastrointestinal', 'capsule', 60, 6.00],
            ['Ranitidine 150mg', 'Ranitidine', 'Gastrointestinal', 'tablet', 80, 2.00],
            ['Domperidone 10mg', 'Domperidone', 'Gastrointestinal', 'tablet', 80, 4.00],
            ['Itopride 50mg', 'Itopride', 'Gastrointestinal', 'tablet', 60, 7.00],
            ['Ondansetron 4mg', 'Ondansetron', 'Gastrointestinal', 'tablet', 40, 8.00],
            ['Mosapride 5mg', 'Mosapride', 'Gastrointestinal', 'tablet', 60, 6.00],
            ['Hyoscine Butylbromide 10mg', 'Hyoscine Butylbromide', 'Gastrointestinal', 'tablet', 60, 6.00],
            ['Drotaverine 40mg', 'Drotaverine', 'Gastrointestinal', 'tablet', 60, 7.00],
            ['Bisacodyl 5mg', 'Bisacodyl', 'Gastrointestinal', 'tablet', 60, 4.00],
            ['Sucralfate 1g Suspension', 'Sucralfate', 'Gastrointestinal', 'bottle', 20, 90.00],
            ['Antacid Suspension', 'Aluminium + Magnesium Hydroxide', 'Gastrointestinal', 'bottle', 30, 60.00],
            ['Lactulose 10g/15ml', 'Lactulose', 'Gastrointestinal', 'bottle', 20, 80.00],
            ['Albendazole 400mg', 'Albendazole', 'Gastrointestinal', 'tablet', 60, 15.00],
            ['Zinc Sulfate 20mg', 'Zinc Sulfate', 'Gastrointestinal', 'tablet', 60, 2.00],
            ['Cetirizine 10mg', 'Cetirizine', 'Allergy', 'tablet', 60, 2.00],
            ['Cetirizine Syrup 5mg/5ml', 'Cetirizine', 'Allergy', 'bottle', 30, 45.00],
            ['Loratadine 10mg', 'Loratadine', 'Allergy', 'tablet', 60, 4.00],
            ['Fexofenadine 120mg', 'Fexofenadine', 'Allergy', 'tablet', 40, 9.00],
            ['Desloratadine 5mg', 'Desloratadine', 'Allergy', 'tablet', 40, 6.00],
            ['Levocetirizine 5mg', 'Levocetirizine', 'Allergy', 'tablet', 40, 6.00],
            ['Prednisolone 5mg', 'Prednisolone', 'Steroid', 'tablet', 60, 2.00],
            ['Salbutamol Inhaler 100mcg', 'Salbutamol', 'Respiratory', 'puff', 30, 1.50],
            ['Budesonide Inhaler 200mcg', 'Budesonide', 'Respiratory', 'puff', 20, 2.50],
            ['Aminophylline 200mg', 'Aminophylline', 'Respiratory', 'tablet', 60, 3.00],
            ['Salbutamol + Ipratropium Nebule', 'Salbutamol + Ipratropium', 'Respiratory', 'unit', 30, 15.00],
            ['Montelukast 10mg', 'Montelukast', 'Respiratory', 'tablet', 60, 17.50],
            ['Cough Syrup', 'Dextromethorphan', 'Respiratory', 'bottle', 30, 60.00],
            ['Oral Rehydration Salts', 'ORS', 'Emergency Care', 'sachet', 200, 10.00],
            ['Vitamin C 1000mg', 'Ascorbic Acid', 'Supplement', 'tablet', 60, 5.00],
            ['Vitamin D3 1000 IU', 'Cholecalciferol', 'Supplement', 'capsule', 60, 8.00],
            ['Vitamin B Complex', 'Vitamin B Complex', 'Supplement', 'tablet', 100, 4.00],
            ['Vitamin B1+B6+B12', 'Vitamin B1+B6+B12', 'Supplement', 'tablet', 60, 10.00],
            ['Folic Acid 5mg', 'Folic Acid', 'Supplement', 'tablet', 60, 1.50],
            ['Iron + Folic Acid', 'Ferrous Sulfate + Folic Acid', 'Supplement', 'tablet', 60, 3.00],
            ['Calcium + Vitamin D3', 'Calcium Carbonate + Cholecalciferol', 'Supplement', 'tablet', 60, 8.00],
            ['Omega-3 1000mg', 'Fish Oil', 'Supplement', 'capsule', 40, 15.00],
            ['Multivitamin Syrup', 'Multivitamin', 'Supplement', 'bottle', 20, 90.00],
            ['Insulin Glargine 100IU/ml', 'Insulin Glargine', 'Diabetes Care', 'ml', 20, 200.00],
            ['Human Insulin Mixtard 30/70 100IU/ml', 'Biphasic Insulin', 'Diabetes Care', 'ml', 20, 60.00],
            ['Metformin 500mg', 'Metformin', 'Diabetes Care', 'tablet', 100, 4.00],
            ['Metformin XR 750mg', 'Metformin', 'Diabetes Care', 'tablet', 60, 9.00],
            ['Glimepiride 2mg', 'Glimepiride', 'Diabetes Care', 'tablet', 60, 8.00],
            ['Gliclazide 80mg', 'Gliclazide', 'Diabetes Care', 'tablet', 60, 7.00],
            ['Glibenclamide 5mg', 'Glibenclamide', 'Diabetes Care', 'tablet', 60, 2.00],
            ['Sitagliptin 100mg', 'Sitagliptin', 'Diabetes Care', 'tablet', 40, 30.00],
            ['Linagliptin 5mg', 'Linagliptin', 'Diabetes Care', 'tablet', 40, 20.00],
            ['Pioglitazone 15mg', 'Pioglitazone', 'Diabetes Care', 'tablet', 40, 10.00],
            ['Empagliflozin 10mg', 'Empagliflozin', 'Diabetes Care', 'tablet', 40, 25.00],
            ['Amlodipine 5mg', 'Amlodipine', 'Cardiology', 'tablet', 100, 7.00],
            ['Amlodipine + Atenolol 5/50mg', 'Amlodipine + Atenolol', 'Cardiology', 'tablet', 60, 8.00],
            ['Atorvastatin 20mg', 'Atorvastatin', 'Cardiology', 'tablet', 80, 12.00],
            ['Rosuvastatin 10mg', 'Rosuvastatin', 'Cardiology', 'tablet', 60, 12.00],
            ['Losartan 50mg', 'Losartan', 'Cardiology', 'tablet', 80, 10.00],
            ['Telmisartan 40mg', 'Telmisartan', 'Cardiology', 'tablet', 60, 17.00],
            ['Telmisartan + Amlodipine 40/5mg', 'Telmisartan + Amlodipine', 'Cardiology', 'tablet', 60, 25.00],
            ['Valsartan 80mg', 'Valsartan', 'Cardiology', 'tablet', 60, 15.00],
            ['Ramipril 5mg', 'Ramipril', 'Cardiology', 'tablet', 60, 10.00],
            ['Enalapril 5mg', 'Enalapril', 'Cardiology', 'tablet', 60, 6.00],
            ['Aspirin 75mg', 'Acetylsalicylic Acid', 'Cardiology', 'tablet', 100, 1.00],
            ['Atenolol 50mg', 'Atenolol', 'Cardiology', 'tablet', 60, 5.00],
            ['Metoprolol 50mg', 'Metoprolol', 'Cardiology', 'tablet', 60, 6.00],
            ['Bisoprolol 5mg', 'Bisoprolol', 'Cardiology', 'tablet', 60, 12.00],
            ['Carvedilol 6.25mg', 'Carvedilol', 'Cardiology', 'tablet', 40, 10.00],
            ['Clopidogrel 75mg', 'Clopidogrel', 'Cardiology', 'tablet', 60, 18.00],
            ['Ticagrelor 90mg', 'Ticagrelor', 'Cardiology', 'tablet', 30, 80.00],
            ['Isosorbide Mononitrate 20mg', 'Isosorbide Mononitrate', 'Cardiology', 'tablet', 60, 8.00],
            ['Glyceryl Trinitrate 0.5mg', 'Glyceryl Trinitrate', 'Cardiology', 'tablet', 40, 8.00],
            ['Furosemide 40mg', 'Furosemide', 'Cardiology', 'tablet', 60, 2.00],
            ['Spironolactone 25mg', 'Spironolactone', 'Cardiology', 'tablet', 40, 8.00],
            ['Hydrochlorothiazide 25mg', 'Hydrochlorothiazide', 'Cardiology', 'tablet', 60, 2.00],
            ['Ezetimibe 10mg', 'Ezetimibe', 'Cardiology', 'tablet', 40, 20.00],
            ['Digoxin 0.25mg', 'Digoxin', 'Cardiology', 'tablet', 40, 4.00],
            ['Warfarin 5mg', 'Warfarin', 'Cardiology', 'tablet', 30, 10.00],
            ['Nifedipine 10mg', 'Nifedipine', 'Cardiology', 'capsule', 40, 5.00],
            ['Gabapentin 300mg', 'Gabapentin', 'Neurology', 'capsule', 40, 15.00],
            ['Pregabalin 75mg', 'Pregabalin', 'Neurology', 'capsule', 40, 20.00],
            ['Carbamazepine 200mg', 'Carbamazepine', 'Neurology', 'tablet', 40, 10.00],
            ['Sodium Valproate 500mg', 'Sodium Valproate', 'Neurology', 'tablet', 40, 15.00],
            ['Levodopa + Carbidopa 100/25mg', 'Levodopa + Carbidopa', 'Neurology', 'tablet', 40, 15.00],
            ['Fluoxetine 20mg', 'Fluoxetine', 'Psychiatry', 'capsule', 40, 10.00],
            ['Sertraline 50mg', 'Sertraline', 'Psychiatry', 'tablet', 40, 12.00],
            ['Escitalopram 10mg', 'Escitalopram', 'Psychiatry', 'tablet', 40, 15.00],
            ['Amitriptyline 25mg', 'Amitriptyline', 'Psychiatry', 'tablet', 40, 4.00],
            ['Clonazepam 0.5mg', 'Clonazepam', 'Psychiatry', 'tablet', 40, 3.00],
            ['Diazepam 5mg', 'Diazepam', 'Psychiatry', 'tablet', 40, 2.00],
            ['Levothyroxine 50mcg', 'Levothyroxine', 'Endocrinology', 'tablet', 60, 2.00],
            ['Allopurinol 300mg', 'Allopurinol', 'Other', 'tablet', 40, 7.00],
            ['Colchicine 0.5mg', 'Colchicine', 'Other', 'tablet', 30, 4.00],
            ['Hydroxychloroquine 200mg', 'Hydroxychloroquine', 'Other', 'tablet', 40, 15.00],
            ['Ketoconazole 2% Cream', 'Ketoconazole', 'Topical', 'tube', 20, 50.00],
            ['Clotrimazole 1% Cream', 'Clotrimazole', 'Topical', 'tube', 20, 40.00],
            ['Hydrocortisone 1% Cream', 'Hydrocortisone', 'Topical', 'tube', 20, 40.00],
            ['Chloramphenicol Eye Ointment 1%', 'Chloramphenicol', 'Topical', 'tube', 20, 30.00],
            ['Artificial Tears (Eye Drops)', 'Carboxymethylcellulose', 'Topical', 'bottle', 20, 70.00],
            ['Calamine Lotion', 'Calamine', 'Topical', 'bottle', 20, 50.00],
            ['Chlorhexidine Mouthwash', 'Chlorhexidine', 'Topical', 'bottle', 20, 80.00],
            ['Normal Saline 0.9% 500ml', 'Sodium Chloride', 'IV Fluids', 'bag', 50, 60.00],
            ['Dextrose 5% 500ml', 'Dextrose', 'IV Fluids', 'bag', 50, 70.00],
            ["Ringer's Lactate 500ml", "Ringer's Lactate", 'IV Fluids', 'bag', 50, 80.00],
        ];

        $stmt = db()->prepare(
            'INSERT INTO medicines (name, generic_name, category, unit, reorder_level, price)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                generic_name = VALUES(generic_name),
                category = VALUES(category),
                unit = VALUES(unit),
                reorder_level = VALUES(reorder_level),
                price = VALUES(price)'
        );
        foreach ($seed as $row) {
            $stmt->execute($row);
        }

        $batch_seed = [
            'Paracetamol 500mg' => [['PARA-2601', '+45 days', 500], ['PARA-2609', '+240 days', 1200]],
            'Paracetamol 650mg' => [['PARA65-2601', '+180 days', 400]],
            'Paracetamol Syrup 120mg/5ml' => [['PARAS-2601', '+150 days', 60]],
            'Naproxen 250mg' => [['NAP-2601', '+210 days', 240]],
            'Naproxen 500mg' => [['NAP5-2601', '+210 days', 240]],
            'Ibuprofen 200mg' => [['IBU2-2601', '+200 days', 300]],
            'Ibuprofen 400mg' => [['IBU-2601', '+90 days', 0], ['IBU-2602', '+190 days', 120]],
            'Ibuprofen Syrup 100mg/5ml' => [['IBUS-2601', '+140 days', 40]],
            'Diclofenac 50mg' => [['DICL-2601', '+220 days', 400]],
            'Diclofenac Gel 1%' => [['DICLG-2601', '+365 days', 30]],
            'Aceclofenac 100mg' => [['ACEC-2601', '+200 days', 240]],
            'Piroxicam 20mg' => [['PIRO-2601', '+210 days', 240]],
            'Ketorolac 10mg' => [['KETO-2601', '+180 days', 160]],
            'Nimesulide 100mg' => [['NIME-2601', '+190 days', 240]],
            'Celecoxib 200mg' => [['CELE-2601', '+200 days', 160]],
            'Lornoxicam 8mg' => [['LORNO-2601', '+180 days', 160]],
            'Tramadol 50mg' => [['TRAM-2601', '+240 days', 200]],
            'Amoxicillin 500mg' => [['AMOX-2508', '-30 days', 120], ['AMOX-2602', '+70 days', 300]],
            'Amoxicillin Suspension 250mg/5ml' => [['AMOXS-2601', '+100 days', 30]],
            'Amoxicillin + Clavulanate 500/125mg' => [['AMCL-2601', '+120 days', 160]],
            'Azithromycin 500mg' => [['AZI-2601', '+90 days', 200]],
            'Ciprofloxacin 500mg' => [['CIPRO-2601', '+150 days', 200]],
            'Doxycycline 100mg' => [['DOXY-2601', '+170 days', 200]],
            'Cefixime 200mg' => [['CEFI-2601', '+160 days', 160]],
            'Cefuroxime 250mg' => [['CEFU-2601', '+150 days', 160]],
            'Cephalexin 500mg' => [['CEPX-2601', '+170 days', 160]],
            'Clarithromycin 500mg' => [['CLARI-2601', '+140 days', 160]],
            'Levofloxacin 500mg' => [['LEVO-2601', '+150 days', 160]],
            'Metronidazole 400mg' => [['METRO-2601', '+210 days', 400]],
            'Co-trimoxazole 800/160mg' => [['COTRI-2601', '+200 days', 240]],
            'Cloxacillin 500mg' => [['CLOX-2601', '+160 days', 160]],
            'Erythromycin 250mg' => [['ERY-2601', '+170 days', 160]],
            'Nitrofurantoin 100mg' => [['NITRO-2601', '+180 days', 160]],
            'Tinidazole 500mg' => [['TINI-2601', '+190 days', 160]],
            'Fluconazole 150mg' => [['FLUC-2601', '+180 days', 120]],
            'Itraconazole 100mg' => [['ITRA-2601', '+180 days', 120]],
            'Terbinafine 250mg' => [['TERB-2601', '+170 days', 120]],
            'Acyclovir 400mg' => [['ACY-2601', '+190 days', 120]],
            'Omeprazole 20mg' => [['OME-2602', '+150 days', 400]],
            'Pantoprazole 40mg' => [['PANT-2601', '+160 days', 300]],
            'Esomeprazole 20mg' => [['ESO-2601', '+160 days', 300]],
            'Rabeprazole 20mg' => [['RABE-2601', '+170 days', 240]],
            'Lansoprazole 30mg' => [['LANS-2601', '+170 days', 240]],
            'Ranitidine 150mg' => [['RANI-2601', '+200 days', 300]],
            'Domperidone 10mg' => [['DOMP-2601', '+190 days', 300]],
            'Itopride 50mg' => [['ITOP-2601', '+190 days', 240]],
            'Ondansetron 4mg' => [['ONDA-2601', '+180 days', 160]],
            'Mosapride 5mg' => [['MOSA-2601', '+190 days', 240]],
            'Hyoscine Butylbromide 10mg' => [['HYOS-2601', '+200 days', 240]],
            'Drotaverine 40mg' => [['DROT-2601', '+200 days', 240]],
            'Bisacodyl 5mg' => [['BISA-2601', '+210 days', 240]],
            'Sucralfate 1g Suspension' => [['SUCR-2601', '+120 days', 30]],
            'Antacid Suspension' => [['ANTA-2601', '+130 days', 50]],
            'Lactulose 10g/15ml' => [['LACT-2601', '+160 days', 30]],
            'Albendazole 400mg' => [['ALBE-2601', '+220 days', 240]],
            'Zinc Sulfate 20mg' => [['ZINC-2601', '+210 days', 240]],
            'Cetirizine 10mg' => [['CET-2601', '+180 days', 300]],
            'Cetirizine Syrup 5mg/5ml' => [['CETS-2601', '+140 days', 50]],
            'Loratadine 10mg' => [['LORA-2601', '+190 days', 240]],
            'Fexofenadine 120mg' => [['FEXO-2601', '+180 days', 160]],
            'Desloratadine 5mg' => [['DESL-2601', '+180 days', 160]],
            'Levocetirizine 5mg' => [['LEVOC-2601', '+180 days', 160]],
            'Prednisolone 5mg' => [['PRED-2601', '+200 days', 240]],
            'Salbutamol Inhaler 100mcg' => [['SAL-2602', '+80 days', 60]],
            'Budesonide Inhaler 200mcg' => [['BUD-2601', '+90 days', 40]],
            'Aminophylline 200mg' => [['AMIN-2601', '+190 days', 240]],
            'Salbutamol + Ipratropium Nebule' => [['SALIP-2601', '+120 days', 120]],
            'Montelukast 10mg' => [['MONT-2601', '+180 days', 240]],
            'Cough Syrup' => [['COUGH-2603', '+100 days', 45]],
            'Oral Rehydration Salts' => [['ORS-2604', '+365 days', 1000]],
            'Vitamin C 1000mg' => [['VITC-2601', '+160 days', 250]],
            'Vitamin D3 1000 IU' => [['VITD-2601', '+220 days', 240]],
            'Vitamin B Complex' => [['VITB-2601', '+200 days', 400]],
            'Vitamin B1+B6+B12' => [['NEURO-2601', '+200 days', 240]],
            'Folic Acid 5mg' => [['FOLIC-2601', '+210 days', 240]],
            'Iron + Folic Acid' => [['IRON-2601', '+200 days', 240]],
            'Calcium + Vitamin D3' => [['CALD-2601', '+220 days', 240]],
            'Omega-3 1000mg' => [['OMEGA-2601', '+300 days', 160]],
            'Multivitamin Syrup' => [['MVIT-2601', '+150 days', 30]],
            'Insulin Glargine 100IU/ml' => [['INS-2601', '+200 days', 40]],
            'Human Insulin Mixtard 30/70 100IU/ml' => [['HUMI-2601', '+180 days', 60]],
            'Metformin 500mg' => [['MET-2603', '+120 days', 600]],
            'Metformin XR 750mg' => [['METXR-2601', '+200 days', 300]],
            'Glimepiride 2mg' => [['GLIM-2601', '+200 days', 240]],
            'Gliclazide 80mg' => [['GLIC-2601', '+200 days', 240]],
            'Glibenclamide 5mg' => [['GLIB-2601', '+210 days', 240]],
            'Sitagliptin 100mg' => [['SITA-2601', '+200 days', 160]],
            'Linagliptin 5mg' => [['LINA-2601', '+200 days', 160]],
            'Pioglitazone 15mg' => [['PIO-2601', '+200 days', 160]],
            'Empagliflozin 10mg' => [['EMPA-2601', '+220 days', 160]],
            'Amlodipine 5mg' => [['AML-2601', '+210 days', 500]],
            'Amlodipine + Atenolol 5/50mg' => [['AMLA-2601', '+210 days', 240]],
            'Atorvastatin 20mg' => [['ATOR-2601', '+220 days', 300]],
            'Rosuvastatin 10mg' => [['ROSU-2601', '+220 days', 240]],
            'Losartan 50mg' => [['LOSA-2601', '+220 days', 300]],
            'Telmisartan 40mg' => [['TELM-2601', '+220 days', 240]],
            'Telmisartan + Amlodipine 40/5mg' => [['TELA-2601', '+220 days', 240]],
            'Valsartan 80mg' => [['VALS-2601', '+220 days', 240]],
            'Ramipril 5mg' => [['RAMI-2601', '+210 days', 240]],
            'Enalapril 5mg' => [['ENAL-2601', '+210 days', 240]],
            'Atenolol 50mg' => [['ATEN-2601', '+210 days', 240]],
            'Metoprolol 50mg' => [['METO-2601', '+210 days', 240]],
            'Bisoprolol 5mg' => [['BISO-2601', '+210 days', 240]],
            'Carvedilol 6.25mg' => [['CARV-2601', '+200 days', 160]],
            'Clopidogrel 75mg' => [['CLOP-2601', '+220 days', 240]],
            'Ticagrelor 90mg' => [['TICA-2601', '+220 days', 120]],
            'Isosorbide Mononitrate 20mg' => [['ISMN-2601', '+210 days', 240]],
            'Glyceryl Trinitrate 0.5mg' => [['GTN-2601', '+180 days', 160]],
            'Furosemide 40mg' => [['FURO-2601', '+200 days', 240]],
            'Spironolactone 25mg' => [['SPIR-2601', '+200 days', 160]],
            'Hydrochlorothiazide 25mg' => [['HCTZ-2601', '+210 days', 240]],
            'Ezetimibe 10mg' => [['EZET-2601', '+220 days', 160]],
            'Digoxin 0.25mg' => [['DIGO-2601', '+200 days', 160]],
            'Warfarin 5mg' => [['WARF-2601', '+200 days', 120]],
            'Nifedipine 10mg' => [['NIFE-2601', '+200 days', 160]],
            'Gabapentin 300mg' => [['GABA-2601', '+200 days', 160]],
            'Pregabalin 75mg' => [['PREG-2601', '+200 days', 160]],
            'Carbamazepine 200mg' => [['CARBA-2601', '+210 days', 160]],
            'Sodium Valproate 500mg' => [['VALP-2601', '+210 days', 160]],
            'Levodopa + Carbidopa 100/25mg' => [['LEVO-C-2601', '+180 days', 160]],
            'Fluoxetine 20mg' => [['FLUOX-2601', '+200 days', 160]],
            'Sertraline 50mg' => [['SERT-2601', '+200 days', 160]],
            'Escitalopram 10mg' => [['ESCI-2601', '+200 days', 160]],
            'Amitriptyline 25mg' => [['AMIT-2601', '+200 days', 160]],
            'Clonazepam 0.5mg' => [['CLON-2601', '+200 days', 160]],
            'Diazepam 5mg' => [['DIAZ-2601', '+210 days', 160]],
            'Levothyroxine 50mcg' => [['LEVO-T-2601', '+220 days', 240]],
            'Allopurinol 300mg' => [['ALLO-2601', '+220 days', 160]],
            'Colchicine 0.5mg' => [['COLCH-2601', '+200 days', 120]],
            'Hydroxychloroquine 200mg' => [['HYDR-2601', '+220 days', 160]],
            'Ketoconazole 2% Cream' => [['KETO-C-2601', '+365 days', 30]],
            'Clotrimazole 1% Cream' => [['CLOT-C-2601', '+365 days', 30]],
            'Hydrocortisone 1% Cream' => [['HYDR-C-2601', '+365 days', 30]],
            'Chloramphenicol Eye Ointment 1%' => [['CHLOR-E-2601', '+300 days', 30]],
            'Artificial Tears (Eye Drops)' => [['ARTIF-2601', '+180 days', 30]],
            'Calamine Lotion' => [['CALA-2601', '+365 days', 30]],
            'Chlorhexidine Mouthwash' => [['CHLOR-M-2601', '+365 days', 30]],
            'Normal Saline 0.9% 500ml' => [['NS-2601', '+365 days', 100]],
            'Dextrose 5% 500ml' => [['DEX-2601', '+365 days', 100]],
            "Ringer's Lactate 500ml" => [['RL-2601', '+365 days', 100]],
        ];

        $findMedicine = db()->prepare('SELECT id FROM medicines WHERE name = ? LIMIT 1');
        $insertBatch = db()->prepare(
            'INSERT IGNORE INTO medicine_batches (medicine_id, batch_no, expiry_date, quantity_remaining, created_by)
             VALUES (?, ?, ?, ?, ?)'
        );
        $creatorId = (int)($_SESSION['user_id'] ?? 0);
        if ($creatorId <= 0) {
            try {
                $creatorId = (int)db()->query("SELECT id FROM users WHERE role = 'Pharmacist' ORDER BY id LIMIT 1")->fetchColumn();
            } catch (PDOException $e) {
                $creatorId = 0;
            }
        }
        if ($creatorId <= 0) {
            return;
        }
        foreach ($batch_seed as $medicineName => $batches) {
            $findMedicine->execute([$medicineName]);
            $medicineId = (int)$findMedicine->fetchColumn();
            if ($medicineId === 0) {
                continue;
            }
            foreach ($batches as $batch) {
                $insertBatch->execute([
                    $medicineId,
                    $batch[0],
                    date('Y-m-d', strtotime($batch[1])),
                    (int)$batch[2],
                    $creatorId,
                ]);
            }
        }
    } catch (PDOException $e) {
    }
}

/** Number of days a prescription remains valid before it expires. */
function pharmacy_prescription_days_valid(): int
{
    return 30;
}

function prescription_status_transitions(): array
{
    return [
        'PENDING' => ['VERIFIED', 'REJECTED', 'EXPIRED'],
        'VERIFIED' => ['READY', 'DISPENSED', 'PARTIALLY_DISPENSED', 'REJECTED', 'EXPIRED'],
        'READY' => ['DISPENSED', 'PARTIALLY_DISPENSED', 'EXPIRED'],
        'PARTIALLY_DISPENSED' => ['READY', 'DISPENSED', 'EXPIRED'],
        'DISPENSED' => [],
        'REJECTED' => [],
        'EXPIRED' => [],
    ];
}

function can_transition_prescription(string $from, string $to): bool
{
    return in_array($to, prescription_status_transitions()[$from] ?? [], true);
}

function prescription_dispensable_statuses(): array
{
    return ['VERIFIED', 'READY', 'PARTIALLY_DISPENSED'];
}

/** Lazy-expire prescriptions past their validity window. */
function expire_stale_prescriptions(): void
{
    try {
        db()->prepare(
            "UPDATE prescriptions
                SET status = 'EXPIRED'
              WHERE status NOT IN ('DISPENSED', 'REJECTED', 'EXPIRED')
                AND expires_at < NOW()"
        )->execute();
    } catch (PDOException $e) {
    }
}

function pharmacy_status_badge(string $status): string
{
    $map = [
        'PENDING' => ['bg-warning-subtle text-warning-emphasis', 'fa-clock'],
        'VERIFIED' => ['bg-info-subtle text-info-emphasis', 'fa-user-check'],
        'READY' => ['bg-primary-subtle text-primary-emphasis', 'fa-box-open'],
        'DISPENSED' => ['bg-success-subtle text-success-emphasis', 'fa-circle-check'],
        'PARTIALLY_DISPENSED' => ['bg-primary-subtle text-primary-emphasis', 'fa-circle-half-stroke'],
        'REJECTED' => ['bg-danger-subtle text-danger-emphasis', 'fa-ban'],
        'EXPIRED' => ['bg-secondary-subtle text-secondary-emphasis', 'fa-clock-rotate-left'],
    ];
    $meta = $map[$status] ?? ['bg-secondary-subtle text-secondary-emphasis', 'fa-info'];
    $label = ucwords(strtolower(str_replace('_', ' ', $status)));
    return '<span class="badge rounded-pill ' . $meta[0] . '"><i class="fa-solid ' . $meta[1] . '"></i> ' . e($label) . '</span>';
}

/** Format a numeric quantity for display without trailing zeros. */
function pharmacy_qty(float $value): string
{
    return rtrim(rtrim(number_format($value, 2), '0'), '.');
}

/** Log a pharmacy module action to the audit trail. */
function log_audit(string $action, ?string $entity_type = null, ?int $entity_id = null, string $details = ''): void
{
    try {
        $stmt = db()->prepare(
            'INSERT INTO audit_logs (user_id, user_role, action, entity_type, entity_id, details, ip_address)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            (int)($_SESSION['user_id'] ?? 0) ?: null,
            (string)($_SESSION['role'] ?? '') ?: null,
            $action,
            $entity_type,
            $entity_id,
            $details !== '' ? $details : null,
            client_ip(),
        ]);
    } catch (PDOException $e) {
    }
}

/** Hospital scope for the logged-in pharmacist (NULL = central/all hospitals). */
function current_user_hospital_id(): ?int
{
    try {
        $stmt = db()->prepare('SELECT hospital_id FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([(int)($_SESSION['user_id'] ?? 0)]);
        $value = $stmt->fetchColumn();
        return $value !== false && $value !== null ? (int)$value : null;
    } catch (PDOException $e) {
        return null;
    }
}

/** Sum of non-expired, on-hand stock for a medicine within the pharmacist's scope. */
function available_stock(int $medicine_id): int
{
    $hospital_id = current_user_hospital_id();
    $sql = 'SELECT COALESCE(SUM(quantity_remaining), 0)
              FROM medicine_batches
             WHERE medicine_id = ? AND expiry_date > CURDATE() AND quantity_remaining > 0';
    $params = [$medicine_id];
    if ($hospital_id !== null) {
        $sql .= ' AND (hospital_id = ? OR hospital_id IS NULL)';
        $params[] = $hospital_id;
    }
    try {
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * Stock summary for a medicine within scope.
 * @return array{available:int,total:int,expiring:int}
 */
function medicine_stock_summary(int $medicine_id): array
{
    $hospital_id = current_user_hospital_id();
    $sql = 'SELECT
              COALESCE(SUM(CASE WHEN expiry_date > CURDATE() THEN quantity_remaining ELSE 0 END), 0) AS available,
              COALESCE(SUM(quantity_remaining), 0) AS total,
              COALESCE(SUM(CASE WHEN expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 60 DAY)
                                 THEN quantity_remaining ELSE 0 END), 0) AS expiring
            FROM medicine_batches
            WHERE medicine_id = ?';
    $params = [$medicine_id];
    if ($hospital_id !== null) {
        $sql .= ' AND (hospital_id = ? OR hospital_id IS NULL)';
        $params[] = $hospital_id;
    }
    try {
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return [
            'available' => (int)($row['available'] ?? 0),
            'total' => (int)($row['total'] ?? 0),
            'expiring' => (int)($row['expiring'] ?? 0),
        ];
    } catch (PDOException $e) {
        return ['available' => 0, 'total' => 0, 'expiring' => 0];
    }
}

/** Send a notification to every Pharmacist account using a stable event key for deduplication. */
function notify_pharmacists(string $title, string $message, string $type = 'prescription', ?string $eventKey = null): void
{
    try {
        $rows = db()->query("SELECT id FROM users WHERE role = 'Pharmacist'")->fetchAll();
        foreach ($rows as $row) {
            $userId = (int)$row['id'];
            if ($eventKey !== null && $eventKey !== '') {
                create_event_notification(db(), $userId, $title, $message, $type, $eventKey, 'stock.php');
                continue;
            }
            create_notification($userId, $title, $message, $type);
        }
    } catch (PDOException $e) {
    }
}

/** Trigger pharmacist-facing stock and expiry notices when a medicine enters a risk state. */
function pharmacist_stock_alerts(int $medicine_id): void
{
    try {
        $stmt = db()->prepare('SELECT name, reorder_level FROM medicines WHERE id = ? LIMIT 1');
        $stmt->execute([$medicine_id]);
        $medicine = $stmt->fetch();
        if (!$medicine) {
            return;
        }

        $summary = medicine_stock_summary($medicine_id);
        $name = (string)$medicine['name'];
        $reorder = (int)$medicine['reorder_level'];
        $available = (int)$summary['available'];
        $expiring = (int)$summary['expiring'];

        if ($available <= 0) {
            notify_pharmacists('Medicine out of stock', 'Medicine "' . $name . '" is now out of stock and needs restocking.', 'stock', 'stock-out:' . $medicine_id);
            return;
        }

        if ($available < $reorder) {
            notify_pharmacists('Low stock alert', 'Medicine "' . $name . '" is below its reorder level (' . $available . ' remaining).', 'stock', 'stock-low:' . $medicine_id);
        }

        if ($expiring > 0) {
            notify_pharmacists('Expiring medicine', 'Medicine "' . $name . '" has stock expiring within 60 days and should be reviewed.', 'stock', 'stock-expiring:' . $medicine_id);
        }
    } catch (PDOException $e) {
    }
}

function get_prescription(int $id): ?array
{
    $stmt = db()->prepare(
        'SELECT p.*, pat.fullname AS patient_name, pat.account_number, pat.date_of_birth, pat.gender,
                doc.fullname AS doctor_name, ver.fullname AS verified_name
           FROM prescriptions p
           JOIN users pat ON pat.id = p.patient_id
           JOIN users doc ON doc.id = p.doctor_id
           LEFT JOIN users ver ON ver.id = p.verified_by
          WHERE p.id = ? LIMIT 1'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/** Prescription items with the quantity already dispensed across all dispensings. */
function get_prescription_items(int $id): array
{
    $stmt = db()->prepare(
        'SELECT pi.*, m.name AS medicine_name, m.unit,
                COALESCE((SELECT SUM(di.quantity_given)
                            FROM dispensing_items di
                           WHERE di.prescription_item_id = pi.id), 0) AS given
           FROM prescription_items pi
           JOIN medicines m ON m.id = pi.medicine_id
          WHERE pi.prescription_id = ?
          ORDER BY pi.id ASC'
    );
    $stmt->execute([$id]);
    return $stmt->fetchAll();
}
