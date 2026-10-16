<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../auth/auth_check.php';

try {
    ensure_demo_accounts();

    $demoEmails = [
        'patient@nhre.gov',
        'doctor001@nhre.dev',
        'admin@nhre.gov',
        'sysadmin@nhre.gov',
        'pharmacist@nhre.gov',
        'lab@nhre.gov',
    ];
    $placeholders = implode(',', array_fill(0, count($demoEmails), '?'));
    $statement = db()->prepare("SELECT COUNT(DISTINCT email) FROM users WHERE email IN ($placeholders)");
    $statement->execute($demoEmails);
    $seededCount = (int)$statement->fetchColumn();

    if ($seededCount !== count($demoEmails)) {
        throw new RuntimeException(
            sprintf('Expected %d demo accounts after seeding; found %d.', count($demoEmails), $seededCount)
        );
    }

    fwrite(STDOUT, sprintf("Verified %d demo accounts are ready.\n", $seededCount));
} catch (PDOException $exception) {
    fwrite(STDERR, 'Demo account seeding failed: ' . $exception->getMessage() . "\n");
    exit(1);
} catch (RuntimeException $exception) {
    fwrite(STDERR, 'Demo account seeding failed: ' . $exception->getMessage() . "\n");
    exit(1);
}
