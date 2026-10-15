<?php
require_once __DIR__ . '/auth_check.php';

require_auth();
$kind = (string)($_GET['kind'] ?? '');
$filename = basename((string)($_GET['file'] ?? ''));
if ($kind !== 'profile' || $filename === '' || !preg_match('/^user_[0-9]+_[a-f0-9]+\.(?:png|jpg|webp|gif)$/', $filename)) {
    http_response_code(404);
    exit('File not found.');
}

$storedFile = nhre_read_stored_file('profile_pics', $filename);
if ($storedFile === null) {
    http_response_code(404);
    exit('File not found.');
}

header('Content-Type: ' . $storedFile['mime_type']);
header('Content-Length: ' . strlen($storedFile['contents']));
header('Cache-Control: private, max-age=3600');
header('X-Content-Type-Options: nosniff');
echo $storedFile['contents'];
