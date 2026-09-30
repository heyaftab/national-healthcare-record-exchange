<?php
declare(strict_types=1);
require_once __DIR__ . '/auth/auth_check.php';
require_role(['Lab Technician']);
$_GET['view'] = 'requests';
require __DIR__ . '/medical_tests.php';
